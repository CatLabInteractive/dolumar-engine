<?php

use PHPUnit\Framework\TestCase;

class CatLabAuthMapperTest extends TestCase
{
	private const PREFIX = 'https://accounts.example.test/users/';
	private const URL = 'https://accounts.example.test/users/990001';
	private const OTHER = 'https://legacy.example.test/id/990001';

	public static function setUpBeforeClass (): void
	{
		if (!defined ('OPENID_CONNECT_PROFILE_URL_PREFIX'))
		{
			define ('OPENID_CONNECT_PROFILE_URL_PREFIX', self::PREFIX);
		}
	}

	private function clean (): void
	{
		$db = Neuron_DB_Database::getInstance ();
		$db->query ("DELETE FROM n_auth_catlab WHERE openid_url LIKE '%990001'");
		$db->query ("DELETE FROM n_auth_openid WHERE openid_url LIKE '%990001'");
	}

	protected function setUp (): void
	{
		$this->clean ();
	}

	protected function tearDown (): void
	{
		$this->clean ();
	}

	private function player (int $id): Neuron_GameServer_Player
	{
		$player = $this->createMock (Neuron_GameServer_Player::class);
		$player->method ('getId')->willReturn ($id);
		return $player;
	}

	public function testSaveAndLoadForPlayer (): void
	{
		$db = Neuron_DB_Database::getInstance ();
		$db->query ("INSERT INTO n_auth_openid SET openid_url = '" . self::URL . "', user_id = 880001");

		$mapper = Neuron_GameServer_Mappers_CatLabAuthMapper::getInstance ();
		$mapper->save (self::URL, 12, 'tok', 'openid credits');

		$row = $mapper->getForPlayer ($this->player (880001));
		$this->assertSame ([
			'openid_url' => self::URL,
			'profile_id' => 12,
			'access_token' => 'tok',
			'scope' => 'openid credits',
		], $row);
		$this->assertNull ($mapper->getForPlayer ($this->player (880002)));
	}

	public function testSaveUpdates (): void
	{
		$mapper = Neuron_GameServer_Mappers_CatLabAuthMapper::getInstance ();
		$db = Neuron_DB_Database::getInstance ();
		$db->query ("INSERT INTO n_auth_openid SET openid_url = '" . self::URL . "', user_id = 880001");

		$mapper->save (self::URL, null, 'one', 'openid');
		$mapper->save (self::URL, 34, 'two', 'openid credits');

		$row = $mapper->getForPlayer ($this->player (880001));
		$this->assertSame (34, $row['profile_id']);
		$this->assertSame ('two', $row['access_token']);
		$this->assertSame ('openid credits', $row['scope']);
	}

	public function testIgnoresLegacyUrlsAndRowsWithoutToken (): void
	{
		$db = Neuron_DB_Database::getInstance ();
		$db->query ("INSERT INTO n_auth_openid SET openid_url = '" . self::URL . "', user_id = 880001");
		$db->query ("INSERT INTO n_auth_openid SET openid_url = '" . self::OTHER . "', user_id = 880001");

		$mapper = Neuron_GameServer_Mappers_CatLabAuthMapper::getInstance ();
		$mapper->save (self::OTHER, 5, 'legacy', 'openid');
		$this->assertNull ($mapper->getForPlayer ($this->player (880001)));

		$mapper->save (self::URL, 6, 'good', 'openid');
		$this->assertSame ('good', $mapper->getForPlayer ($this->player (880001))['access_token']);
	}
}
