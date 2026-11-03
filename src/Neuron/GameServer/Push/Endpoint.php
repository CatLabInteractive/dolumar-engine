<?php
/**
 *  Dolumar engine, php/html MMORTS engine
 *  Copyright (C) 2009 Thijs Van der Schaeghe
 *  CatLab Interactive bvba, Gent, Belgium
 *  http://www.catlab.eu/
 *  http://www.dolumar.com/
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License along
 *  with this program; if not, write to the Free Software Foundation, Inc.,
 *  51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

/*
	The push/subscribe and push/unsubscribe routes.

	The browser posts its PushSubscription as JSON. Only a logged-in player can
	store one, only same-origin requests are accepted (a JSON content type cannot
	be sent cross-site without a CORS preflight, which we never answer), and only
	endpoints on known push services are stored, so the cron job never posts to an
	address a player made up.
*/
class Neuron_GameServer_Push_Endpoint
{
	const MAX_ENDPOINT_LENGTH = 700;

	/*
		Hosts of the browser push services. A suffix match on the endpoint host.
	*/
	private static $allowedHosts = array
	(
		'fcm.googleapis.com',          // Chrome, Edge (new), Opera, Samsung Internet
		'android.googleapis.com',
		'push.services.mozilla.com',   // Firefox
		'notify.windows.com',          // Edge (legacy)
		'push.apple.com'               // Safari (web.push.apple.com)
	);

	/*
		@return array (status code, response array)
	*/
	public static function handle ($action, $player, $method, $contentType, $rawBody, $origin = null, $host = null, $userAgent = '')
	{
		if (!Neuron_GameServer_Push_Config::isEnabled ())
		{
			return array (404, array ('error' => 'disabled'));
		}

		if ($action !== 'subscribe' && $action !== 'unsubscribe')
		{
			return array (404, array ('error' => 'not found'));
		}

		if (strtoupper ((string) $method) !== 'POST')
		{
			return array (405, array ('error' => 'method not allowed'));
		}

		if (stripos ((string) $contentType, 'application/json') !== 0)
		{
			return array (415, array ('error' => 'json expected'));
		}

		if (!self::isSameOrigin ($origin, $host))
		{
			return array (403, array ('error' => 'cross-origin request'));
		}

		if (!($player instanceof Neuron_GameServer_Player))
		{
			return array (401, array ('error' => 'not logged in'));
		}

		$data = json_decode ((string) $rawBody, true);
		$subscription = self::parseSubscription ($data, $action === 'subscribe');
		if ($subscription === null)
		{
			return array (400, array ('error' => 'invalid subscription'));
		}

		$mapper = Neuron_GameServer_Mappers_PushMapper::getInstance ();

		if ($action === 'subscribe')
		{
			$mapper->addSubscription
			(
				$player->getId (),
				$subscription['endpoint'],
				$subscription['p256dh'],
				$subscription['auth'],
				$userAgent
			);
		}
		else
		{
			$mapper->removeSubscription ($player->getId (), $subscription['endpoint']);
		}

		return array (200, array ('success' => true));
	}

	/*
		@return array with endpoint, p256dh, auth, or null when invalid.
	*/
	public static function parseSubscription ($data, $requireKeys = true)
	{
		if (!is_array ($data) || !isset ($data['endpoint']) || !is_string ($data['endpoint']))
		{
			return null;
		}

		$endpoint = $data['endpoint'];
		if (!self::isAllowedEndpoint ($endpoint))
		{
			return null;
		}

		$p256dh = isset ($data['keys']['p256dh']) ? $data['keys']['p256dh'] : null;
		$auth = isset ($data['keys']['auth']) ? $data['keys']['auth'] : null;

		if ($requireKeys)
		{
			// P-256 public key: 65 bytes, base64url ~87 chars. Auth secret: 16 bytes, ~22 chars.
			if (!self::isBase64Url ($p256dh, 80, 100) || !self::isBase64Url ($auth, 16, 32))
			{
				return null;
			}
		}

		return array
		(
			'endpoint' => $endpoint,
			'p256dh' => is_string ($p256dh) ? $p256dh : '',
			'auth' => is_string ($auth) ? $auth : ''
		);
	}

	public static function isAllowedEndpoint ($endpoint)
	{
		if (!is_string ($endpoint) || strlen ($endpoint) > self::MAX_ENDPOINT_LENGTH)
		{
			return false;
		}

		if (preg_match ('/[\x00-\x20\x7f-\xff]/', $endpoint))
		{
			return false;
		}

		$parts = parse_url ($endpoint);
		if
		(
			$parts === false ||
			!isset ($parts['scheme']) || strtolower ($parts['scheme']) !== 'https' ||
			!isset ($parts['host']) ||
			isset ($parts['user']) || isset ($parts['pass']) || isset ($parts['port'])
		)
		{
			return false;
		}

		$host = strtolower ($parts['host']);
		foreach (self::$allowedHosts as $allowed)
		{
			if ($host === $allowed || substr ($host, -strlen ('.' . $allowed)) === '.' . $allowed)
			{
				return true;
			}
		}

		return false;
	}

	private static function isSameOrigin ($origin, $host)
	{
		// Browsers send Origin on every POST from fetch(); a missing one is not a browser cross-site request.
		if ($origin === null || $origin === '')
		{
			return true;
		}

		$originHost = parse_url ((string) $origin, PHP_URL_HOST);
		$ownHost = parse_url ('http://' . (string) $host, PHP_URL_HOST);

		return is_string ($originHost) && is_string ($ownHost) && strcasecmp ($originHost, $ownHost) === 0;
	}

	private static function isBase64Url ($value, $min, $max)
	{
		return is_string ($value) && preg_match ('/^[A-Za-z0-9_-]{' . intval ($min) . ',' . intval ($max) . '}={0,2}$/', $value) === 1;
	}

	/*
		Run the route for the current request and print the JSON response.
	*/
	public static function dispatch ($action)
	{
		$player = Neuron_GameServer::getPlayer ();

		list ($status, $response) = self::handle
		(
			$action,
			$player ? $player : null,
			isset ($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET',
			isset ($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '',
			file_get_contents ('php://input', false, null, 0, 8192),
			isset ($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : null,
			isset ($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null,
			isset ($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''
		);

		http_response_code ($status);
		header ('Content-Type: application/json; charset=utf-8');
		header ('Cache-Control: no-store');
		echo json_encode ($response);
	}
}
