<?php

use PHPUnit\Framework\TestCase;

class FakeCreditsClient extends Neuron_GameServer_Credits_AccountsClient
{
	public $created = [];
	public $refunds = [];
	public $requests = [];
	public $getRequestThrows = null;
	public $createThrows = null;
	public $refundResult = true;
	/** @var array queue of outcomes for refundRequest: 'refunded' | 'already_refunded' | Throwable */
	public $refundOutcomes = [];
	public $nextId = 5001;
	public $onGetRequest = null;

	public function __construct () {}

	public function createRequest (string $accessToken, int $profileId, int $credits, string $reason, string $notifyUrl, string $returnUrl, array $extra): array
	{
		if ($this->createThrows) { throw $this->createThrows; }
		$this->created[] = compact ('accessToken', 'profileId', 'credits', 'reason', 'notifyUrl', 'returnUrl', 'extra');
		return ['id' => $this->nextId++, 'confirmUrl' => 'https://accounts.example.test/confirm/1'];
	}

	public function getLoginToken (string $accessToken): ?string
	{
		return 'LT';
	}

	public function getRequest (int $requestId): ?array
	{
		if ($this->getRequestThrows) { throw $this->getRequestThrows; }
		if ($this->onGetRequest) { $cb = $this->onGetRequest; $this->onGetRequest = null; $cb (); }
		return $this->requests[$requestId] ?? null;
	}

	public function refund (int $profileId, int $credits, string $reason, ?int $creditRequestId): bool
	{
		throw new LogicException ('Purchases must refund through refundRequest');
	}

	public function refundRequest (int $profileId, int $credits, string $reason, int $creditRequestId): string
	{
		$this->refunds[] = [$profileId, $credits, $creditRequestId];
		$outcome = array_shift ($this->refundOutcomes) ?? ($this->refundResult ? 'refunded' : new Neuron_GameServer_Credits_AccountsRejected (400, 'no'));
		if ($outcome instanceof Throwable) { throw $outcome; }
		return $outcome;
	}
}

class PurchaseServiceTest extends TestCase
{
	private const PREFIX = 'https://accounts.example.test/users/';
	private const PLID = 770001;
	private const OTHER_PLID = 770002;
	private const PROFILE = 4242;

	private $clock = 1800000000;
	private $client;
	private $service;
	private $useCalls = 0;
	private $useResult = true;
	private $player;

	public static function setUpBeforeClass (): void
	{
		foreach (['OPENID_CONNECT_PROFILE_URL_PREFIX' => self::PREFIX, 'API_FULL_URL' => 'https://api.example.test/', 'ABSOLUTE_URL' => 'https://game.example.test/', 'PREMIUM_COST_CREDITS' => 100] as $k => $v)
		{
			if (!defined ($k)) { define ($k, $v); }
		}
	}

	private function clean (): void
	{
		$db = Neuron_DB_Database::getInstance ();
		$db->query ("DELETE FROM n_credit_purchases WHERE plid IN (" . self::PLID . ", " . self::OTHER_PLID . ")");
		$db->query ("DELETE FROM n_auth_catlab WHERE openid_url LIKE '%770001'");
		$db->query ("DELETE FROM n_auth_openid WHERE openid_url LIKE '%770001'");
	}

	protected function setUp (): void
	{
		$this->clean ();
		$this->useCalls = 0;
		$this->useResult = true;
		$this->client = new FakeCreditsClient ();
		$this->player = $this->player ();
		$player = $this->player;
		$this->service = new Neuron_GameServer_Credits_PurchaseService
		(
			$this->client, function () { return $this->clock; }, function ($plid) use ($player) { return $plid === self::PLID ? $player : null; }
		);
		$this->login (self::PROFILE);
	}

	protected function tearDown (): void
	{
		$this->clean ();
	}

