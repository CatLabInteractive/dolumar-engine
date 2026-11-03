<?php

/**
 * Credit purchase lifecycle on CatLab Accounts.
 *
 * Trust model: the action and price come from our own n_credit_purchases row;
 * a grant additionally requires Accounts (client credentials) to report the
 * request ACCEPTED for the same credits and the profile id stored on the row
 * when the purchase started.
 *
 * Money rule: an ACCEPTED (charged) request is either granted once or
 * refunded to the charged profile (the request's user.id), always with its
 * credit_request_id so Accounts caps the refund at what was paid. A refund
 * that cannot reach Accounts is kept as 'refund_pending' and retried by
 * reconcile; only a definitive refusal ends in 'failed' (logged).
 *
 * Exactly-once: every state change is a compare-and-set UPDATE
 * (see CreditPurchaseMapper::transition). The row is CLAIMED (pending ->
 * granted, or -> refunded) before any side effect runs, so useCredit() and
 * refund() can each run at most once per purchase, whatever the number of
 * concurrent notifies/reconciles.
 */
class Neuron_GameServer_Credits_PurchaseService
{
	const TTL = 3600;
	const EXPIRED_RETRY_WINDOW = 604800;

	private Neuron_GameServer_Credits_AccountsClient $client;
	private $now;
	private $playerLoader;

	/**
	 * @param callable|null $now          returns a unix timestamp
	 * @param callable|null $playerLoader int plid => ?Neuron_GameServer_Player
	 */
	public function __construct (Neuron_GameServer_Credits_AccountsClient $client, ?callable $now = null, ?callable $playerLoader = null)
	{
		$this->client = $client;
		$this->now = $now ?? 'time';
		$this->playerLoader = $playerLoader ?? function ($plid) {
			return Neuron_GameServer_Player::getFromId ($plid);
		};
	}

	/**
	 * @return array{status:string, url:?string}
	 */
	public function start (Neuron_GameServer_Player $player, array $data): array
	{
		$quote = $player->quoteCreditAction ($data);
		if ($quote === null || (int) ($quote['credits'] ?? 0) <= 0)
		{
			return ['status' => 'invalid', 'url' => null];
		}

		$auth = Neuron_GameServer_Mappers_CatLabAuthMapper::getInstance ()->getForPlayer ($player);
		if ($auth === null || $auth['profile_id'] === null || $auth['access_token'] === '')
		{
			return ['status' => 'login', 'url' => null];
		}

		$credits = (int) $quote['credits'];
		$description = (string) $quote['description'];
		$mapper = Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ();
		$now = $this->now ();

		// The client-supplied amount is never used for pricing (nor stored).
		unset ($data['amount']);

		$purchaseId = $mapper->insert
		(
			(int) $player->getId (), (int) $auth['profile_id'], $credits, $description, $data,
			$this->format ($now), $this->format ($now + self::TTL)
		);

		try
		{
			$request = $this->client->createRequest
			(
				$auth['access_token'], (int) $auth['profile_id'], $credits, $description,
				API_FULL_URL . 'creditnotify/', ABSOLUTE_URL, ['purchase_id' => $purchaseId]
			);
		}
		catch (Neuron_GameServer_Credits_AccountsRejected $e)
		{
			error_log ('Credits: Accounts rejected createRequest for purchase ' . $purchaseId . ' (HTTP ' . $e->getStatus () . ')');
			$mapper->transition ($purchaseId, 'pending', 'failed', $this->format ($this->now ()));
			// Revoked token or credits scope not granted: a fresh login fixes it.
			if (in_array ($e->getStatus (), [401, 403], true))
			{
				return ['status' => 'login', 'url' => null];
			}
			return ['status' => 'unavailable', 'url' => null];
		}
		catch (Neuron_GameServer_Credits_AccountsUnavailable $e)
		{
			// No request exists on Accounts, so nothing can ever settle this row.
			error_log ('Credits: Accounts unavailable on createRequest for purchase ' . $purchaseId);
			$mapper->transition ($purchaseId, 'pending', 'failed', $this->format ($this->now ()));
			return ['status' => 'unavailable', 'url' => null];
		}

		$mapper->setRequestId ($purchaseId, (int) $request['id']);

		$url = (string) $request['confirmUrl'];
		try
		{
			$token = $this->client->getLoginToken ($auth['access_token']);
		}
		catch (Neuron_GameServer_Credits_AccountsUnavailable | Neuron_GameServer_Credits_AccountsRejected $e)
		{
			$token = null;
		}
		if ($token !== null && $token !== '')
		{
			$url .= (strpos ($url, '?') === false ? '?' : '&') . 'authcode=' . rawurlencode ($token);
		}

		return ['status' => 'redirect', 'url' => $url];
	}

