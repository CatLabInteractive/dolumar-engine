<?php

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Minishlink\WebPush\WebPush;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

require_once __DIR__ . '/PushTestHelper.php';

/*
	Stands in for the push services: answers per endpoint, records what was sent.
*/
class FakePushServiceClient implements ClientInterface
{
	public array $statusByEndpoint = [];
	public array $sent = [];

	public function sendRequest(RequestInterface $request): ResponseInterface
	{
		$endpoint = (string) $request->getUri();
		$this->sent[] = $endpoint;
		return new Response($this->statusByEndpoint[$endpoint] ?? 201);
	}
}

class PushSenderTest extends TestCase
{
	private FakePushServiceClient $client;

	protected function setUp(): void
	{
		PushTestHelper::enablePush();
		PushTestHelper::createTables();
		$this->client = new FakePushServiceClient();
	}

	protected function tearDown(): void
	{
		PushTestHelper::dropTables();
	}

	private function sender(): Neuron_GameServer_Push_Sender
	{
		$factory = new HttpFactory();
		$webPush = new WebPush(Neuron_GameServer_Push_Config::getAuth(), [], $this->client, $factory, $factory);
		return new Neuron_GameServer_Push_Sender($webPush);
	}

	private function subscribe(int $playerId, string $endpoint): void
	{
		$keys = PushTestHelper::browserKeys();
		Neuron_GameServer_Mappers_PushMapper::getInstance()->addSubscription($playerId, $endpoint, $keys['p256dh'], $keys['auth']);
	}

	public function testPayload(): void
	{
		$json = Neuron_GameServer_Push_Sender::buildPayload("Dolumar", "Your village\n  is under attack", 'https://game.example.com/', 'battle-defending', 'https://game.example.com/icon.png');
		$this->assertSame(
			['title' => 'Dolumar', 'body' => 'Your village is under attack', 'url' => 'https://game.example.com/', 'tag' => 'battle-defending', 'icon' => 'https://game.example.com/icon.png'],
			json_decode($json, true)
		);

		$long = json_decode(Neuron_GameServer_Push_Sender::buildPayload('t', str_repeat('é', 500), 'u'), true);
		$this->assertSame(Neuron_GameServer_Push_Sender::MAX_BODY_LENGTH, mb_strlen($long['body']));
		$this->assertArrayNotHasKey('tag', $long);
	}

	public function testDeliversToEverySubscribedBrowser(): void
	{
		$this->subscribe(1, 'https://fcm.googleapis.com/fcm/send/phone');
		$this->subscribe(1, 'https://updates.push.services.mozilla.com/wpush/v2/laptop');
		$this->subscribe(2, 'https://fcm.googleapis.com/fcm/send/other');

		$mapper = Neuron_GameServer_Mappers_PushMapper::getInstance();
		$mapper->enqueue(1, Neuron_GameServer_Push_Sender::buildPayload('t', 'b', 'u'));

		$stats = $this->sender()->flushQueue();

		$this->assertSame(1, $stats['queued']);
		$this->assertSame(2, $stats['sent']);
		$this->assertEqualsCanonicalizing(
			['https://fcm.googleapis.com/fcm/send/phone', 'https://updates.push.services.mozilla.com/wpush/v2/laptop'],
			$this->client->sent
		);
		$this->assertSame([], $mapper->takeQueue(10));

		$success = Neuron_DB_Database::getInstance()->query('SELECT COUNT(*) AS c FROM n_push_subscriptions WHERE ps_lastsuccess IS NOT NULL');
		$this->assertSame('2', (string) $success[0]['c']);
	}

	public function testExpiredSubscriptionsAreRemoved(): void
	{
		$this->subscribe(1, 'https://fcm.googleapis.com/fcm/send/gone');
		$this->subscribe(1, 'https://fcm.googleapis.com/fcm/send/broken');
		$this->client->statusByEndpoint['https://fcm.googleapis.com/fcm/send/gone'] = 410;
		$this->client->statusByEndpoint['https://fcm.googleapis.com/fcm/send/broken'] = 500;

		Neuron_GameServer_Mappers_PushMapper::getInstance()->enqueue(1, Neuron_GameServer_Push_Sender::buildPayload('t', 'b', 'u'));

		$stats = $this->sender()->flushQueue();

		$this->assertSame(1, $stats['expired']);
		$this->assertSame(1, $stats['failed']);
		$rows = Neuron_GameServer_Mappers_PushMapper::getInstance()->getSubscriptions([1]);
		$this->assertSame(['https://fcm.googleapis.com/fcm/send/broken'], array_column($rows, 'endpoint'));
	}

	public function testStaleAndUnsubscribedMessagesAreDropped(): void
	{
		$this->subscribe(1, 'https://fcm.googleapis.com/fcm/send/phone');

		$mapper = Neuron_GameServer_Mappers_PushMapper::getInstance();
		$mapper->enqueue(1, Neuron_GameServer_Push_Sender::buildPayload('t', 'old', 'u'));
		$mapper->enqueue(3, Neuron_GameServer_Push_Sender::buildPayload('t', 'nobody', 'u'));

		$stats = $this->sender()->flushQueue(500, time() + Neuron_GameServer_Push_Sender::MAX_AGE + 60);

		$this->assertSame(2, $stats['queued']);
		$this->assertSame(2, $stats['stale']);
		$this->assertSame([], $this->client->sent);
		$this->assertSame([], $mapper->takeQueue(10));
	}
}