	private function login (?int $profile, string $token = 'tok'): void
	{
		$db = Neuron_DB_Database::getInstance ();
		$db->query ("DELETE FROM n_auth_catlab WHERE openid_url LIKE '%770001'");
		$db->query ("DELETE FROM n_auth_openid WHERE openid_url LIKE '%770001'");
		$url = self::PREFIX . '770001';
		$db->query ("INSERT INTO n_auth_openid SET openid_url = '" . $url . "', user_id = " . self::PLID);
		Neuron_GameServer_Mappers_CatLabAuthMapper::getInstance ()->save ($url, $profile, $token, 'openid credits');
	}

	private function player (): Neuron_GameServer_Player
	{
		$test = $this;
		$player = $this->getMockBuilder (Neuron_GameServer_Player::class)->disableOriginalConstructor ()
			->onlyMethods (['getId', 'useCredit'])->getMock ();
		$player->method ('getId')->willReturn (self::PLID);
		$player->method ('useCredit')->willReturnCallback (function () use ($test) {
			$test->useCalls ++;
			return $test->useResult;
		});
		return $player;
	}

	private function started (): int
	{
		$r = $this->service->start ($this->service_player (), ['action' => 'premium']);
		$this->assertSame ('redirect', $r['status']);
		return 5001 + count ($this->client->created) - 1;
	}

	private function service_player (): Neuron_GameServer_Player
	{
		return $this->player;
	}

	private function accept (int $reqId, int $credits = 100, int $profile = self::PROFILE, string $status = 'ACCEPTED'): void
	{
		$this->client->requests[$reqId] = ['id' => $reqId, 'credits' => $credits, 'status' => $status, 'user' => ['id' => $profile]];
	}

	private function rowStatus (int $reqId): string
	{
		return Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ()->getByRequestId ($reqId)['status'];
	}

	public function testStartUsesServerQuoteNotAmount (): void
	{
		$r = $this->service->start ($this->service_player (), ['action' => 'premium', 'amount' => 1]);
		$this->assertSame ('redirect', $r['status']);
		$this->assertSame ('https://accounts.example.test/confirm/1?authcode=LT', $r['url']);
		$c = $this->client->created[0];
		$this->assertSame (100, $c['credits']);
		$this->assertSame (self::PROFILE, $c['profileId']);
		$this->assertSame ('https://api.example.test/creditnotify/', $c['notifyUrl']);
		$this->assertSame ('https://game.example.test/', $c['returnUrl']);
		$row = Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ()->getByRequestId (5001);
		$this->assertSame (100, $row['credits']);
		$this->assertSame ($row['id'], $c['extra']['purchase_id']);
		$this->assertSame (date ('Y-m-d H:i:s', $this->clock + 3600), $row['expires_at']);
	}

	public function testStartWithoutTokenAsksLogin (): void
	{
		Neuron_DB_Database::getInstance ()->query ("DELETE FROM n_auth_catlab WHERE openid_url LIKE '%770001'");
		$this->assertSame ('login', $this->service->start ($this->service_player (), ['action' => 'premium'])['status']);
		$this->login (null);
		$this->assertSame ('login', $this->service->start ($this->service_player (), ['action' => 'premium'])['status']);
		$this->assertSame ([], $this->client->created);
	}

	public function testStartInvalidActionCreatesNothing (): void
	{
		$this->assertSame ('invalid', $this->service->start ($this->service_player (), ['action' => 'nonsense'])['status']);
		$this->assertSame ([], $this->client->created);
		$this->assertSame ([], $this->service->getPlayerPurchases ($this->service_player ()));
	}

	public function testStartRejectedMarksFailed (): void
	{
		$this->client->createThrows = new Neuron_GameServer_Credits_AccountsRejected (422, 'x');
		$this->quiet (function () {
			$this->assertSame ('unavailable', $this->service->start ($this->service_player (), ['action' => 'premium'])['status']);
		});
		$rows = $this->service->getPlayerPurchases ($this->service_player ());
		$this->assertSame ('failed', $rows[0]['status']);
	}

	public function testNotifyGrantsOnce (): void
	{
		$id = $this->started ();
		$this->accept ($id);
		$this->assertSame ('granted', $this->service->handleNotify ($id));
		$this->assertSame ('ignored', $this->service->handleNotify ($id));
		$this->assertSame (1, $this->useCalls);
		$this->assertSame ([], $this->client->refunds);
	}

