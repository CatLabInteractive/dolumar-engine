<?php

use PHPUnit\Framework\TestCase;

class ChatMapperTest extends TestCase
{
	protected function setUp(): void
	{
		$db = Neuron_DB_Database::getInstance();
		$db->query('DROP TEMPORARY TABLE IF EXISTS n_chat_messages');
		$db->query('
			CREATE TEMPORARY TABLE n_chat_messages (
				c_m_id INT AUTO_INCREMENT PRIMARY KEY,
				c_c_id INT NOT NULL,
				c_plid INT NOT NULL,
				c_date DATETIME NOT NULL,
				c_message TEXT NOT NULL
			)
		');

		for ($i = 1; $i <= 5; $i++) {
			$db->query("INSERT INTO n_chat_messages SET c_c_id = 1, c_plid = 1, c_date = NOW(), c_message = 'm{$i}'");
		}
	}

	protected function tearDown(): void
	{
		Neuron_DB_Database::getInstance()->query('DROP TEMPORARY TABLE IF EXISTS n_chat_messages');
	}

	private function ids(array $messages): array
	{
		return array_map(fn ($m) => (int) $m->getId(), $messages);
	}

	public function testOlderMessagesBeforeId(): void
	{
		$messages = Neuron_GameServer_Mappers_ChatMapper::getInstance()->getMessages(1, 10, null, 4);
		$this->assertSame([1, 2, 3], $this->ids($messages));
	}

	public function testInjectedBeforeIsTreatedAsNumber(): void
	{
		$messages = Neuron_GameServer_Mappers_ChatMapper::getInstance()->getMessages(1, 10, null, "4' OR '1'='1");
		$this->assertSame([1, 2, 3], $this->ids($messages));
	}

	public function testInjectedSinceIsTreatedAsNumber(): void
	{
		$messages = Neuron_GameServer_Mappers_ChatMapper::getInstance()->getMessages(1, 10, "3' OR '1'='1");
		$this->assertSame([4, 5], $this->ids($messages));
	}
}
