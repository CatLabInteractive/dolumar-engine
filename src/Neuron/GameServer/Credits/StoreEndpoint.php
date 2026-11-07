<?php

/**
 * The api/store/* routes behind the app's in-app purchases.
 *
 *   POST api/store/ticket    (game page, session)  -> {ticket, url}
 *   GET  api/store/packs     (store page, CORS)    -> {packs: [{sku, credits}]}
 *   POST api/store/register  (store page, CORS)    -> Accounts' answer
 *
 * The game page runs in the app's WebView and cannot talk to the store
 * plugin; the app's bundled store page can, but has no game session. So the
 * game page asks for a ticket (a signed, short-lived player id), navigates to
 * the store page with it, and the store page registers the purchase with the
 * ticket. Accounts verifies the receipt with Google or Apple and credits the
 * player's personal profile; only after a 200 does the store page finish the
 * store transaction. Free of header()/echo so it can be tested; dispatch()
 * prints.
 */
class Neuron_GameServer_Credits_StoreEndpoint
{
	const MAX_BODY = 32768;
	const SOURCES = ['google', 'apple'];

	private Neuron_GameServer_Credits_AccountsClient $client;
	private Neuron_GameServer_Credits_StoreTicket $tickets;
	/** @var callable int plid => Neuron_GameServer_Player|null */
	private $playerLoader;
	/** @var callable Neuron_GameServer_Player => array{profile_id:?int}|null */
	private $authLoader;
	/** @var callable */
	private $now;

	public function __construct
	(
		Neuron_GameServer_Credits_AccountsClient $client,
		Neuron_GameServer_Credits_StoreTicket $tickets,
		?callable $playerLoader = null,
		?callable $authLoader = null,
		?callable $now = null
	)
	{
		$this->client = $client;
		$this->tickets = $tickets;
		$this->playerLoader = $playerLoader ?? function (int $plid) {
			$player = Neuron_GameServer::getPlayer ($plid);
			return $player && $player->isFound () ? $player : null;
		};
		$this->authLoader = $authLoader ?? function (Neuron_GameServer_Player $player) {
			return Neuron_GameServer_Mappers_CatLabAuthMapper::getInstance ()->getForPlayer ($player);
		};
		$this->now = $now ?? 'time';
	}

	/**
	 * @param Neuron_GameServer_Player|false|null $player the session player (ticket only)
	 * @return array{code:int, body:array, headers:array<string,string>}
	 */
	public function handle (?string $action, $player, string $method, string $contentType, string $rawBody, ?string $origin, ?string $host, string $userAgent = ''): array
	{
		$method = strtoupper ($method);
		$headers = ['Cache-Control' => 'no-store'];

		switch ($action)
		{
			case 'ticket':
				return $this->ticket ($player, $method, $origin, $host, $userAgent, $headers);

			case 'packs':
			case 'register':
				$cors = $this->cors ($origin, $action === 'packs' ? 'GET' : 'POST');
				if ($cors === null)
				{
					return $this->answer (403, ['error' => 'origin_not_allowed'], $headers);
				}
				$headers += $cors;
				if ($method === 'OPTIONS')
				{
					return $this->answer (204, [], $headers);
				}
				return $action === 'packs'
					? $this->packs ($method, $headers)
					: $this->register ($method, $contentType, $rawBody, $headers);

			default:
				return $this->answer (404, ['error' => 'not_found'], $headers);
		}
	}

	private function ticket ($player, string $method, ?string $origin, ?string $host, string $userAgent, array $headers): array
	{
		if ($method !== 'POST')
		{
			return $this->answer (405, ['error' => 'method_not_allowed'], $headers);
		}
		if (!self::isSameOrigin ($origin, $host))
		{
			return $this->answer (403, ['error' => 'cross_origin'], $headers);
		}
		if (!($player instanceof Neuron_GameServer_Player))
		{
			return $this->answer (401, ['error' => 'not_logged_in'], $headers);
		}

		$ticket = $this->tickets->issue ((int) $player->getId (), $this->now ());
		$url = Neuron_GameServer_App::getOrigin ($userAgent) . '/store.html'
			. '?api=' . rawurlencode (API_FULL_URL . 'store/')
			. '&ticket=' . rawurlencode ($ticket);

		return $this->answer (200, ['ticket' => $ticket, 'expires_in' => Neuron_GameServer_Credits_StoreTicket::TTL, 'url' => $url], $headers);
	}

	private function packs (string $method, array $headers): array
	{
		if ($method !== 'GET')
		{
			return $this->answer (405, ['error' => 'method_not_allowed'], $headers);
		}

		try
		{
			return $this->answer (200, ['packs' => $this->client->getStorePacks ()], $headers);
		}
		catch (Neuron_GameServer_Credits_AccountsUnavailable | Neuron_GameServer_Credits_AccountsRejected $e)
		{
			error_log ('Store: packs unavailable: ' . get_class ($e));
			return $this->answer (503, ['error' => 'unavailable', 'reason' => 'accounts'], $headers);
		}
	}

