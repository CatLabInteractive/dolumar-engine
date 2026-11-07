<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/PurchaseServiceTest.php';

class FakeStoreClient extends FakeCreditsClient
{
	public $packs = [['sku' => 'credits.100', 'credits' => 100], ['sku' => 'credits.500', 'credits' => 500]];
	public $packsThrows = null;
	public $registered = [];
	public $registerThrows = null;
	public $registerAnswer = ['status' => 200, 'body' => ['status' => 'registered', 'credits' => 500, 'balance' => 500, 'transaction_id' => 77]];

	public function getStorePacks (): array
	{
		if ($this->packsThrows) { throw $this->packsThrows; }
		return $this->packs;
	}

	public function registerStorePurchase (int $profileId, array $purchase): array
	{
		if ($this->registerThrows) { throw $this->registerThrows; }
		$this->registered[] = [$profileId, $purchase];
		return $this->registerAnswer;
	}
}

class StoreEndpointTest extends TestCase
{
	private const NOW = 1800000000;
	private const PLID = 770003;
	private const ANDROID = 'Mozilla/5.0 (Linux; Android 14) DolumarApp/1.0 (android)';
	private const IOS = 'Mozilla/5.0 (iPhone) DolumarApp/1.0 (ios)';

	private FakeStoreClient $client;
	private Neuron_GameServer_Credits_StoreTicket $tickets;
	private $players = [];
	private $auth = [];

	public static function setUpBeforeClass (): void
	{
		foreach (['API_FULL_URL' => 'https://game.example.test/api/', 'ABSOLUTE_URL' => 'https://game.example.test/'] as $k => $v)
		{
			if (!defined ($k)) { define ($k, $v); }
		}
	}

	protected function setUp (): void
	{
		$this->client = new FakeStoreClient ();
		$this->tickets = new Neuron_GameServer_Credits_StoreTicket ('secret');
		$this->players = [self::PLID => $this->player (self::PLID)];
		$this->auth = [self::PLID => ['profile_id' => 4242, 'access_token' => 'tok', 'openid_url' => 'x', 'scope' => 'credits']];
	}

	private function player (int $plid): Neuron_GameServer_Player
	{
		$player = $this->getMockBuilder (Neuron_GameServer_Player::class)->disableOriginalConstructor ()
			->onlyMethods (['getId'])->getMock ();
		$player->method ('getId')->willReturn ($plid);
		return $player;
	}

	private function endpoint (): Neuron_GameServer_Credits_StoreEndpoint
	{
		return new Neuron_GameServer_Credits_StoreEndpoint
		(
			$this->client,
			$this->tickets,
			function (int $plid) { return $this->players[$plid] ?? null; },
			function (Neuron_GameServer_Player $player) { return $this->auth[$player->getId ()] ?? null; },
			function () { return self::NOW; }
		);
	}

	private function ticket ($player = null, string $ua = self::ANDROID, ?string $origin = 'https://game.example.test'): array
	{
		return $this->endpoint ()->handle ('ticket', $player === null ? $this->players[self::PLID] : $player, 'POST', '', '', $origin, 'game.example.test', $ua);
	}

	private function purchase (array $overrides = []): array
	{
		return array_merge ([
			'ticket' => $this->tickets->issue (self::PLID, self::NOW),
			'source' => 'google',
			'sku' => 'credits.500',
			'store_reference' => 'tok.ABC_def-123',
			'proof' => ['purchaseToken' => 'tok.ABC_def-123', 'productId' => 'credits.500'],
		], $overrides);
	}

	private function register ($body, string $origin = 'https://localhost', string $method = 'POST', string $contentType = 'application/json'): array
	{
		return $this->endpoint ()->handle ('register', null, $method, $contentType, is_string ($body) ? $body : json_encode ($body), $origin, 'game.example.test', self::ANDROID);
	}

