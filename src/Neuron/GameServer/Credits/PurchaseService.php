<?php

/**
 * Credit purchase lifecycle on CatLab Accounts.
 *
 * Trust model: the action and price come from our own n_credit_purchases row;
 * a grant additionally requires Accounts (client credentials) to report the
 * request ACCEPTED for the same credits and the player's own profile id.
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
			(int) $player->getId (), $credits, $description, $data,
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
		$result = $this->settle ($purchase, false);
		// 'expired' / 'failed' are internal outcomes of the reconcile path.
		return $result === 'expired' ? 'pending' : ($result === 'failed' ? 'ignored' : $result);
	}

	/**
	 * Settle pending purchases with a request id. Returns how many changed state.
	 */
	public function reconcile (int $limit = 50): int
	{
		$mapper = Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ();
		$rows = $mapper->getSettleable ($limit, $this->format ($this->now () - self::EXPIRED_RETRY_WINDOW));

		$settled = 0;
		foreach ($rows as $row)
		{
			$mapper->touch ($row['id'], $this->format ($this->now ()));
			$result = $this->settle ($row, true);
			if (in_array ($result, ['granted', 'refunded', 'expired', 'failed'], true))
			{
				$settled ++;
			}
		}
		return $settled;
	}

	/**
	 * Admin tool: refund a granted purchase. Claims granted -> refunded first.
	 */
	public function refundPurchase (int $purchaseId, string $reason): bool
	{
		$mapper = Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ();
		$purchase = $mapper->get ($purchaseId);
		if ($purchase === null || $purchase['credit_request_id'] === null || $purchase['status'] !== 'granted')
		{
			return false;
		}

		$profileId = $this->profileIdOf ($purchase);
		if ($profileId === null)
		{
			return false;
		}

		if (!$mapper->transition ($purchase['id'], 'granted', 'refunded', $this->format ($this->now ())))
		{
			return false;
		}

		return $this->issueRefund ($purchase, $profileId, $reason);
	}

	public function getPlayerPurchases (Neuron_GameServer_Player $player): array
	{
		return Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ()->getForPlayer ((int) $player->getId ());
	}

	private function settle (array $purchase, bool $fromReconcile): string
	{
		$mapper = Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ();

		// 'expired' rows stay settleable: a late confirmation gets refunded.
		if ($purchase['status'] !== 'pending' && $purchase['status'] !== 'expired')
		{
			return 'ignored';
		}
		$from = $purchase['status'];
		$requestId = (int) $purchase['credit_request_id'];

		try
		{
			$request = $this->client->getRequest ($requestId);
		}
		catch (Neuron_GameServer_Credits_AccountsUnavailable $e)
		{
			return 'pending';
		}
		catch (Neuron_GameServer_Credits_AccountsRejected $e)
		{
			// Likely a bad client secret: leave the row alone, shout, never grant or refund.
			error_log ('Credits: Accounts rejected getRequest ' . $requestId . ' (HTTP ' . $e->getStatus () . ')');
			$mapper->touch ($purchase['id'], $this->format ($this->now ()));
			return 'pending';
		}

		if ($request === null)
		{
			return $this->unsettleable ($purchase, $from, 'request not found on Accounts');
		}

		if (strtoupper ((string) ($request['status'] ?? '')) !== 'ACCEPTED')
		{
			if ($from === 'pending' && $this->isExpired ($purchase)
				&& $mapper->transition ($purchase['id'], 'pending', 'expired', $this->format ($this->now ())))
			{
				return 'expired';
			}
			return 'pending';
		}

		$profileId = $this->profileIdOf ($purchase);
		$requestProfileId = isset ($request['user']['id']) ? (int) $request['user']['id'] : 0;

		if ((int) ($request['credits'] ?? -1) !== $purchase['credits'] || $profileId === null || $requestProfileId !== $profileId)
		{
			return $this->unsettleable ($purchase, $from, 'accepted request does not match purchase credits/profile');
		}

		if ($this->isExpired ($purchase))
		{
			return $this->claimAndRefund ($purchase, $from, $profileId, 'Purchase expired');
		}

		$player = ($this->playerLoader) ((int) $purchase['plid']);
		if (!$player)
		{
			return $this->claimAndRefund ($purchase, $from, $profileId, 'Player not found');
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
		$this->issueRefund ($purchase, $profileId, 'Action could not be applied');
		return 'refunded';
	}

	/**
	 * A row that cannot be matched to its request: ignore while the purchase
	 * is live, fail it for good once past expiry so it cannot clog reconcile.
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

	private function claimAndRefund (array $purchase, string $from, int $profileId, string $reason): string
	{
		$mapper = Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ();
		if (!$mapper->transition ($purchase['id'], $from, 'refunded', $this->format ($this->now ())))
		{
			return 'ignored';
		}
		$this->issueRefund ($purchase, $profileId, $reason);
		return 'refunded';
	}

	/**
	 * Row is already claimed as 'refunded'. If Accounts refuses or is down,
	 * mark it 'failed' and log so an admin can act; a refund we could not
	 * issue must be visible, not silently retried.
	 */
	private function issueRefund (array $purchase, int $profileId, string $reason): bool
	{
		$mapper = Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ();

		try
		{
			$ok = $this->client->refund ($profileId, $purchase['credits'], $reason, $purchase['credit_request_id']);
		}
		catch (Neuron_GameServer_Credits_AccountsUnavailable | Neuron_GameServer_Credits_AccountsRejected $e)
		{
			$ok = false;
		}

		if (!$ok)
		{
			error_log ('Credits: REFUND FAILED for purchase ' . $purchase['id'] . ' (request ' . $purchase['credit_request_id'] . ', ' . $purchase['credits'] . ' credits, profile ' . $profileId . '): manual action needed');
			$mapper->transition ($purchase['id'], 'refunded', 'failed', $this->format ($this->now ()));
		}
		return $ok;
	}

	private function profileIdOf (array $purchase): ?int
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