	public function testNotifyPendingDoesNothing (): void
	{
		$id = $this->started ();
		$this->accept ($id, 100, self::PROFILE, 'PENDING');
		$this->assertSame ('pending', $this->service->handleNotify ($id));
		$this->assertSame ('pending', $this->rowStatus ($id));
		$this->assertSame (0, $this->useCalls);
	}

	public function testNotifyWrongAmountRefundsWhatWasCharged (): void
	{
		$id = $this->started ();
		$this->accept ($id, 1);
		$this->quiet (function () use ($id) {
			$this->assertSame ('refunded', $this->service->handleNotify ($id));
		});
		$this->assertSame ('refunded', $this->rowStatus ($id));
		$this->assertSame (0, $this->useCalls);
		$this->assertSame ([[self::PROFILE, 1, $id]], $this->client->refunds);
		$this->assertSame ('ignored', $this->service->handleNotify ($id));
		$this->assertCount (1, $this->client->refunds);
	}

	public function testNotifyWrongProfileRefundsChargedProfile (): void
	{
		$id = $this->started ();
		$this->accept ($id, 100, 9999);
		$this->quiet (function () use ($id) {
			$this->assertSame ('refunded', $this->service->handleNotify ($id));
		});
		$this->assertSame ('refunded', $this->rowStatus ($id));
		$this->assertSame (0, $this->useCalls);
		$this->assertSame ([[9999, 100, $id]], $this->client->refunds);
	}

	public function testStartStoresProfile (): void
	{
		$id = $this->started ();
		$this->assertSame (self::PROFILE, Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ()->getByRequestId ($id)['profile_id']);
	}

	public function testProfileChangeAfterStartStillGrants (): void
	{
		// The player re-logs with another linked Accounts login after paying.
		$id = $this->started ();
		$this->accept ($id);
		$this->login (5555);
		$this->assertSame ('granted', $this->service->handleNotify ($id));
		$this->assertSame (1, $this->useCalls);
		$this->assertSame ([], $this->client->refunds);
	}

	public function testChargedOtherThanStoredProfileIsRefunded (): void
	{
		// Even when the player's current profile matches the charge, the row's own profile decides.
		$id = $this->started ();
		$this->login (5555);
		$this->accept ($id, 100, 5555);
		$this->quiet (function () use ($id) {
			$this->assertSame ('refunded', $this->service->handleNotify ($id));
		});
		$this->assertSame (0, $this->useCalls);
		$this->assertSame ([[5555, 100, $id]], $this->client->refunds);
	}

	public function testLegacyRowWithoutProfileUsesCurrentProfile (): void
	{
		$id = $this->started ();
		Neuron_DB_Database::getInstance ()->query ("UPDATE n_credit_purchases SET profile_id = NULL WHERE credit_request_id = " . $id);
		$this->accept ($id);
		$this->assertSame ('granted', $this->service->handleNotify ($id));
		$this->assertSame (1, $this->useCalls);
	}

	public function testDeletedPlayerIsRefunded (): void
	{
		$id = $this->started ();
		$this->accept ($id);
		$service = new Neuron_GameServer_Credits_PurchaseService ($this->client, function () { return $this->clock; }, function () { return null; });
		$this->assertSame ('refunded', $service->handleNotify ($id));
		$this->assertSame ([[self::PROFILE, 100, $id]], $this->client->refunds);
		$this->assertSame ('refunded', $this->rowStatus ($id));
		$this->assertSame (0, $this->useCalls);
	}

	public function testAcceptedWithoutChargedProfileFailsOnlyAfterExpiry (): void
	{
		$id = $this->started ();
		$this->client->requests[$id] = ['id' => $id, 'credits' => 100, 'status' => 'ACCEPTED', 'user' => []];
		$this->quiet (function () use ($id) {
			$this->assertSame ('ignored', $this->service->handleNotify ($id));
			$this->assertSame ('pending', $this->rowStatus ($id));
			$this->clock += 3601;
			$this->assertSame ('ignored', $this->service->handleNotify ($id));
		});
		$this->assertSame ('failed', $this->rowStatus ($id));
		$this->assertSame ([], $this->client->refunds);
	}

