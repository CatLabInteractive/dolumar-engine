<?php

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Thrown when CatLab Accounts cannot be reached or answers with a 5xx.
 */
class Neuron_GameServer_Credits_AccountsUnavailable extends RuntimeException
{
}

/**
 * Thrown when Accounts rejects a request with a 4xx (a permanent failure,
 * unlike AccountsUnavailable). The message never contains secrets.
 */
class Neuron_GameServer_Credits_AccountsRejected extends RuntimeException
{
	private int $status;

	public function __construct (int $status, string $message = '')
	{
		parent::__construct ($message !== '' ? $message : 'Accounts rejected the request (HTTP ' . $status . ').', $status);
		$this->status = $status;
	}

	public function getStatus (): int
	{
		return $this->status;
	}
}

/**
 * HTTP client for the credits API of CatLab Accounts.
 * Never put access tokens or the client secret in exception messages or logs.
 */
class Neuron_GameServer_Credits_AccountsClient
{
	private string $baseUrl;
	private string $clientId;
	private string $clientSecret;
	private ClientInterface $http;

	public function __construct (string $baseUrl, string $clientId, string $clientSecret, ?ClientInterface $http = null)
	{
		$this->baseUrl = rtrim ($baseUrl, '/') . '/';
		$this->clientId = $clientId;
		$this->clientSecret = $clientSecret;
		$this->http = $http ?? new Client (['timeout' => 10, 'connect_timeout' => 5, 'http_errors' => false]);
	}

	/**
	 * @return self|null null when credits are not configured
	 */
	public static function fromConfig (): ?self
	{
		foreach (['OPENID_CONNECT_CLIENT_ID', 'OPENID_CONNECT_SECRET', 'CATLAB_CREDITS_TYPE_ID'] as $name)
		{
			if (!defined ($name) || constant ($name) === '' || constant ($name) === null || constant ($name) === false)
			{
				return null;
			}
		}

		$url = defined ('CATLAB_ACCOUNTS_URL') && CATLAB_ACCOUNTS_URL ? CATLAB_ACCOUNTS_URL : 'https://accounts.catlab.eu/';
		return new self ($url, (string)OPENID_CONNECT_CLIENT_ID, (string)OPENID_CONNECT_SECRET);
	}

	public function getBuyUrl (): string
	{
		return $this->baseUrl . 'credits/' . CATLAB_CREDITS_TYPE_ID . '/purchase';
	}

	public function getPersonalProfileId (string $accessToken): ?int
	{
		$data = $this->send ('GET', 'api/1.0/profiles', $this->bearer ($accessToken));
		foreach (($data['items'] ?? []) as $profile)
		{
			if (($profile['personal'] ?? false) === true && isset ($profile['id']))
			{
				return (int)$profile['id'];
			}
		}
		return null;
	}

	public function getBalance (string $accessToken): ?int
	{
		$data = $this->send ('GET', 'api/1.0/users/me/credits/credit-price', $this->bearer ($accessToken));
		return isset ($data['price']['available']) ? (int)$data['price']['available'] : null;
	}

	/**
	 * @return array{id:int, confirmUrl:string}
	 */
	public function createRequest (string $accessToken, int $profileId, int $credits, string $reason, string $notifyUrl, string $returnUrl, array $extra): array
	{
		$data = $this->send ('POST', 'api/1.0/profiles/' . $profileId . '/credits/request', $this->bearer ($accessToken) + [
			'json' => [
				'credits' => $credits,
				'reason' => $reason,
				'notify_url' => $notifyUrl,
				'return' => $returnUrl,
				'extra' => $extra,
			]
		], true);

		if (!isset ($data['id'], $data['confirmUrl']))
		{
			throw new Neuron_GameServer_Credits_AccountsUnavailable ('Accounts returned an invalid credit request.');
		}

		return ['id' => (int)$data['id'], 'confirmUrl' => (string)$data['confirmUrl']];
	}

	public function getLoginToken (string $accessToken): ?string
	{
		$data = $this->send ('POST', 'api/1.0/users/me/login-token', $this->bearer ($accessToken));
		return isset ($data['token']) ? (string)$data['token'] : null;
	}

	public function getRequest (int $requestId): ?array
	{
		return $this->send ('GET', 'api/1.0/credits/requests/' . $requestId, $this->basic (), true, [404]);
	}

	public function refund (int $profileId, int $credits, string $reason, ?int $creditRequestId): bool
	{
		$json = ['credits' => $credits, 'reason' => $reason];
		if ($creditRequestId)
		{
			$json['credit_request_id'] = $creditRequestId;
		}

		$data = $this->send ('POST', 'api/1.0/profiles/' . $profileId . '/credits/refund', $this->basic () + ['json' => $json]);
		return $data !== null && !empty ($data['success']);
	}

	private function bearer (string $accessToken): array
	{
		return ['headers' => ['Authorization' => 'Bearer ' . $accessToken, 'Accept' => 'application/json']];
	}

	private function basic (): array
	{
		return ['headers' => ['Authorization' => 'Basic ' . base64_encode ($this->clientId . ':' . $this->clientSecret), 'Accept' => 'application/json']];
	}

	/**
	 * @return array|null decoded body, null on 4xx
	 * @throws Neuron_GameServer_Credits_AccountsUnavailable on network failure or 5xx
	 * @throws Neuron_GameServer_Credits_AccountsRejected on 4xx when $rejectOnClientError (except $nullStatuses)
	 */
	private function send (string $method, string $path, array $options, bool $rejectOnClientError = false, array $nullStatuses = []): ?array
	{
		$options['http_errors'] = false;

		try
		{
			$response = $this->http->request ($method, $this->baseUrl . $path, $options);
		}
		catch (GuzzleException $e)
		{
			// Do not chain the exception: its message may contain request details.
			throw new Neuron_GameServer_Credits_AccountsUnavailable ('Accounts is unreachable.');
		}

		$status = $response->getStatusCode ();
		if ($status >= 500)
		{
			throw new Neuron_GameServer_Credits_AccountsUnavailable ('Accounts returned HTTP ' . $status . '.');
		}
		if ($status >= 400)
		{
			if ($rejectOnClientError && !in_array ($status, $nullStatuses, true))
			{
				$error = json_decode ((string)$response->getBody (), true);
				$text = is_array ($error) && isset ($error['error']) && is_string ($error['error']) ? $error['error'] : '';
				throw new Neuron_GameServer_Credits_AccountsRejected ($status, $text !== '' ? 'Accounts rejected the request (HTTP ' . $status . '): ' . $text : '');
			}
			return null;
		}

		$data = json_decode ((string)$response->getBody (), true);
		return is_array ($data) ? $data : null;
	}
}
