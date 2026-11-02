<?php

/**
 * HTTP-level logic of the creditstart / creditnotify api endpoints, kept free
 * of header()/echo so it can be tested. Results are
 * array{code:int, location:?string, body:string}.
 */
class Neuron_GameServer_Credits_ApiHandler
{
	/** Routing / session params that are not part of the purchase data. */
	private const ROUTING_PARAMS = ['module', 'session_id', 'session_pass', 'sessionId', 'phpSessionId', 'rand', 'language', 'DEBUG', 'output'];

	private Neuron_GameServer_Credits_PurchaseService $service;
	private string $accountsBaseUrl;

	public function __construct (Neuron_GameServer_Credits_PurchaseService $service, string $accountsBaseUrl)
	{
		$this->service = $service;
		$this->accountsBaseUrl = rtrim ($accountsBaseUrl, '/') . '/';
	}

	/**
	 * @param Neuron_GameServer_Player|false|null $player the session player
	 */
	public function start ($player, array $get): array
	{
		if (!$player)
		{
			return $this->login ();
		}

		foreach (self::ROUTING_PARAMS as $key)
		{
			unset ($get[$key]);
		}

		$result = $this->service->start ($player, $get);

		switch ($result['status'])
		{
			case 'redirect':
				$url = (string) $result['url'];
				// Only ever send the browser to Accounts.
				if (strpos ($url, $this->accountsBaseUrl) === 0)
				{
					return ['code' => 302, 'location' => $url, 'body' => '<p>Redirecting to CatLab Accounts...</p>'];
				}
				error_log ('Credits: refusing redirect to a non-Accounts url');
				return $this->message ('Credits are temporarily unavailable. Please try again later.');

			case 'login':
				return $this->login ();

			case 'invalid':
				return $this->message ('This purchase is not possible right now.');

			default:
				return $this->message ('Credits are temporarily unavailable. Please try again later.');
		}
	}

	public function notify (string $method, array $post): array
	{
		if (strtoupper ($method) !== 'POST')
		{
			return ['code' => 405, 'location' => null, 'body' => 'Method Not Allowed'];
		}

		$id = isset ($post['credit_request_id']) ? (int) $post['credit_request_id'] : 0;
		if ($id <= 0)
		{
			return ['code' => 200, 'location' => null, 'body' => 'ignored'];
		}

		try
		{
			$word = $this->service->handleNotify ($id);
		}
		catch (Throwable $e)
		{
			// The purchase stays pending; reconcile settles it.
			error_log ('Credits: notify ' . $id . ' failed: ' . get_class ($e));
			$word = 'pending';
		}

		return ['code' => 200, 'location' => null, 'body' => $word];
	}

	private function login (): array
	{
		return ['code' => 302, 'location' => Neuron_URLBuilder::getInstance ()->getRawURL ('oauth2/login', []), 'body' => '<p>Please log in again.</p>'];
	}

	private function message (string $text): array
	{
		return ['code' => 200, 'location' => null, 'body' => '<p>' . htmlspecialchars ($text) . '</p>'];
	}
}