	public function testDeclinedRequestClosesWithoutPolling (): void
	{
		$id = $this->started ();
		$this->accept ($id, 100, self::PROFILE, 'DECLINED');
		$this->quiet (function () use ($id) {
			$this->assertSame ('pending', $this->service->handleNotify ($id));
		});
		$this->assertSame ('expired', $this->rowStatus ($id));
		$this->assertSame (0, $this->useCalls);
		$this->assertSame ([], $this->client->refunds);

		// Should Accounts still accept it after the purchase expired, it is refunded, never granted.
		$this->clock += 3601;
		$this->accept ($id);
		$this->assertSame ('refunded', $this->service->handleNotify ($id));
		$this->assertSame (0, $this->useCalls);
		$this->assertCount (1, $this->client->refunds);
	}

	public function testStartRevokedTokenAsksLogin (): void
	{
		foreach ([401, 403] as $status)
		{
			$this->client->createThrows = new Neuron_GameServer_Credits_AccountsRejected ($status, 'x');
			$this->quiet (function () {
				$this->assertSame ('login', $this->service->start ($this->service_player (), ['action' => 'premium'])['status']);
			});
			$this->assertSame ('failed', $this->service->getPlayerPurchases ($this->service_player ())[0]['status']);
		}
	}

	private function quiet (callable $fn): void
	{
		$prev = ini_set ('error_log', '/dev/null');
		try { $fn (); } finally { ini_set ('error_log', $prev); }
	}

	public function testUnknownRequestIgnored (): void
	{
		$this->assertSame ('ignored', $this->service->handleNotify (987654));
	}

	public function testExpiredPurchaseIsRefunded (): void
	{
		$id = $this->started ();
		$this->accept ($id);
		$this->clock += 3601;
		$this->assertSame ('refunded', $this->service->handleNotify ($id));
		$this->assertSame ([[self::PROFILE, 100, $id]], $this->client->refunds);
		$this->assertSame ('refunded', $this->rowStatus ($id));
		$this->assertSame (0, $this->useCalls);
	}

	public function testInapplicableActionIsRefunded (): void
	{
		$id = $this->started ();
		$this->accept ($id);
		$this->useResult = false;
		$this->assertSame ('refunded', $this->service->handleNotify ($id));
		$this->assertSame ([[self::PROFILE, 100, $id]], $this->client->refunds);
		$this->assertSame ('refunded', $this->rowStatus ($id));
		$this->assertSame ('ignored', $this->service->handleNotify ($id));
		$this->assertCount (1, $this->client->refunds);
	}

	public function testFailedRefundIsMarkedFailed (): void
	{
		$id = $this->started ();
		$this->accept ($id);
		$this->useResult = false;
		$this->client->refundResult = false;
		$prev = ini_set ('error_log', '/dev/null');
		$this->assertSame ('ignored', $this->service->handleNotify ($id));
		ini_set ('error_log', $prev);
		$this->assertSame ('failed', $this->rowStatus ($id));
		// A definitive refusal is terminal: reconcile does not retry it.
		$this->assertSame (0, $this->service->reconcileForPlayer ($this->player));
		$this->assertCount (1, $this->client->refunds);
	}

	public function testTransientRefundFailureIsRetried (): void
	{
		$id = $this->started ();
		$this->accept ($id);
		$this->useResult = false;
		$this->client->refundOutcomes = [new Neuron_GameServer_Credits_AccountsUnavailable ('down')];
		$this->quiet (function () use ($id) {
			$this->assertSame ('pending', $this->service->handleNotify ($id));
		});
		$this->assertSame ('refund_pending', $this->rowStatus ($id));

		// Retry while Accounts is still down: stays pending.
		$this->client->refundOutcomes = [new Neuron_GameServer_Credits_AccountsUnavailable ('down')];
		$this->quiet (function () {
			$this->assertSame (0, $this->service->reconcileForPlayer ($this->player));
		});
		$this->assertSame ('refund_pending', $this->rowStatus ($id));

		$this->clock += 10;
		$this->assertSame (1, $this->service->reconcileForPlayer ($this->player));
		$this->assertSame ('refunded', $this->rowStatus ($id));
		$this->assertSame ([[self::PROFILE, 100, $id], [self::PROFILE, 100, $id], [self::PROFILE, 100, $id]], $this->client->refunds);
		$this->assertSame (0, $this->service->reconcileForPlayer ($this->player));
		$this->assertCount (3, $this->client->refunds);
		$this->assertSame (1, $this->useCalls);
	}

