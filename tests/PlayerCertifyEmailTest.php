<?php

use PHPUnit\Framework\TestCase;

class PlayerCertifyEmailTest extends TestCase
{
	protected function setUp(): void
	{
		$db = Neuron_DB_Database::getInstance();
		$db->query('DROP TEMPORARY TABLE IF EXISTS n_players');
		$db->query('
			CREATE TEMPORARY TABLE n_players (
				plid INT PRIMARY KEY,
				email_cert TINYINT NOT NULL DEFAULT 0,
				email_cert_key VARCHAR(32) NULL,
				p_referer INT NOT NULL DEFAULT 0
			)
		');
		$db->query("INSERT INTO n_players SET plid = 7, email_cert = 0, email_cert_key = 'goodkey', p_referer = 0");
		$db->query("INSERT INTO n_players SET plid = 8, email_cert = 0, email_cert_key = '', p_referer = 0");
	}

	protected function tearDown(): void
	{
		Neuron_DB_Database::getInstance()->query('DROP TEMPORARY TABLE IF EXISTS n_players');
	}

	private function player(int $id, string $key): Neuron_GameServer_Player
	{
		$player = new Neuron_GameServer_Player($id);
		$player->setData(['plid' => $id, 'email_cert' => 0, 'email_cert_key' => $key, 'p_referer' => 0]);
		return $player;
	}

	private function storedCert(int $id): int
	{
		$r = Neuron_DB_Database::getInstance()->query("SELECT email_cert FROM n_players WHERE plid = {$id}");
		return (int) $r[0]['email_cert'];
	}

	public function testWrongKeyIsRejected(): void
	{
		$player = $this->player(7, 'goodkey');
		$this->assertFalse($player->certifyEmail('wrongkey'));
		$this->assertFalse($player->isEmailCertified());
		$this->assertSame(0, $this->storedCert(7));
	}

	public function testEmptyStoredKeyIsRejected(): void
	{
		$player = $this->player(8, '');
		$this->assertFalse($player->certifyEmail(''));
		$this->assertFalse($player->certifyEmail(false));
		$this->assertSame(0, $this->storedCert(8));
	}

	public function testCorrectKeyCertifies(): void
	{
		$player = $this->player(7, 'goodkey');
		$this->assertTrue($player->certifyEmail('goodkey'));
		$this->assertTrue($player->isEmailCertified());
		$this->assertSame(1, $this->storedCert(7));
	}
}