	public function testATicketSendsTheAppToItsStorePage (): void
	{
		$r = $this->ticket ();
		$this->assertSame (200, $r['code']);
		$this->assertSame (self::PLID, $this->tickets->read ($r['body']['ticket'], self::NOW));
		$this->assertSame (900, $r['body']['expires_in']);
		$this->assertSame
		(
			'https://localhost/store.html?api=' . rawurlencode ('https://game.example.test/api/store/') . '&ticket=' . rawurlencode ($r['body']['ticket']),
			$r['body']['url']
		);
		$this->assertSame ('no-store', $r['headers']['Cache-Control']);

		$this->assertStringStartsWith ('app://localhost/store.html?', $this->ticket (null, self::IOS)['body']['url']);
		// A browser gets a ticket too (nothing secret in it), but the url only works in the app.
		$this->assertStringStartsWith ('https://localhost/store.html?', $this->ticket (null, 'Mozilla/5.0')['body']['url']);
	}

	public function testATicketNeedsALoggedInPlayerOnTheGamesOwnOrigin (): void
	{
		$this->assertSame (401, $this->ticket (false)['code']);
		$this->assertSame ('not_logged_in', $this->ticket (false)['body']['error']);
		$this->assertSame (403, $this->ticket (null, self::ANDROID, 'https://localhost')['code']);
		$this->assertSame (200, $this->ticket (null, self::ANDROID, null)['code']);
		$this->assertSame (405, $this->endpoint ()->handle ('ticket', $this->players[self::PLID], 'GET', '', '', null, 'game.example.test')['code']);
		$this->assertSame (404, $this->endpoint ()->handle ('other', $this->players[self::PLID], 'GET', '', '', null, 'game.example.test')['code']);
	}

	public function testThePacksComeFromAccountsWithCorsForTheApp (): void
	{
		$r = $this->endpoint ()->handle ('packs', null, 'GET', '', '', 'app://localhost', 'game.example.test');
		$this->assertSame (200, $r['code']);
		$this->assertSame ($this->client->packs, $r['body']['packs']);
		$this->assertSame ('app://localhost', $r['headers']['Access-Control-Allow-Origin']);
		$this->assertSame ('GET, OPTIONS', $r['headers']['Access-Control-Allow-Methods']);
		$this->assertSame ('Origin', $r['headers']['Vary']);

		$preflight = $this->endpoint ()->handle ('packs', null, 'OPTIONS', '', '', 'https://localhost', 'game.example.test');
		$this->assertSame (204, $preflight['code']);
		$this->assertSame ('https://localhost', $preflight['headers']['Access-Control-Allow-Origin']);

		$foreign = $this->endpoint ()->handle ('packs', null, 'GET', '', '', 'https://evil.example.test', 'game.example.test');
		$this->assertSame (403, $foreign['code']);
		$this->assertArrayNotHasKey ('Access-Control-Allow-Origin', $foreign['headers']);

		$this->client->packsThrows = new Neuron_GameServer_Credits_AccountsUnavailable ('down');
		$this->assertSame (503, $this->endpoint ()->handle ('packs', null, 'GET', '', '', 'https://localhost', 'game.example.test')['code']);
	}

	public function testARegisteredPurchaseIsPassedToAccountsForThePlayersProfile (): void
	{
		$r = $this->register ($this->purchase ());

		$this->assertSame (200, $r['code']);
		$this->assertSame ('registered', $r['body']['status']);
		$this->assertSame (500, $r['body']['balance']);
		$this->assertSame ('https://localhost', $r['headers']['Access-Control-Allow-Origin']);
		$this->assertSame ('POST, OPTIONS', $r['headers']['Access-Control-Allow-Methods']);

		$this->assertCount (1, $this->client->registered);
		list ($profileId, $purchase) = $this->client->registered[0];
		$this->assertSame (4242, $profileId);
		// The ticket never reaches Accounts; the rest does, as is.
		$this->assertSame (['source', 'sku', 'store_reference', 'proof'], array_keys ($purchase));
		$this->assertSame ('tok.ABC_def-123', $purchase['store_reference']);
		$this->assertSame (['purchaseToken' => 'tok.ABC_def-123', 'productId' => 'credits.500'], $purchase['proof']);
	}