	public function testRetryAfterRefundWentThroughIsDone (): void
	{
		// First attempt reached Accounts but the answer was lost; the retry is told it exceeds the purchase.
		$id = $this->started ();
		$this->accept ($id);
		$this->clock += 3601;
		$this->client->refundOutcomes = [new Neuron_GameServer_Credits_AccountsUnavailable ('timeout'), 'already_refunded'];
		$this->quiet (function () use ($id) {
			$this->assertSame ('pending', $this->service->handleNotify ($id));
			$this->assertSame ('refund_pending', $this->rowStatus ($id));
			$this->assertSame (1, $this->service->reconcileForPlayer ($this->player));
		});
		$this->assertSame ('refunded', $this->rowStatus ($id));
		$this->assertSame (0, $this->service->reconcileForPlayer ($this->player));
		$this->assertCount (2, $this->client->refunds);
		$this->assertSame (0, $this->useCalls);
	}

	public function testRetryRefundsTheChargeReportedByAccounts (): void
	{
		$id = $this->started ();
		$this->accept ($id, 1, 9999);
		$this->client->refundOutcomes = [new Neuron_GameServer_Credits_AccountsUnavailable ('down')];
		$this->quiet (function () use ($id) {
			$this->service->handleNotify ($id);
			$this->service->reconcileForPlayer ($this->player);
		});
		$this->assertSame ([[9999, 1, $id], [9999, 1, $id]], $this->client->refunds);
		$this->assertSame ('refunded', $this->rowStatus ($id));
	}

	public function testRefundPendingWithoutChargeOnAccountsFails (): void
	{
		$id = $this->started ();
		$this->accept ($id);
		$this->useResult = false;
		$this->client->refundOutcomes = [new Neuron_GameServer_Credits_AccountsUnavailable ('down')];
		$this->quiet (function () use ($id) {
			$this->service->handleNotify ($id);
			unset ($this->client->requests[$id]);
			$this->assertSame (1, $this->service->reconcileForPlayer ($this->player));
		});
		$this->assertSame ('failed', $this->rowStatus ($id));
		$this->assertCount (1, $this->client->refunds);
	}

	public function testAccountsDownLeavesPending (): void
	{
		$id = $this->started ();
		$this->client->getRequestThrows = new Neuron_GameServer_Credits_AccountsUnavailable ('down');
		$this->assertSame ('pending', $this->service->handleNotify ($id));
		$this->assertSame ('pending', $this->rowStatus ($id));
	}

	public function testAccountsRejectedLeavesPending (): void
	{
		$id = $this->started ();
		$this->client->getRequestThrows = new Neuron_GameServer_Credits_AccountsRejected (401, 'bad');
		$prev = ini_set ('error_log', '/dev/null');
		$this->assertSame ('pending', $this->service->handleNotify ($id));
		ini_set ('error_log', $prev);
		$this->assertSame ('pending', $this->rowStatus ($id));
		$this->assertSame ([], $this->client->refunds);
	}

	public function testReconcileSettlesAcceptedAndExpiresOld (): void
	{
		$b = $this->started ();
		$this->accept ($b, 100, self::PROFILE, 'PENDING');
		$this->clock += 3000;
		$a = $this->started ();
		$this->accept ($a);
		$this->clock += 700; // b is now past expiry (3700s), a is not
		$this->assertSame (2, $this->service->reconcileForPlayer ($this->player));
		$this->assertSame ('granted', $this->rowStatus ($a));
		$this->assertSame ('expired', $this->rowStatus ($b));
		$this->assertSame (1, $this->useCalls);

		// Late confirmation of the expired purchase is refunded.
		$this->accept ($b);
		$this->assertSame ('refunded', $this->service->handleNotify ($b));
		$this->assertSame ([[self::PROFILE, 100, $b]], $this->client->refunds);
		$this->assertSame (1, $this->useCalls);
	}