	/**
	 * @return string granted|refunded|ignored|pending
	 */
	public function handleNotify (int $creditRequestId): string
	{
		$purchase = Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ()->getByRequestId ($creditRequestId);
		if ($purchase === null)
		{
			return 'ignored';
		}
		$result = $this->settle ($purchase);
		// 'expired' / 'failed' / 'refund_pending' are internal outcomes of the reconcile path.
		switch ($result)
		{
			case 'expired':
			case 'refund_pending':
				return 'pending';
			case 'failed':
				return 'ignored';
			default:
				return $result;
		}
	}

	/**
	 * Settle pending purchases with a request id and retry refunds that could
	 * not reach Accounts. Returns how many reached a final state.
	 */
	public function reconcile (int $limit = 50): int
	{
		return $this->reconcileRows
		(
			Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ()
				->getSettleable ($limit, $this->format ($this->now () - self::EXPIRED_RETRY_WINDOW))
		);
	}

	/**
	 * Same as reconcile(), limited to one player's purchases.
	 */
	public function reconcileForPlayer (Neuron_GameServer_Player $player, int $limit = 10): int
	{
		return $this->reconcileRows
		(
			Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ()
				->getSettleable ($limit, $this->format ($this->now () - self::EXPIRED_RETRY_WINDOW), (int) $player->getId ())
		);
	}

	private function reconcileRows (array $rows): int
	{
		$mapper = Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ();
		$settled = 0;
		foreach ($rows as $row)
		{
			$mapper->touch ($row['id'], $this->format ($this->now ()));
			$result = $this->settle ($row);
			if (in_array ($result, ['granted', 'refunded', 'expired', 'failed'], true))
			{
				$settled ++;
			}
		}
		return $settled;
	}

	/**
	 * Admin tool: refund a granted purchase. True when the refund was issued
	 * or is queued for retry (refund_pending).
	 */
	public function refundPurchase (int $purchaseId, string $reason): bool
	{
		$status = $this->refundGranted ($purchaseId, $reason);
		return $status === 'refunded' || $status === 'refund_pending';
	}

	/**
	 * Admin premium refund: refund the newest $blocks granted premium purchases
	 * of this player (one purchase = one block of premium days).
	 *
	 * @return array{refunded:int, pending:int, failed:int, purchases:int} credits per outcome, purchases touched
	 */
	public function refundNewestPremium (Neuron_GameServer_Player $player, int $blocks, string $reason): array
	{
		$out = ['refunded' => 0, 'pending' => 0, 'failed' => 0, 'purchases' => 0];
		if ($blocks < 1)
		{
			return $out;
		}

		// getPlayerPurchases is newest first.
		foreach ($this->getPlayerPurchases ($player) as $purchase)
		{
			$action = $purchase['action_data']['action'] ?? '';
			if ($purchase['status'] !== 'granted' || ($action !== '' && $action !== 'premium'))
			{
				continue;
			}

			$status = $this->refundGranted ($purchase['id'], $reason);
			if ($status !== null)
			{
				$out['purchases'] ++;
				$key = $status === 'refund_pending' ? 'pending' : ($status === 'refunded' ? 'refunded' : 'failed');
				$out[$key] += $purchase['credits'];
			}

			if ($out['purchases'] >= $blocks)
			{
				break;
			}
		}
		return $out;
	}

	public function getPlayerPurchases (Neuron_GameServer_Player $player): array
	{
		return Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ()->getForPlayer ((int) $player->getId ());
	}

	/**
	 * Claim granted -> refunded first, then refund the profile charged at start.
	 *
	 * @return string|null refunded|refund_pending|failed, null when not refundable
	 */
	private function refundGranted (int $purchaseId, string $reason): ?string
	{
		$mapper = Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ();
		$purchase = $mapper->get ($purchaseId);
		if ($purchase === null || $purchase['credit_request_id'] === null || $purchase['status'] !== 'granted')
		{
			return null;
		}

		// A granted row was charged to its stored profile (legacy rows: the current one).
		$profileId = $purchase['profile_id'] ?? $this->currentProfileIdOf ($purchase);
		if ($profileId === null)
		{
			return null;
		}

		if (!$mapper->transition ($purchase['id'], 'granted', 'refunded', $this->format ($this->now ())))
		{
			return null;
		}

		return $this->issueRefund ($purchase, $profileId, $purchase['credits'], $reason);
	}