	public function testAccountsRefusalsArePassedBackWithTheirReason (): void
	{
		$this->client->registerAnswer = ['status' => 403, 'body' => ['error' => 'rejected', 'reason' => 'invalid_purchase', 'secret' => 'no']];
		$r = $this->register ($this->purchase ());
		$this->assertSame (403, $r['code']);
		$this->assertSame (['error' => 'rejected', 'reason' => 'invalid_purchase'], $r['body']);

		$this->client->registerAnswer = ['status' => 503, 'body' => ['error' => 'unavailable', 'reason' => 'credits_not_verified']];
		$r = $this->register ($this->purchase ());
		$this->assertSame (503, $r['code']);
		$this->assertSame (['error' => 'unavailable', 'reason' => 'credits_not_verified'], $r['body']);

		$this->client->registerAnswer = ['status' => 500, 'body' => []];
		$r = $this->register ($this->purchase ());
		$this->assertSame (503, $r['code']);
		$this->assertSame (['error' => 'unavailable', 'reason' => 'accounts'], $r['body']);

		$this->client->registerAnswer = ['status' => 200, 'body' => ['unexpected' => true]];
		$this->assertSame (503, $this->register ($this->purchase ())['code']);

		$this->client->registerThrows = new Neuron_GameServer_Credits_AccountsUnavailable ('down');
		$this->assertSame (503, $this->register ($this->purchase ())['code']);
	}

	public function testABadOrExpiredTicketRegistersNothing (): void
	{
		$cases = [
			'forged' => (new Neuron_GameServer_Credits_StoreTicket ('other'))->issue (self::PLID, self::NOW),
			'expired' => $this->tickets->issue (self::PLID, self::NOW - 1000),
			'unknown player' => $this->tickets->issue (self::PLID + 1, self::NOW),
			'not a string' => 12,
			'missing' => null,
		];
		foreach ($cases as $name => $ticket)
		{
			$body = $this->purchase ();
			if ($ticket === null) { unset ($body['ticket']); } else { $body['ticket'] = $ticket; }
			$r = $this->register ($body);
			$this->assertSame (401, $r['code'], $name);
			$this->assertSame ('invalid_ticket', $r['body']['error'], $name);
		}

		$this->auth = [];
		$r = $this->register ($this->purchase ());
		$this->assertSame (401, $r['code']);
		$this->assertSame ('no_profile', $r['body']['error']);

		$this->assertCount (0, $this->client->registered);
	}

	public function testABodyThatDoesNotFitIsRefusedBeforeTheTicketIsRead (): void
	{
		$bad = [
			'source' => $this->purchase (['source' => 'steam']),
			'sku' => $this->purchase (['sku' => 'gold.500']),
			'reference' => $this->purchase (['store_reference' => 'a b']),
			'long reference' => $this->purchase (['store_reference' => str_repeat ('a', 256)]),
			'proof' => $this->purchase (['proof' => 'abc']),
			'empty proof' => $this->purchase (['proof' => []]),
		];
		foreach ($bad as $name => $body)
		{
			$r = $this->register ($body);
			$this->assertSame (400, $r['code'], $name);
			$this->assertSame ('invalid_request', $r['body']['error'], $name);
		}

		$this->assertSame (400, $this->register ('not json')['code']);
		$this->assertSame (415, $this->register ($this->purchase (), 'https://localhost', 'POST', 'text/plain')['code']);
		$this->assertSame (405, $this->register ($this->purchase (), 'https://localhost', 'GET')['code']);
		$this->assertSame (413, $this->register (json_encode ($this->purchase (['proof' => ['x' => str_repeat ('a', 40000)]])))['code']);
		$this->assertSame (403, $this->register ($this->purchase (), 'https://evil.example.test')['code']);
		$this->assertCount (0, $this->client->registered);
	}

	public function testAnApplePurchaseIsAcceptedToo (): void
	{
		$r = $this->register ($this->purchase (['source' => 'apple', 'store_reference' => '2000000123456789', 'proof' => ['transactionId' => '2000000123456789']]), 'app://localhost');
		$this->assertSame (200, $r['code']);
		$this->assertSame ('apple', $this->client->registered[0][1]['source']);
	}
}
