<?php

/**
 * Stores the CatLab Accounts access token and personal profile id per
 * Accounts openid url (table n_auth_catlab). Never log the token.
 */
class Neuron_GameServer_Mappers_CatLabAuthMapper
{
	public static function getInstance (): self
	{
		static $in;
		if (!isset ($in))
		{
			$in = new self ();
		}
		return $in;
	}

	/**
	 * Insert or replace the stored token for an openid url.
	 */
	public function save (string $openIdUrl, ?int $profileId, string $accessToken, string $scope): void
	{
		$db = Neuron_DB_Database::getInstance ();

		$db->query
		(
			"INSERT INTO n_auth_catlab (openid_url, profile_id, access_token, scope, updated_at) VALUES ("
			. "'" . $db->escape ($openIdUrl) . "', "
			. ($profileId === null ? 'NULL' : (int) $profileId) . ", "
			. "'" . $db->escape ($accessToken) . "', "
			. "'" . $db->escape ($scope) . "', NOW()) "
			. "ON DUPLICATE KEY UPDATE profile_id = VALUES(profile_id), access_token = VALUES(access_token), "
			. "scope = VALUES(scope), updated_at = VALUES(updated_at)"
		);
	}

	/**
	 * The most recently updated Accounts login of this player that has a token.
	 *
	 * @return array{openid_url:string, profile_id:?int, access_token:string, scope:string}|null
	 */
	public function getForPlayer (Neuron_GameServer_Player $player): ?array
	{
		$db = Neuron_DB_Database::getInstance ();
		$prefix = OPENID_CONNECT_PROFILE_URL_PREFIX;

		$result = $db->query
		(
			"SELECT c.openid_url, c.profile_id, c.access_token, c.scope "
			. "FROM n_auth_openid o JOIN n_auth_catlab c ON c.openid_url = o.openid_url "
			. "WHERE o.user_id = " . (int) $player->getId () . " "
			. "AND LEFT(o.openid_url, " . strlen ($prefix) . ") = '" . $db->escape ($prefix) . "' "
			. "AND c.access_token IS NOT NULL AND c.access_token <> '' "
			. "ORDER BY c.updated_at DESC LIMIT 1"
		);

		if ($result->getNumRows () === 0)
		{
			return null;
		}

		$row = $result[0];
		return [
			'openid_url' => (string) $row['openid_url'],
			'profile_id' => $row['profile_id'] === null ? null : (int) $row['profile_id'],
			'access_token' => (string) $row['access_token'],
			'scope' => (string) ($row['scope'] ?? ''),
		];
	}
}