	private function register (string $method, string $contentType, string $rawBody, array $headers): array
	{
		if ($method !== 'POST')
		{
			return $this->answer (405, ['error' => 'method_not_allowed'], $headers);
		}
		if (stripos ($contentType, 'application/json') !== 0)
		{
			return $this->answer (415, ['error' => 'json_expected'], $headers);
		}
		if (strlen ($rawBody) > self::MAX_BODY)
		{
			return $this->answer (413, ['error' => 'too_large'], $headers);
		}

		$data = json_decode ($rawBody, true);
		$purchase = self::readPurchase (is_array ($data) ? $data : []);
		if ($purchase === null)
		{
			return $this->answer (400, ['error' => 'invalid_request'], $headers);
		}

		$plid = isset ($data['ticket']) && is_string ($data['ticket']) ? $this->tickets->read ($data['ticket'], $this->now ()) : null;
		$player = $plid === null ? null : ($this->playerLoader) ($plid);
		if (!($player instanceof Neuron_GameServer_Player))
		{
			return $this->answer (401, ['error' => 'invalid_ticket'], $headers);
		}

		$auth = ($this->authLoader) ($player);
		if ($auth === null || empty ($auth['profile_id']))
		{
			return $this->answer (401, ['error' => 'no_profile'], $headers);
		}

		try
		{
			$result = $this->client->registerStorePurchase ((int) $auth['profile_id'], $purchase);
		}
		catch (Neuron_GameServer_Credits_AccountsUnavailable $e)
		{
			error_log ('Store: Accounts unreachable while registering a ' . $purchase['source'] . ' purchase for player ' . $plid);
			return $this->answer (503, ['error' => 'unavailable', 'reason' => 'accounts'], $headers);
		}

		if ($result['status'] === 200 && isset ($result['body']['status']))
		{
			return $this->answer (200, $result['body'], $headers);
		}

		if (!isset ($result['body']['error']) || !is_string ($result['body']['error']))
		{
			error_log ('Store: Accounts answered HTTP ' . $result['status'] . ' for a ' . $purchase['source'] . ' purchase of player ' . $plid);
			return $this->answer (503, ['error' => 'unavailable', 'reason' => 'accounts'], $headers);
		}

		// 400 invalid_request, 403 rejected {reason}, 404 not_found, 503 unavailable {reason}: as is.
		error_log ('Store: Accounts answered HTTP ' . $result['status'] . ' ' . $result['body']['error']
			. (isset ($result['body']['reason']) && is_string ($result['body']['reason']) ? ' (' . $result['body']['reason'] . ')' : '')
			. ' for a ' . $purchase['source'] . ' purchase of player ' . $plid);
		$body = ['error' => (string) $result['body']['error']];
		if (isset ($result['body']['reason']) && is_string ($result['body']['reason']))
		{
			$body['reason'] = $result['body']['reason'];
		}
		return $this->answer ($result['status'], $body, $headers);
	}

	/**
	 * The purchase as Accounts wants it, or null when the body does not fit.
	 */
	public static function readPurchase (array $data): ?array
	{
		$source = $data['source'] ?? null;
		$sku = $data['sku'] ?? null;
		$reference = $data['store_reference'] ?? null;
		$proof = $data['proof'] ?? null;

		if (!in_array ($source, self::SOURCES, true)
			|| !is_string ($sku) || !preg_match ('/^credits\.[0-9]{1,9}$/', $sku)
			|| !is_string ($reference) || !preg_match ('/^[A-Za-z0-9._-]{1,255}$/', $reference)
			|| !is_array ($proof) || $proof === [])
		{
			return null;
		}

		return ['source' => $source, 'sku' => $sku, 'store_reference' => $reference, 'proof' => $proof];
	}

	/**
	 * CORS headers for a request from one of the app's bundled pages, null
	 * when the origin is not the app. A request without an Origin header is
	 * not cross-origin (curl, the game page itself) and is allowed.
	 *
	 * @return array<string,string>|null
	 */
	private function cors (?string $origin, string $allowMethod): ?array
	{
		if ($origin === null || $origin === '')
		{
			return [];
		}
		if (!in_array (rtrim ($origin, '/'), Neuron_GameServer_App::getOrigins (), true))
		{
			return null;
		}
		return [
			'Access-Control-Allow-Origin' => rtrim ($origin, '/'),
			'Access-Control-Allow-Methods' => $allowMethod . ', OPTIONS',
			'Access-Control-Allow-Headers' => 'Content-Type',
			'Access-Control-Max-Age' => '600',
			'Vary' => 'Origin',
		];
	}

	private static function isSameOrigin (?string $origin, ?string $host): bool
	{
		if ($origin === null || $origin === '')
		{
			return true;
		}
		$originHost = parse_url ($origin, PHP_URL_HOST);
		$ownHost = parse_url ('http://' . (string) $host, PHP_URL_HOST);
		return is_string ($originHost) && is_string ($ownHost) && strcasecmp ($originHost, $ownHost) === 0;
	}

	private function answer (int $code, array $body, array $headers): array
	{
		return ['code' => $code, 'body' => $body, 'headers' => $headers];
	}

	private function now (): int
	{
		return (int) ($this->now) ();
	}

	/**
	 * Run the route for the current request and print the JSON answer.
	 */
	public static function dispatch (?string $action): void
	{
		$client = Neuron_GameServer_Credits_AccountsClient::fromConfig ();
		$tickets = Neuron_GameServer_Credits_StoreTicket::fromConfig ();
		if ($client === null || $tickets === null)
		{
			http_response_code (404);
			header ('Content-Type: application/json; charset=utf-8');
			echo json_encode (['error' => 'disabled']);
			return;
		}

		$endpoint = new self ($client, $tickets);
		$player = $action === 'ticket' ? Neuron_GameServer::getPlayer () : null;

		$result = $endpoint->handle
		(
			$action,
			$player ? $player : null,
			isset ($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET',
			isset ($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '',
			(string) file_get_contents ('php://input', false, null, 0, self::MAX_BODY + 1),
			isset ($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : null,
			isset ($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null,
			isset ($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''
		);

		http_response_code ($result['code']);
		foreach ($result['headers'] as $name => $value)
		{
			header ($name . ': ' . $value);
		}
		if ($result['code'] !== 204)
		{
			header ('Content-Type: application/json; charset=utf-8');
			echo json_encode ($result['body']);
		}
	}
}
