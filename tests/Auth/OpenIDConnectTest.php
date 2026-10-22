<?php

use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\GenericResourceOwner;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\TestCase;

class OpenIDConnectTest extends TestCase
{
	private const PREFIX = 'https://accounts.example.test/users/';

	private array $insertedOpenIdUrls = [];

	public static function setUpBeforeClass (): void
	{
		if (!defined ('OPENID_CONNECT_PROFILE_URL_PREFIX'))
		{
			define ('OPENID_CONNECT_PROFILE_URL_PREFIX', self::PREFIX);
		}
	}

	protected function setUp (): void
	{
		$_SESSION = [];
	}

	protected function tearDown (): void
	{
		$_SESSION = [];

		if (count ($this->insertedOpenIdUrls) > 0)
		{
			$db = Neuron_Core_Database::__getInstance ();
			foreach ($this->insertedOpenIdUrls as $url)
			{
				$db->query ("DELETE FROM n_auth_openid WHERE openid_url = '" . $db->escape ($url) . "'");
			}
			$this->insertedOpenIdUrls = [];
		}
	}

	private function providerReturning (array $profile): GenericProvider
	{
		$provider = $this->createMock (GenericProvider::class);
		$provider->method ('getAccessToken')
			->willReturn (new AccessToken (['access_token' => 'token']));
		$provider->method ('getResourceOwner')
			->willReturn (new GenericResourceOwner ($profile, 'id'));
		return $provider;
	}

	public function test_state_mismatch_throws (): void
	{
		$provider = $this->createMock (GenericProvider::class);
		$provider->expects ($this->never ())->method ('getAccessToken');

		$_SESSION['oidc_state'] = 'expected-state';

		$oidc = new Neuron_Auth_OpenIDConnect ($provider);

		$this->expectException (RuntimeException::class);
		$oidc->getProfile (['code' => 'abc', 'state' => 'attacker-state']);
	}

	public function test_missing_session_state_throws (): void
	{
		$provider = $this->createMock (GenericProvider::class);
		$provider->expects ($this->never ())->method ('getAccessToken');

		$oidc = new Neuron_Auth_OpenIDConnect ($provider);

		$this->expectException (RuntimeException::class);
		$oidc->getProfile (['code' => 'abc', 'state' => '']);
	}

	public function test_missing_code_throws (): void
	{
		$provider = $this->createMock (GenericProvider::class);
		$provider->expects ($this->never ())->method ('getAccessToken');

		$_SESSION['oidc_state'] = 'expected-state';

		$oidc = new Neuron_Auth_OpenIDConnect ($provider);

		$this->expectException (RuntimeException::class);
		$oidc->getProfile (['state' => 'expected-state']);
	}

	public function test_state_is_single_use (): void
	{
		$_SESSION['oidc_state'] = 'expected-state';

		$oidc = new Neuron_Auth_OpenIDConnect ($this->providerReturning ([
			'id' => '42', 'email' => 'a@b.c', 'username' => 'p', 'verified_email' => true,
		]));

		$oidc->getProfile (['code' => 'abc', 'state' => 'expected-state']);

		$this->expectException (RuntimeException::class);
		$oidc->getProfile (['code' => 'abc', 'state' => 'expected-state']);
	}

	public function test_authorization_url_stores_state_and_requests_scopes (): void
	{
		$provider = new GenericProvider ([
			'clientId' => 'client',
			'clientSecret' => 'secret',
			'redirectUri' => 'https://game.example.test/oauth2/login/next',
			'urlAuthorize' => 'https://accounts.example.test/oauth2/authorize',
			'urlAccessToken' => 'https://accounts.example.test/oauth2/token',
			'urlResourceOwnerDetails' => 'https://accounts.example.test/oauth2/me',
		]);

		$oidc = new Neuron_Auth_OpenIDConnect ($provider);
		$url = $oidc->getAuthorizationUrl ();

		parse_str ((string) parse_url ($url, PHP_URL_QUERY), $query);

		$this->assertNotEmpty ($_SESSION['oidc_state']);
		$this->assertSame ($_SESSION['oidc_state'], $query['state']);
		$this->assertSame ('openid email profile', $query['scope']);
		$this->assertSame ('client', $query['client_id']);
		$this->assertSame ('code', $query['response_type']);
	}

	public function test_profile_maps_fields (): void
	{
		$_SESSION['oidc_state'] = 'expected-state';

		$oidc = new Neuron_Auth_OpenIDConnect ($this->providerReturning ([
			'id' => '42', 'email' => 'a@b.c', 'username' => 'p', 'verified_email' => true,
		]));

		$profile = $oidc->getProfile (['code' => 'abc', 'state' => 'expected-state']);

		$this->assertSame ([
			'id' => '42',
			'email' => 'a@b.c',
			'username' => 'p',
			'verified_email' => true,
		], $profile);
	}

	public function test_open_id_url_uses_prefix (): void
	{
		$this->assertSame (OPENID_CONNECT_PROFILE_URL_PREFIX . '42', Neuron_Auth_OpenIDConnect::openIdUrlFor ('42'));
	}

	public function test_existing_openid_url_maps_to_existing_user (): void
	{
		$db = Neuron_Core_Database::__getInstance ();
		$db->query ("
			CREATE TABLE IF NOT EXISTS `n_auth_openid` (
				`openid_url` varchar(255) NOT NULL,
				`user_id` int(11) NOT NULL,
				`notify_url` text,
				`profilebox_url` text,
				`userstats_url` text,
				PRIMARY KEY (`openid_url`),
				KEY `user_id` (`user_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8
		");

		$url = Neuron_Auth_OpenIDConnect::openIdUrlFor ('42');
		$db->query ("DELETE FROM n_auth_openid WHERE openid_url = '" . $db->escape ($url) . "'");

		$this->insertedOpenIdUrls[] = $url;
		$db->insert ('n_auth_openid', ['openid_url' => $url, 'user_id' => 7]);

		$this->assertSame (7, Neuron_Auth_OpenIDConnect::findUserId ($url));
		$this->assertNull (Neuron_Auth_OpenIDConnect::findUserId (Neuron_Auth_OpenIDConnect::openIdUrlFor ('does-not-exist')));
	}
}
