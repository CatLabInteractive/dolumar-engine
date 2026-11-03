<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/PushTestHelper.php';

class PushMapperTest extends TestCase
{
	protected function setUp(): void
	{
		PushTestHelper::createTables();
	}

	protected function tearDown(): void
	{
		PushTestHelper::dropTables();
	}

	public function testEndpointMovesToTheLatestPlayer(): void
	{
		$mapper = Neuron_GameServer_Mappers_PushMapper::getInstance();
		$endpoint = 'https://fcm.googleapis.com/fcm/send/shared';

		$mapper->addSubscription(1, $endpoint, 'key1', 'auth1', 'Browser');
		$mapper->addSubscription(2, $endpoint, 'key2', 'auth2', 'Browser');

		$this->assertFalse($mapper->hasSubscriptions(1));
		$rows = $mapper->getSubscriptions([1, 2]);
		$this->assertCount(1, $rows);
		$this->assertSame(2, $rows[0]['plid']);
		$this->assertSame('key2', $rows[0]['p256dh']);
	}

	public function testRemoveEndpoint(): void
	{
		$mapper = Neuron_GameServer_Mappers_PushMapper::getInstance();
		$mapper->addSubscription(1, 'https://fcm.googleapis.com/a', 'k', 'a');
		$mapper->addSubscription(1, 'https://fcm.googleapis.com/b', 'k', 'a');

		$mapper->removeEndpoint('https://fcm.googleapis.com/a');

		$rows = $mapper->getSubscriptions([1]);
		$this->assertCount(1, $rows);
		$this->assertSame('https://fcm.googleapis.com/b', $rows[0]['endpoint']);
		$this->assertSame([], $mapper->getSubscriptions([]));
	}

	public function testTakeQueueReturnsOldestFirstAndEmptiesIt(): void
	{
		$mapper = Neuron_GameServer_Mappers_PushMapper::getInstance();
		$mapper->enqueue(1, '{"n":1}');
		$mapper->enqueue(2, '{"n":2}');
		$mapper->enqueue(3, '{"n":3}');

		$first = $mapper->takeQueue(2);
		$this->assertSame([1, 2], array_column($first, 'plid'));
		$this->assertSame('{"n":1}', $first[0]['payload']);
		$this->assertEqualsWithDelta(time(), $first[0]['created'], 5);

		$rest = $mapper->takeQueue(10);
		$this->assertSame([3], array_column($rest, 'plid'));
		$this->assertSame([], $mapper->takeQueue(10));
	}
}
