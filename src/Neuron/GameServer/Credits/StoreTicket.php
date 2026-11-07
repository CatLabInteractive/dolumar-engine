<?php

/**
 * A short-lived, signed ticket that lets the app's bundled store page (on its
 * own origin, without the game's session cookie) register a store purchase
 * for one player. Issued to the logged-in player by api/store/ticket, read
 * by api/store/register.
 *
 * Format: base64url("<plid>.<expires>") . "." . base64url(hmac-sha256).
 */
class Neuron_GameServer_Credits_StoreTicket
{
	const TTL = 900;

	private string $key;

	public function __construct (string $secret)
	{
		// Never sign with the raw client secret: derive a key for this purpose.
		$this->key = hash_hmac ('sha256', 'store-ticket', $secret, true);
	}

	/**
	 * @return self|null null when there is no secret to sign with
	 */
	public static function fromConfig (): ?self
	{
		if (!defined ('OPENID_CONNECT_SECRET') || (string) OPENID_CONNECT_SECRET === '')
		{
			return null;
		}
		return new self ((string) OPENID_CONNECT_SECRET);
	}

	public function issue (int $plid, ?int $now = null): string
	{
		$payload = $plid . '.' . (($now ?? time ()) + self::TTL);
		return self::encode ($payload) . '.' . self::encode ($this->sign ($payload));
	}

	/**
	 * @return int|null the player id, null when the ticket is forged or expired
	 */
	public function read (string $ticket, ?int $now = null): ?int
	{
		$parts = explode ('.', $ticket);
		if (count ($parts) !== 2 || strlen ($ticket) > 200)
		{
			return null;
		}

		$payload = self::decode ($parts[0]);
		$signature = self::decode ($parts[1]);
		if ($payload === null || $signature === null || !hash_equals ($this->sign ($payload), $signature))
		{
			return null;
		}

		if (!preg_match ('/^([1-9][0-9]{0,11})\.([0-9]{1,12})$/', $payload, $m))
		{
			return null;
		}

		if ((int) $m[2] < ($now ?? time ()))
		{
			return null;
		}

		return (int) $m[1];
	}

	private function sign (string $payload): string
	{
		return hash_hmac ('sha256', $payload, $this->key, true);
	}

	private static function encode (string $raw): string
	{
		return rtrim (strtr (base64_encode ($raw), '+/', '-_'), '=');
	}

	private static function decode (string $encoded): ?string
	{
		if ($encoded === '' || !preg_match ('/^[A-Za-z0-9_-]+$/', $encoded))
		{
			return null;
		}
		$raw = base64_decode (strtr ($encoded, '-_', '+/'), true);
		return $raw === false ? null : $raw;
	}
}