	public function testRefundPurchaseAdmin (): void
	{
		$id = $this->started ();
		$this->accept ($id);
		$this->service->handleNotify ($id);
		$pid = Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ()->getByRequestId ($id)['id'];
		$this->assertTrue ($this->service->refundPurchase ($pid, 'support'));
		$this->assertFalse ($this->service->refundPurchase ($pid, 'support'));
		$this->assertCount (1, $this->client->refunds);
		$this->assertSame ('refunded', $this->rowStatus ($id));
	}

	/**
	 * Buy $n premium purchases, granted, then one that stays pending (never refunded).
	 * @return int[] request ids of the premium purchases, oldest first
	 */
	private function grantedPremium (int $n): array
	{
		$ids = [];
		for ($i = 0; $i < $n; $i ++)
		{
			$ids[] = $id = $this->started ();
			$this->accept ($id);
			$this->assertSame ('granted', $this->service->handleNotify ($id));
			$this->clock += 10;
		}
		$pending = $this->service->start ($this->player, ['action' => 'premium']);
		$this->assertSame ('redirect', $pending['status']);
		return $ids;
	}

	public function testAdminPremiumRefundsOnlyBlocksLeft (): void
	{
		foreach ([[5, 1], [5, 14], [5, 15], [5, 16], [5, 31], [2, 60], [3, 0]] as [$n, $days])
		{
			$this->clean ();
			$this->login (self::PROFILE);
			$this->client->refunds = [];
			$ids = $this->grantedPremium ($n);

			$endDate = $this->clock + $days * 86400;
			$blocks = Neuron_GameServer_Pages_Admin_Premium::blocksLeft ($endDate, $this->clock);
			$this->assertSame ((int) ceil ($days / 15), $blocks, "$days days");

			$result = $this->service->refundNewestPremium ($this->player, $blocks, 'premium account refund');
			$expected = min ($n, $blocks);
			$this->assertSame ($expected, $result['purchases'], "$n purchases, $days days");
			$this->assertSame ($expected * 100, $result['refunded']);
			$this->assertSame (0, $result['pending'] + $result['failed']);

			// Exactly the newest ones.
			$newest = array_slice (array_reverse ($ids), 0, $expected);
			$this->assertSame ($newest, array_map (function ($r) { return $r[2]; }, $this->client->refunds));
			foreach ($ids as $id)
			{
				$this->assertSame (in_array ($id, $newest, true) ? 'refunded' : 'granted', $this->rowStatus ($id));
			}
		}
	}

	public function testAdminPremiumRefundSkipsOtherActions (): void
	{
		$id = $this->started ();
		$this->accept ($id);
		$this->service->handleNotify ($id);
		$mapper = Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ();
		$other = $mapper->insert (self::PLID, self::PROFILE, 75, 'runes', ['action' => 'buyrunes'], date ('Y-m-d H:i:s', $this->clock), date ('Y-m-d H:i:s', $this->clock + 3600));
		$mapper->setRequestId ($other, 6002);
		$mapper->transition ($other, 'pending', 'granted', date ('Y-m-d H:i:s', $this->clock));

		$result = $this->service->refundNewestPremium ($this->player, 2, 'premium account refund');
		$this->assertSame (1, $result['purchases']);
		$this->assertSame ([[self::PROFILE, 100, $id]], $this->client->refunds);
		$this->assertSame ('granted', $this->rowStatus (6002));
	}