	private function settle (array $purchase): string
	{
		$mapper = Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ();

		if ($purchase['status'] === 'refund_pending')
		{
			return $this->retryRefund ($purchase);
		}

		// 'expired' rows stay settleable: a late confirmation gets refunded.
		if ($purchase['status'] !== 'pending' && $purchase['status'] !== 'expired')
		{
			return 'ignored';
		}
		$from = $purchase['status'];

		$request = $this->fetchRequest ($purchase);
		if ($request === false)
		{
			return 'pending';
		}

		if ($request === null)
		{
			return $this->unsettleable ($purchase, $from, 'request not found on Accounts');
		}

		$status = strtoupper ((string) ($request['status'] ?? ''));
		if ($status !== 'ACCEPTED')
		{
			// No status at all is treated like PENDING.
			$final = $status !== 'PENDING' && $status !== '';
			if ($from === 'pending' && ($final || $this->isExpired ($purchase))
				&& $mapper->transition ($purchase['id'], 'pending', 'expired', $this->format ($this->now ())))
			{
				if ($final)
				{
					// A final status that is not a charge (Accounts has none today). The row
					// stays settleable as 'expired' for a while, so a later ACCEPTED is refunded.
					error_log ('Credits: purchase ' . $purchase['id'] . ' request ' . $purchase['credit_request_id'] . ' has status ' . $status . ': not charged, closed');
				}
				return 'expired';
			}
			return 'pending';
		}

		// ACCEPTED: the player was charged. From here on, grant or refund.
		$chargedProfile = isset ($request['user']['id']) ? (int) $request['user']['id'] : 0;
		$chargedCredits = (int) ($request['credits'] ?? 0);
		if ($chargedProfile <= 0 || $chargedCredits <= 0)
		{
			return $this->unsettleable ($purchase, $from, 'accepted request without a charged profile or credits');
		}

		if ($chargedCredits !== $purchase['credits'])
		{
			error_log ('Credits: purchase ' . $purchase['id'] . ' request ' . $purchase['credit_request_id'] . ' charged ' . $chargedCredits . ' credits, expected ' . $purchase['credits'] . ': refunding');
			return $this->claimAndRefund ($purchase, $from, $chargedProfile, $chargedCredits, 'Purchase could not be applied');
		}

		$expectedProfile = $purchase['profile_id'] ?? $this->currentProfileIdOf ($purchase);
		if ($expectedProfile === null || $chargedProfile !== $expectedProfile)
		{
			error_log ('Credits: purchase ' . $purchase['id'] . ' request ' . $purchase['credit_request_id'] . ' charged profile ' . $chargedProfile . ', expected ' . ($expectedProfile ?? 'none') . ': refunding');
			return $this->claimAndRefund ($purchase, $from, $chargedProfile, $chargedCredits, 'Purchase could not be applied');
		}

		if ($this->isExpired ($purchase))
		{
			return $this->claimAndRefund ($purchase, $from, $chargedProfile, $chargedCredits, 'Purchase expired');
		}

		$player = ($this->playerLoader) ((int) $purchase['plid']);
		if (!$player)
		{
			return $this->claimAndRefund ($purchase, $from, $chargedProfile, $chargedCredits, 'Player not found');
		}

		// Claim BEFORE useCredit: only the caller whose UPDATE changed the row may apply it.
		if (!$mapper->transition ($purchase['id'], $from, 'granted', $this->format ($this->now ())))
		{
			return 'ignored';
		}

		try
		{
			$applied = $player->useCredit ($purchase['credits'], $purchase['action_data']) === true;
		}
		catch (Throwable $e)
		{
			error_log ('Credits: useCredit threw for purchase ' . $purchase['id'] . ': ' . $e->getMessage ());
			$applied = false;
		}

		if ($applied)
		{
			return 'granted';
		}

		// Could not apply: granted -> refunded, then refund.
		if (!$mapper->transition ($purchase['id'], 'granted', 'refunded', $this->format ($this->now ())))
		{
			return 'ignored';
		}
		return $this->issueRefund ($purchase, $chargedProfile, $chargedCredits, 'Action could not be applied');
	}

	/**
	 * Retry a refund that could not reach Accounts. The charged profile and
	 * amount come from Accounts itself, so a retry refunds exactly the charge.
	 */
	private function retryRefund (array $purchase): string
	{
		$mapper = Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ();

		$request = $this->fetchRequest ($purchase);
		if ($request === false)
		{
			return 'refund_pending';
		}

		$chargedProfile = isset ($request['user']['id']) ? (int) $request['user']['id'] : 0;
		$chargedCredits = (int) ($request['credits'] ?? 0);
		if ($request === null || strtoupper ((string) ($request['status'] ?? '')) !== 'ACCEPTED' || $chargedProfile <= 0 || $chargedCredits <= 0)
		{
			if ($mapper->transition ($purchase['id'], 'refund_pending', 'failed', $this->format ($this->now ())))
			{
				error_log ('Credits: REFUND FAILED for purchase ' . $purchase['id'] . ' (request ' . $purchase['credit_request_id'] . '): Accounts no longer reports an accepted charge; marked failed, manual action needed');
				return 'failed';
			}
			return 'ignored';
		}

		if (!$mapper->transition ($purchase['id'], 'refund_pending', 'refunded', $this->format ($this->now ())))
		{
			return 'ignored';
		}

		return $this->issueRefund ($purchase, $chargedProfile, $chargedCredits, 'Refund: ' . $purchase['description']);
	}

