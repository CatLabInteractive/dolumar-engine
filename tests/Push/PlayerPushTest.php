<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/PushTestHelper.php';

class PlayerPushTest extends TestCase
{
	protected function setUp(): void
	{
		PushTestHelper::enablePush();
		PushTestHelper::createTables();
	}

	protected function tearDown(): void
	{
		PushTestHelper::dropTables();
	}

	public function testDefaultMutedCategoriesApplyUntilThePlayerSaves(): void
	{
		$player = new PushTestPlayer(1);

		$this->assertTrue($player->isPushCategoryEnabled('battle'));
		$this->assertFalse($player->isPushCategoryEnabled('clan'));

		$player->setPushMuted([]);
		$this->assertTrue($player->isPushCategoryEnabled('clan'));

		$player->setPushMuted(['battle', 'unknown']);
		$this->assertSame('battle', $player->prefs['pushMuted']);
		$this->assertFalse($player->isPushCategoryEnabled('battle'));
	}

	public function testQueuesOnlyForSubscribedPlayersAndEnabledCategories(): void
	{
		$player = new PushTestPlayer(1);
		$mapper = Neuron_GameServer_Mappers_PushMapper::getInstance();

		$this->assertFalse($player->queuePushNotification('No browser yet', 'battle'));

		$mapper->addSubscription(1, 'https://fcm.googleapis.com/fcm/send/x', 'k', 'a');

		$this->assertFalse($player->queuePushNotification('Muted by default', 'clan'));
		$this->assertTrue($player->queuePushNotification('Your village is under attack', 'battle', 'battle-defending'));

		$queue = $mapper->takeQueue(10);
		$this->assertCount(1, $queue);
		$this->assertSame(1, $queue[0]['plid']);

		$payload = json_decode($queue[0]['payload'], true);
		$this->assertSame('Dolumar Test', $payload['title']);
		$this->assertSame('Your village is under attack', $payload['body']);
		$this->assertSame(ABSOLUTE_URL, $payload['url']);
		$this->assertSame('battle-defending', $payload['tag']);
	}
}
