<?php

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GenericProvider;

/**
 * OpenID Connect login against the CatLab accounts server.
 *
 * Wraps a league/oauth2-client provider, keeps the anti-CSRF state in the
 * session between the redirect and the callback, and maps the profile
 * endpoint to the fields Dolumar uses.
 */
class Neuron_Auth_OpenIDConnect
{
	public const SCOPE = 'openid email profile';
	private const SESSION_STATE_KEY = 'oidc_state';

	public function __construct (private AbstractProvider $provider)
	{
	}

	public static function fromConfig (string $redirectUri): self
	{
		return new self (new GenericProvider ([
			'clientId' => OPENID_CONNECT_CLIENT_ID,
			'clientSecret' => OPENID_CONNECT_SECRET,
			'redirectUri' => $redirectUri,
			'urlAuthorize' => OPENID_CONNECT_AUTHORIZE_URL,
			'urlAccessToken' => OPENID_CONNECT_TOKEN_URL,
			'urlResourceOwnerDetails' => OPENID_CONNECT_PROFILE_URL,
			'responseResourceOwnerId' => 'id',
		]));
	}

	public static function openIdUrlFor (string $id): string
	{
		return OPENID_CONNECT_PROFILE_URL_PREFIX . $id;
	}

	/**
	 * Returns the user id linked to this openid url, 0 for a row that is
	 * still waiting for registration, or null if no row exists.
	 */
	public static function findUserId (string $openIdUrl): ?int
	{
		$db = Neuron_Core_Database::__getInstance ();

		$acc = $db->select
		(
			'n_auth_openid',
			array ('user_id'),
			"openid_url = '".$db->escape ($openIdUrl)."'"
		);

		if (count ($acc) == 0)
		{
			return null;
		}

		return (int) $acc[0]['user_id'];
	}

	public function getAuthorizationUrl (): string
	{
		$url = $this->provider->getAuthorizationUrl (['scope' => self::SCOPE]);
		$_SESSION[self::SESSION_STATE_KEY] = $this->provider->getState ();

		return $url;
	}

	/**
	 * Exchanges the authorization code from the callback query for the user's profile.
	 *
	 * @return array{id:string,email:string,username:string,verified_email:bool}
	 * @throws RuntimeException on a state mismatch, a missing code or a profile without id.
	 */
	public function getProfile (array $query): array
	{
		$expectedState = $_SESSION[self::SESSION_STATE_KEY] ?? null;
		// A state is only valid for one callback.
		unset ($_SESSION[self::SESSION_STATE_KEY]);

		$state = $query['state'] ?? null;
		if (!is_string ($expectedState) || $expectedState === ''
			|| !is_string ($state) || !hash_equals ($expectedState, $state))
		{
			throw new RuntimeException ('Invalid OpenID Connect state.');
		}

		$code = $query['code'] ?? null;
		if (!is_string ($code) || $code === '')
		{
			throw new RuntimeException ('Missing OpenID Connect authorization code.');
		}

		$token = $this->provider->getAccessToken ('authorization_code', ['code' => $code]);
		$userInfo = $this->provider->getResourceOwner ($token)->toArray ();

		$id = (string) ($userInfo['id'] ?? '');
		if ($id === '')
		{
			throw new RuntimeException ('OpenID Connect profile has no id.');
		}

		return [
			'id' => $id,
			'email' => (string) ($userInfo['email'] ?? ''),
			'username' => (string) ($userInfo['username'] ?? ''),
			'verified_email' => (bool) ($userInfo['verified_email'] ?? false),
		];
	}
}
