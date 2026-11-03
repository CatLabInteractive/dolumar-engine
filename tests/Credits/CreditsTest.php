<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/PurchaseServiceTest.php';

class CreditsTest extends TestCase
{
	private const PREFIX = 'https://accounts.example.test/users/';
	private const PLID = 770002;

	public static function setUpBeforeClass (): void
	{
		foreach (['OPENID_CONNECT_PROFILE_URL_PREFIX' => self::PREFIX, 'API_FULL_URL' => 'https://api.example.test/', 'ABSOLUTE_URL' => 'https://game.example.test/', 'PREMIUM_COST_CREDITS' => 100] as $k => $v)
		{
			if (!defined ($k)) { define ($k, $v); }
		}
	}

	protected function tearDown (): void
	{
		Neuron_GameServer_Credits::setClientOverride (null);
		$db = Neuron_DB_Database::getInstance ();
		$db->query ("DELETE FROM n_credit_purchases WHERE plid = " . self::PLID);
		$db->query ("DELETE FROM n_auth_catlab WHERE openid_url LIKE '%770002'");
		$db->query ("DELETE FROM n_auth_openid WHERE openid_url LIKE '%770002'");
	}

	private function player (): Neuron_GameServer_Player
	{
		$player = $this->getMockBuilder (Neuron_GameServer_Player::class)->disableOriginalConstructor ()
			->onlyMethods (['getId'])->getMock ();
		$player->method ('getId')->willReturn (self::PLID);
		return $player;
	}

	private function handler (FakeCreditsClient $client): Neuron_GameServer_Credits_ApiHandler
	{
		$service = new Neuron_GameServer_Credits_PurchaseService ($client, null, function () { return null; });
		return new Neuron_GameServer_Credits_ApiHandler ($service, 'https://accounts.example.test/');
	}

	public function testUseUrlIsLocalStartLink (): void
	{
		Neuron_GameServer_Credits::setClientOverride (new FakeCreditsClient ());
		$credits = new Neuron_GameServer_Credits ($this->player ());
		$this->assertSame
		(
			API_FULL_URL . 'creditstart/?action=buyrunes&village=3',
			$credits->getUseUrl (1, ['action' => 'buyrunes', 'village' => 3])
		);
	}

	public function testUseUrlNullWhenOff (): void
	{
		Neuron_GameServer_Credits::setClientOverride (false);
		$credits = new Neuron_GameServer_Credits ($this->player ());
		$this->assertNull ($credits->getUseUrl (1, ['action' => 'buyrunes']));
		$this->assertNull ($credits->getBuyUrl ());
		$this->assertNull ($credits->getCredits ());
	}

	public function testMissingTokenAsksForLogin (): void
	{
		$client = new FakeCreditsClient ();
		$r = $this->handler ($client)->start ($this->player (), ['action' => 'premium']);
		$this->assertSame (302, $r['code']);
		$this->assertSame (ABSOLUTE_URL . 'oauth2/login', $r['location']);
		$this->assertCount (0, $client->created);
	}

	public function testStartRedirectsToAccounts (): void
	{
		$db = Neuron_DB_Database::getInstance ();
		$url = self::PREFIX . '770002';
		$db->query ("INSERT INTO n_auth_openid SET openid_url = '" . $url . "', user_id = " . self::PLID);
		Neuron_GameServer_Mappers_CatLabAuthMapper::getInstance ()->save ($url, 99, 'tok', 'openid credits');

		$player = $this->getMockBuilder (Neuron_GameServer_Player::class)->disableOriginalConstructor ()
			->onlyMethods (['getId'])->getMock ();
		$player->method ('getId')->willReturn (self::PLID);

		$r = $this->handler (new FakeCreditsClient ())->start ($player, ['action' => 'premium', 'module' => 'api/creditstart', 'session_id' => 'x', 'rand' => '1']);
		$this->assertSame (302, $r['code']);
		$this->assertStringStartsWith ('https://accounts.example.test/', $r['location']);
		$row = Neuron_GameServer_Mappers_CreditPurchaseMapper::getInstance ()->getForPlayer (self::PLID)[0];
		$this->assertSame (['action' => 'premium'], $row['action_data']);
	}

	public function testForeignRedirectIsRefused (): void
	{
		$client = new class extends FakeCreditsClient {
			public function createRequest (string $accessToken, int $profileId, int $credits, string $reason, string $notifyUrl, string $returnUrl, array $extra): array
			{
				return ['id' => 1, 'confirmUrl' => 'https://evil.example.test/x'];
			}
		};
		$db = Neuron_DB_Database::getInstance ();
		$url = self::PREFIX . '770002';
		$db->query ("INSERT INTO n_auth_openid SET openid_url = '" . $url . "', user_id = " . self::PLID);
		Neuron_GameServer_Mappers_CatLabAuthMapper::getInstance ()->save ($url, 99, 'tok', 'openid credits');

		$r = $this->handler ($client)->start ($this->player (), ['action' => 'premium']);
		$this->assertSame (200, $r['code']);
		$this->assertNull ($r['location']);
	}

	public function testStartWithoutPlayerAsksForLogin (): void
	{
		$r = $this->handler (new FakeCreditsClient ())->start (null, ['action' => 'premium']);
		$this->assertSame (302, $r['code']);
		$this->assertSame (ABSOLUTE_URL . 'oauth2/login', $r['location']);
	}

	public function testNotifyEndpointAlways200 (): void
	{
		$r = $this->handler (new FakeCreditsClient ())->notify ('POST', ['credit_request_id' => '99999999']);
		$this->assertSame (200, $r['code']);
		$this->assertSame ('ignored', $r['body']);
	}

	public function testNotifyRejectsNonPost (): void
	{
		$r = $this->handler (new FakeCreditsClient ())->notify ('GET', []);
		$this->assertSame (405, $r['code']);
	}
}
