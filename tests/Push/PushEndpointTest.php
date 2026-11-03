<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/PushTestHelper.php';

class PushEndpointTest extends TestCase
{
	const FCM = 'https://fcm.googleapis.com/fcm/send/abc:def-123';

	protected function setUp(): void
	{
		PushTestHelper::enablePush();
		PushTestHelper::createTables();
	}

	protected function tearDown(): void
	{
		PushTestHelper::dropTables();
	}

	public static function allowedEndpoints(): array
	{
		return [
			'chrome' => ['https://fcm.googleapis.com/fcm/send/abc:def'],
			'firefox' => ['https://updates.push.services.mozilla.com/wpush/v2/gAAAA'],
			'safari' => ['https://web.push.apple.com/QGz_abc'],
			'edge' => ['https://wns2-par02p.notify.windows.com/w/?token=abc'],
		];
	}

	#[DataProvider('allowedEndpoints')]
	public function testKnownPushServicesAreAllowed(string $endpoint): void
	{
		$this->assertTrue(Neuron_GameServer_Push_Endpoint::isAllowedEndpoint($endpoint));
	}

	public static function rejectedEndpoints(): array
	{
		return [
			'plain http' => ['http://fcm.googleapis.com/fcm/send/abc'],
			'unknown host' => ['https://evil.example.com/fcm/send/abc'],
			'suffix trick' => ['https://fcm.googleapis.com.evil.example.com/x'],
			'look-alike' => ['https://evilfcm.googleapis.com.example/x'],
			'internal host' => ['https://localhost/x'],
			'credentials' => ['https://user:pass@fcm.googleapis.com/x'],
			'port' => ['https://fcm.googleapis.com:8443/x'],
			'whitespace' => ["https://fcm.googleapis.com/x\r\nHost: evil"],
			'too long' => ['https://fcm.googleapis.com/' . str_repeat('a', 700)],
			'not a url' => ['fcm.googleapis.com'],
		];
	}

	#[DataProvider('rejectedEndpoints')]
	public function testOtherEndpointsAreRejected(string $endpoint): void
	{
		$this->assertFalse(Neuron_GameServer_Push_Endpoint::isAllowedEndpoint($endpoint));
	}

	public function testSubscriptionNeedsValidKeys(): void
	{
		$keys = PushTestHelper::browserKeys();

		$this->assertNotNull(Neuron_GameServer_Push_Endpoint::parseSubscription(['endpoint' => self::FCM, 'keys' => $keys]));
		$this->assertNull(Neuron_GameServer_Push_Endpoint::parseSubscription(['endpoint' => self::FCM]));
		$this->assertNull(Neuron_GameServer_Push_Endpoint::parseSubscription(['endpoint' => self::FCM, 'keys' => ['p256dh' => "x' OR 1", 'auth' => $keys['auth']]]));
		$this->assertNull(Neuron_GameServer_Push_Endpoint::parseSubscription(['endpoint' => ['array'], 'keys' => $keys]));
		$this->assertNull(Neuron_GameServer_Push_Endpoint::parseSubscription('nope'));

		// Unsubscribing only needs the endpoint.
		$this->assertNotNull(Neuron_GameServer_Push_Endpoint::parseSubscription(['endpoint' => self::FCM], false));
	}

	public function testRequestChecks(): void
	{
		$player = new PushTestPlayer(5);
		$body = PushTestHelper::subscriptionJson(self::FCM);

		$this->assertSame(404, Neuron_GameServer_Push_Endpoint::handle('other', $player, 'POST', 'application/json', $body)[0]);
		$this->assertSame(405, Neuron_GameServer_Push_Endpoint::handle('subscribe', $player, 'GET', 'application/json', $body)[0]);
		$this->assertSame(415, Neuron_GameServer_Push_Endpoint::handle('subscribe', $player, 'POST', 'text/plain', $body)[0]);
		$this->assertSame(403, Neuron_GameServer_Push_Endpoint::handle('subscribe', $player, 'POST', 'application/json', $body, 'https://evil.example.com', 'game.example.com')[0]);
		$this->assertSame(401, Neuron_GameServer_Push_Endpoint::handle('subscribe', null, 'POST', 'application/json', $body)[0]);
		$this->assertSame(400, Neuron_GameServer_Push_Endpoint::handle('subscribe', $player, 'POST', 'application/json', '{"endpoint":"https://evil.example.com/"}')[0]);

		$this->assertFalse(Neuron_GameServer_Mappers_PushMapper::getInstance()->hasSubscriptions(5));
	}

	public function testSubscribeAndUnsubscribe(): void
	{
		$player = new PushTestPlayer(5);
		$mapper = Neuron_GameServer_Mappers_PushMapper::getInstance();

		[$status] = Neuron_GameServer_Push_Endpoint::handle(
			'subscribe', $player, 'POST', 'application/json; charset=utf-8',
			PushTestHelper::subscriptionJson(self::FCM), 'https://game.example.com', 'game.example.com:443', 'TestBrowser'
		);
		$this->assertSame(200, $status);
		$this->assertTrue($mapper->hasSubscriptions(5));

		// Another player cannot remove it.
		Neuron_GameServer_Push_Endpoint::handle('unsubscribe', new PushTestPlayer(6), 'POST', 'application/json', json_encode(['endpoint' => self::FCM]));
		$this->assertTrue($mapper->hasSubscriptions(5));

		[$status] = Neuron_GameServer_Push_Endpoint::handle('unsubscribe', $player, 'POST', 'application/json', json_encode(['endpoint' => self::FCM]));
		$this->assertSame(200, $status);
		$this->assertFalse($mapper->hasSubscriptions(5));
	}
}
