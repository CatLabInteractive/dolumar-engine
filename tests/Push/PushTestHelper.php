<?php

/*
	Shared set-up for the Web Push tests: VAPID keys, temporary tables and
	browser-style subscription keys.
*/
class PushTestHelper
{
	public static function enablePush(): void
	{
		if (!defined('VAPID_PUBLIC_KEY')) {
			$keys = Minishlink\WebPush\VAPID::createVapidKeys();
			define('VAPID_PUBLIC_KEY', $keys['publicKey']);
			define('VAPID_PRIVATE_KEY', $keys['privateKey']);
			define('VAPID_SUBJECT', 'mailto:test@example.com');
		}

		if (!defined('ABSOLUTE_URL')) {
			define('ABSOLUTE_URL', 'https://game.example.com/');
		}
	}

	public static function createTables(): void
	{
		$db = Neuron_DB_Database::getInstance();
		self::dropTables();
		$db->query("
			CREATE TEMPORARY TABLE n_push_subscriptions (
				ps_id INT AUTO_INCREMENT PRIMARY KEY,
				ps_plid INT NOT NULL,
				ps_endpoint VARCHAR(700) CHARACTER SET ascii NOT NULL,
				ps_p256dh VARCHAR(128) CHARACTER SET ascii NOT NULL,
				ps_auth VARCHAR(64) CHARACTER SET ascii NOT NULL,
				ps_useragent VARCHAR(255) NOT NULL DEFAULT '',
				ps_created DATETIME NOT NULL,
				ps_lastsuccess DATETIME NULL,
				UNIQUE KEY ps_endpoint (ps_endpoint),
				KEY ps_plid (ps_plid)
			) DEFAULT CHARSET=utf8mb4
		");
		$db->query("
			CREATE TEMPORARY TABLE n_push_queue (
				pn_id INT AUTO_INCREMENT PRIMARY KEY,
				pn_plid INT NOT NULL,
				pn_payload TEXT NOT NULL,
				pn_created DATETIME NOT NULL,
				KEY pn_plid (pn_plid)
			) DEFAULT CHARSET=utf8mb4
		");
	}

	public static function dropTables(): void
	{
		$db = Neuron_DB_Database::getInstance();
		$db->query('DROP TEMPORARY TABLE IF EXISTS n_push_subscriptions');
		$db->query('DROP TEMPORARY TABLE IF EXISTS n_push_queue');
	}

	/*
		Keys as a browser would send them: an uncompressed P-256 point and a 16 byte secret.
	*/
	public static function browserKeys(): array
	{
		$key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
		$details = openssl_pkey_get_details($key);
		$point = "\x04" . str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);

		return [
			'p256dh' => self::base64url($point),
			'auth' => self::base64url(random_bytes(16)),
		];
	}

	public static function base64url(string $raw): string
	{
		return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
	}

	public static function subscriptionJson(string $endpoint, ?array $keys = null): string
	{
		return json_encode(['endpoint' => $endpoint, 'keys' => $keys ?? self::browserKeys()]);
	}
}

/*
	A player whose preferences live in memory, so the tests need no n_players rows.
*/
class PushTestPlayer extends Neuron_GameServer_Player
{
	public array $prefs = [];
	public array $categories = ['battle' => 'Battles', 'clan' => 'Clan', 'building' => 'Buildings'];
	public array $defaultMuted = ['clan'];

	public function getPreference($sKey, $default = false)
	{
		return array_key_exists($sKey, $this->prefs) ? $this->prefs[$sKey] : $default;
	}

	public function setPreference($sKey, $sValue)
	{
		$this->prefs[$sKey] = (string) $sValue;
	}

	public function getPushCategories()
	{
		return $this->categories;
	}

	public function getPushDefaultMuted()
	{
		return $this->defaultMuted;
	}

	protected function getPushTitle()
	{
		return 'Dolumar Test';
	}
}