	/**
	 * @return array|null|false the request, null when Accounts does not know it,
	 *                          false when it cannot be asked right now
	 */
	private function fetchRequest (array $purchase)
	{
		$requestId = (int) $purchase['credit_request_id'];
		try
		{
			return $this->client->getRequest ($requestId);
		}
		catch (Neuron_GameServer_Credits_AccountsUnavailable $e)
		{
			return false;
		}
		catch (Neuron_GameServer_Credits_AccountsRejected $e)
		{
			// Likely a bad client secret: leave the row alone, shout, never grant or refund.
			error_log ('Credits: Accounts rejected getRequest ' . $requestId . ' (HTTP ' . $e->getStatus () . ')');
			return false;
		}
	}

	/**
	 * A row that cannot be matched to a charge: ignore while the purchase
	 * is live, fail it for good once past expiry so it cannot clog reconcile.
	 * Only used when Accounts reports no charge we could refund.
	 */
	private function unsettleable (array $purchase, string $from, string $reason): string
	{
		error_log ('Credits: purchase ' . $purchase['id'] . ' request ' . $purchase['credit_request_id'] . ': ' . $reason);
		if ($this->isExpired ($purchase)
			&& Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ()->transition ($purchase['id'], $from, 'failed', $this->format ($this->now ())))
		{
			error_log ('Credits: purchase ' . $purchase['id'] . ' request ' . $purchase['credit_request_id'] . ' marked failed (' . $reason . ')');
			return 'failed';
		}
		return 'ignored';
	}

	private function claimAndRefund (array $purchase, string $from, int $profileId, int $credits, string $reason): string
	{
		$mapper = Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ();
		if (!$mapper->transition ($purchase['id'], $from, 'refunded', $this->format ($this->now ())))
		{
			return 'ignored';
		}
		return $this->issueRefund ($purchase, $profileId, $credits, $reason);
	}

	/**
	 * Row is already claimed as 'refunded'. Accounts unreachable (network or
	 * 5xx): 'refund_pending', retried by reconcile. Accounts refuses (4xx):
	 * 'failed' and logged for an admin. "Exceeds the purchase" means an
	 * earlier attempt already went through: done.
	 *
	 * @return string refunded|refund_pending|failed
	 */
	private function issueRefund (array $purchase, int $profileId, int $credits, string $reason): string
	{
		$mapper = Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ();
		$what = 'purchase ' . $purchase['id'] . ' (request ' . $purchase['credit_request_id'] . ', ' . $credits . ' credits, profile ' . $profileId . ')';

		try
		{
			$result = $this->client->refundRequest ($profileId, $credits, $reason, (int) $purchase['credit_request_id']);
			if ($result === 'already_refunded')
			{
				error_log ('Credits: refund for ' . $what . ': Accounts reports it already refunded, treating as done');
			}
			return 'refunded';
		}
		catch (Neuron_GameServer_Credits_AccountsUnavailable $e)
		{
			error_log ('Credits: refund for ' . $what . ' could not reach Accounts; will retry');
			$mapper->transition ($purchase['id'], 'refunded', 'refund_pending', $this->format ($this->now ()));
			return 'refund_pending';
		}
		catch (Neuron_GameServer_Credits_AccountsRejected $e)
		{
			error_log ('Credits: REFUND FAILED for ' . $what . ': ' . $e->getMessage () . '; marked failed, manual action needed');
			$mapper->transition ($purchase['id'], 'refunded', 'failed', $this->format ($this->now ()));
			return 'failed';
		}
	}

	/**
	 * The player's current Accounts profile. Only for rows created before
	 * profile_id was stored on the purchase.
	 */
	private function currentProfileIdOf (array $purchase): ?int
	{
		$player = ($this->playerLoader) ((int) $purchase['plid']);
		if (!$player)
		{
			return null;
		}
		$auth = Neuron_GameServer_Mappers_CatLabAuthMapper::getInstance ()->getForPlayer ($player);
		return $auth === null ? null : $auth['profile_id'];
	}

	private function isExpired (array $purchase): bool
	{
		return strtotime ($purchase['expires_at']) < $this->now ();
	}

	private function now (): int
	{
		return (int) ($this->now) ();
	}

	private function format (int $ts): string
	{
		return date ('Y-m-d H:i:s', $ts);
	}
}