	public function testConcurrentNotifiesGrantOnce (): void
	{
		$id = $this->started ();
		$this->accept ($id);
		$inner = null;
		// Re-enter while the outer call still holds a stale 'pending' row.
		$this->client->onGetRequest = function () use ($id, &$inner) { $inner = $this->service->handleNotify ($id); };
		$this->assertSame ('ignored', $this->service->handleNotify ($id));
		$this->assertSame ('granted', $inner);
		$this->assertSame (1, $this->useCalls);
		$this->assertSame ([], $this->client->refunds);
		$this->assertSame ('granted', $this->rowStatus ($id));
	}

	public function testConcurrentLateNotifiesRefundOnce (): void
	{
		$id = $this->started ();
		$this->accept ($id);
		$this->clock += 3601;
		$inner = null;
		$this->client->onGetRequest = function () use ($id, &$inner) { $inner = $this->service->handleNotify ($id); };
		$this->assertSame ('ignored', $this->service->handleNotify ($id));
		$this->assertSame ('refunded', $inner);
		$this->assertCount (1, $this->client->refunds);
		$this->assertSame (0, $this->useCalls);
	}

	public function testConcurrentInapplicableRefundsOnce (): void
	{
		$id = $this->started ();
		$this->accept ($id);
		$this->useResult = false;
		$this->client->onGetRequest = function () use ($id) { $this->service->handleNotify ($id); };
		$this->service->handleNotify ($id);
		$this->assertCount (1, $this->client->refunds);
		$this->assertSame (1, $this->useCalls);
	}

	public function testReconcileForPlayerLeavesOtherPlayers (): void
	{
		$mine = $this->started ();
		$this->accept ($mine);
		$mapper = Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ();
		$other = $mapper->insert (self::OTHER_PLID, self::PROFILE, 100, 'x', ['action' => 'premium'], date ('Y-m-d H:i:s', $this->clock), date ('Y-m-d H:i:s', $this->clock + 3600));
		$mapper->setRequestId ($other, 6001);
		$this->accept (6001);
		$this->assertSame (1, $this->service->reconcileForPlayer ($this->player));
		$this->assertSame ('granted', $this->rowStatus ($mine));
		$this->assertSame ('pending', $this->rowStatus (6001));
	}

	public function testGlobalReconcile (): void
	{
		// reconcile() is global; never let it touch rows that are not this test's.
		$since = date ('Y-m-d H:i:s', $this->clock - Neuron_GameServer_Credits_PurchaseService::EXPIRED_RETRY_WINDOW);
		$foreign = array_filter (
			Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ()->getSettleable (1000, $since),
			function ($row) { return $row['plid'] !== self::PLID; }
		);
		if ($foreign)
		{
			$this->markTestSkipped ('Database has settleable credit purchases of other players; not running the global reconcile on them.');
		}
		$a = $this->started ();
		$this->accept ($a);
		$this->assertSame (1, $this->service->reconcile ());
		$this->assertSame ('granted', $this->rowStatus ($a));
	}

	public function testNotFoundPastExpiryFails (): void
	{
		$id = $this->started ();
		unset ($this->client->requests[$id]);
		$prev = ini_set ('error_log', '/dev/null');
		$this->assertSame ('ignored', $this->service->handleNotify ($id));
		$this->assertSame ('pending', $this->rowStatus ($id));
		$this->clock += 3601;
		$this->assertSame ('ignored', $this->service->handleNotify ($id));
		ini_set ('error_log', $prev);
		$this->assertSame ('failed', $this->rowStatus ($id));
	}

	public function testStuckRowsDoNotStarveReconcile (): void
	{
		$prev = ini_set ('error_log', '/dev/null');
		$stuck = [];
		// Unknown to Accounts: ignored until they expire.
		for ($i = 0; $i < 3; $i ++) { $stuck[] = $this->started (); $this->clock += 1; }
		$good = $this->started ();
		$this->accept ($good);
		// limit 3: the three older stuck rows fill the first batch, then rotate behind.
		$this->clock += 1;
		$this->service->reconcileForPlayer ($this->player, 3);
		$this->assertSame ('pending', $this->rowStatus ($good));
		$this->clock += 1;
		$this->assertSame (1, $this->service->reconcileForPlayer ($this->player, 3));
		ini_set ('error_log', $prev);
		$this->assertSame ('granted', $this->rowStatus ($good));
	}
}
