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
		$this->refunds[] = [$profileId, $credits, $creditRequestId];
		return $this->refundResult;
	}
}

class PurchaseServiceTest extends TestCase
{
	private const PREFIX = 'https://accounts.example.test/users/';
	private const PLID = 770001;
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
		$db->query ("DELETE FROM n_credit_purchases WHERE plid = " . self::PLID);
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
		$this->assertSame ('unavailable', $this->service->start ($this->service_player (), ['action' => 'premium'])['status']);
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

	public function testNotifyWrongAmountIgnored (): void
	{
		$id = $this->started ();
		$this->accept ($id, 1);
		$this->assertSame ('ignored', $this->service->handleNotify ($id));
		$this->assertSame ('pending', $this->rowStatus ($id));
		$this->assertSame (0, $this->useCalls);
		$this->assertSame ([], $this->client->refunds);
	}

	public function testNotifyWrongProfileIgnored (): void
	{
		$id = $this->started ();
		$this->accept ($id, 100, 9999);
		$this->assertSame ('ignored', $this->service->handleNotify ($id));
		$this->assertSame ('pending', $this->rowStatus ($id));
		$this->assertSame (0, $this->useCalls);
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
		$this->assertSame ('refunded', $this->service->handleNotify ($id));
		ini_set ('error_log', $prev);
		$this->assertSame ('failed', $this->rowStatus ($id));
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
		$this->assertSame (2, $this->service->reconcile ());
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

	public function testPastExpiryMismatchBecomesFailedAndLeavesQueue (): void
	{
		$id = $this->started ();
		$this->accept ($id, 1);
		$this->assertSame (0, $this->service->reconcile ());
		$this->assertSame ('pending', $this->rowStatus ($id));
		$this->clock += 3601;
		$prev = ini_set ('error_log', '/dev/null');
		$this->assertSame (1, $this->service->reconcile ());
		ini_set ('error_log', $prev);
		$this->assertSame ('failed', $this->rowStatus ($id));
		$this->assertSame (0, $this->service->reconcile ());
		$this->assertSame ([], $this->client->refunds);
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
		for ($i = 0; $i < 3; $i ++) { $stuck[] = $this->started (); $this->accept ($stuck[$i], 1); $this->clock += 1; }
		$good = $this->started ();
		$this->accept ($good);
		// limit 3: the three older stuck rows fill the first batch, then rotate behind.
		$this->clock += 1;
		$this->service->reconcile (3);
		$this->assertSame ('pending', $this->rowStatus ($good));
		$this->clock += 1;
		$this->assertSame (1, $this->service->reconcile (3));
		ini_set ('error_log', $prev);
		$this->assertSame ('granted', $this->rowStatus ($good));
	}
}
