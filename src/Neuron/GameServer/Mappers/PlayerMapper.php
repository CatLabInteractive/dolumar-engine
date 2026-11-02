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

class Neuron_GameServer_Mappers_PlayerMapper
{
	const TABLE_PLAYERS = 'players';
	const TABLE_PLAYERS_ADMIN_CLEARED = 'players_admin_cleared';
	const TABLE_PLAYERS_BANNED = 'players_banned';
	const TABLE_PLAYERS_PREFERENCES = 'players_preferences';
	const TABLE_PLAYERS_SOCIAL = 'players_social';
	const TABLE_PLAYERS_TILES = 'players_tiles';

	private static function generateCode ()
	{
		$code = '';
		for ($i = 0; $i < 32; $i ++)
		{
			$code .= dechex (mt_rand (0, 15));
		}
		return $code;
	}

	public static function getFromId ($id)
	{
		return Neuron_GameServer::getPlayer ($id);
	}

	public static function createTemporaryPlayer ()
	{
		$db = Neuron_DB_Database::getInstance ();

		$player = self::createPlayer ();

		$code = self::generateCode ();

		$db->query 
		("
			INSERT INTO
				n_players_temporary
			SET
				plid = {$player->getId ()},
				pt_code = '{$db->escape ($code)}'
		");

		return $player;
	}

	public static function createPlayer ()
	{
		$db = Neuron_DB_Database::getInstance ();

		$id = $db->query 
		("
			INSERT INTO
				n_players
			SET
				creationDate = NOW()
		");

		return Neuron_GameServer::getPlayer ($id);
	}

	public static function getTemporaryPlayerData (Neuron_GameServer_Player $player)
	{
		$db = Neuron_DB_Database::getInstance ();

		$data = $db->query 
		("
			SELECT
				*
			FROM
				n_players_temporary
			WHERE
				plid = {$player->getId ()}
		");

		if (count ($data) > 0)
		{
			return $data[0];
		}
		else
		{
			return null;
		}
	}

	public static function getFromOpenID ($openid)
	{
		$db = Neuron_Core_Database::__getInstance ();

		// See if there is an account available
		$acc = $db->select
		(
			'n_auth_openid',
			array ('user_id'),
			"openid_url = '".$db->escape ($openid)."'"
		);

		if (count ($acc) > 0)
		{
			return self::getFromId ($acc[0]['user_id']);
		}

		return null;
	}

	public static function getFromNickname ($nickname)
	{
		$db = Neuron_Core_Database::__getInstance ();
		
		$data = $db->select
		(
			'n_players',
			array ('plid'),
			"nickname = '".$db->escape ($nickname) . "' AND isRemoved = 0"
		);
		
		if (count ($data) == 1)
		{
			return self::getFromId ($data[0]['plid']);
		}
		return null;
	}

	public static function getFromEmail ($email)
	{
		$db = Neuron_Core_Database::__getInstance ();
		$l = $db->select
		(
			'n_players',
			array ('plid'),
			"email = '".$db->escape ($email)."'"
		);
		
		if (count ($l) > 0)
		{
			return self::getFromId ($l[0]['plid']);
		}
		return null;
	}

	/**
	* Just return the data.
	*/
	public static function getDataFromId ($id)
	{
		$db = Neuron_Core_Database::__getInstance ();

		$id = intval ($id);

		$r = $db->getDataFromQuery ($db->customQuery
		("
			SELECT
				*
			FROM
				n_players
			WHERE
				n_players.plid = '".$id."'
		"));
		
		if (count ($r) == 1)
		{
			return $r[0];
		}
		
		return null;
	}

	public static function certifyEmail (Neuron_GameServer_Player $player, $key)
	{
		$db = Neuron_Core_Database::__getInstance ();
		$db->update
		(
			'n_players',
			array
			(
				'email_cert' => 1
			),
			"plid = '".$player->getId ()."' AND email_cert_key = '".$db->escape ($key)."'"
		);
	}

	public static function setEmail (Neuron_GameServer_Player $player, $email, $key)
	{
		$db = Neuron_Core_Database::__getInstance ();
	
		$db->update
		(
			'n_players',
			array
			(
				'email' => $email,
				'email_cert' => 0,
				'email_cert_key' => $key
			),
			"plid = '".$player->getId ()."'"
		);
	}

    public static function setNickname (Neuron_GameServer_Player $player, $nickname)
    {
        $db = Neuron_Core_Database::__getInstance ();

        $db->update
        (
            'n_players',
            array
            (
                'nickname' => $nickname
            ),
            "plid = '".$player->getId ()."'"
        );

        return true;
    }

	public static function setAdminStatus (Neuron_GameServer_Player $player, $status)
	{
		$db = Neuron_DB_Database::getInstance ();

		$status = intval ($status);
		
		$db->query
		("
			UPDATE
				n_players
			SET
				p_admin = $status
			WHERE
				plid = {$player->getId ()}
		");
	}

	public static function extendPremiumAccount (Neuron_GameServer_Player $player, $endtime)
	{
		$db = Neuron_Core_Database::__getInstance ();

		$db->update
		(
			'n_players',
			array
			(
				'premiumEndDate' => Neuron_Core_Tools::timeStampToMysqlDatetime ($endtime)
			),
			"plid = '".$player->getId ()."'"
		);
	}

	public static function setLanguage (Neuron_GameServer_Player $player, $sLang)
	{
		$db = Neuron_DB_Database::getInstance ();
		
		$db->query
		("
			UPDATE
				n_players
			SET
				p_lang = '{$db->escape ($sLang)}'
			WHERE
				plid = {$player->getId ()}
		");
	}

	public static function getOpenIDs (Neuron_GameServer_Player $player, $filterNotifyUrl = true)
	{
		// First: load this users OpenID notification urls
		$db = Neuron_DB_Database::__getInstance ();

		if ($filterNotifyUrl)
		{
			$openid_rows = $db->query
			("
				SELECT
					*
				FROM
					n_auth_openid
				WHERE
					user_id = {$player->getId()}
					AND notify_url IS NOT NULL
					AND notify_url != ''
			");
		}

		else
		{
			$openid_rows = $db->query
			("
				SELECT
					*
				FROM
					n_auth_openid
				WHERE
					user_id = {$player->getId()}
			");
		}

		return $openid_rows;
	}

	public static function setTemporaryKey (Neuron_GameServer_Player $player, $key, $timeout)
	{
		$db->update
		(
			'n_players',
			array
			(
				'tmp_key' => $key,
				'tmp_key_end' => Neuron_Core_Tools::timestampToMysqlDatetime ($timeout)
			),
			"plid = ".$player->getId ()
		);
	}

	public static function resetAccount (Neuron_GameServer_Player $player)
	{
		$db = Neuron_Core_Database::__getInstance ();
		
		// Make non playing
		$db->update
		(
			'n_players',
			array
			(
				'isPlaying' => 0,
				'tmp_key' => NULL,
				'tmp_key_end' => NULL
			),
			"plid = ".$player->getId ()
		);
	}

	public static function startVacationMode (Neuron_GameServer_Player $player)
	{
		$db = Neuron_DB_Database::__getInstance ();
		
		$db->query
		("
			UPDATE
				n_players
			SET
				startVacation = NOW()
			WHERE
				plid = {$player->getId()}
		");
	}

	public static function endVacationMode (Neuron_GameServer_Player $player)
	{
		$db = Neuron_DB_Database::__getInstance ();

		// Remove the vacation mode
		$db->query
		("
			UPDATE
				n_players
			SET
				startVacation = NULL
			WHERE
				plid = {$player->getId()}
		");
	}

	public static function getRank (Neuron_GameServer_Player $player)
	{
		$db = Neuron_Core_Database::__getInstance ();
		
		$rows = $db->getDataFromQuery ($db->customQuery
		("
			SELECT 
				COUNT(*) AS `rank`
			FROM
				n_players a 
			INNER JOIN
				n_players b ON (a.p_score < b.p_score OR (a.p_score = b.p_score AND a.plid > b.plid)) AND b.isPlaying = 1
			WHERE
				a.plid = '".$player->getId ()."'
			GROUP BY a.plid
		"));

		if (count ($rows) > 0)
		{
			$rank = $rows[0]['rank'];
			
		}
		else
		{
			$rank = self::countAll ();
		}
		
		$rank = $rank + 1;

		return $rank;
	}

	public static function countAll ()
	{
		$db = Neuron_Core_Database::__getInstance ();

		$total = $db->select
		(
			'n_players',
			array ('count(plid) AS total')
		);

		$total = count ($total) > 0 ? $total[0]['total'] : 1;

		return $total;
	}

	public static function setScore (Neuron_GameServer_Player $player, $score)
	{
		$db = Neuron_DB_Database::getInstance ();
		
		$score = intval ($score);
		
		$db->query
		("
			UPDATE
				n_players
			SET
				p_score = {$score}
			WHERE
				plid = {$player->getId ()}
		");
	}

	public static function countLogins (Neuron_GameServer_Player $player)
	{
		$db = Neuron_DB_Database::getInstance ();

		$data = $db->query ("SELECT COUNT(*) AS aantal FROM n_login_log WHERE l_plid = {$player->getId ()}");
		return $data[0]['aantal'];
	}
}