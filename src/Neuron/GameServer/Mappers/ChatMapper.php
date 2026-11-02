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

class Neuron_GameServer_Mappers_ChatMapper
{
	/**
	* We need one instance per player.
	*/
	public static function getInstance ()
	{
		static $in;
		if (!isset ($in))
		{
			$in = new self ();
		}
		return $in;
	}

	protected function __construct ()
	{

	}

	private $lastReceivedMessageId = null;

	public function getChannelID ($channel)
	{
		$db = Neuron_DB_Database::getInstance ();

		$check = $db->query
		("
			SELECT
				*
			FROM
				n_chat_channels
			WHERE
				c_c_name = '{$db->escape ($channel)}'
		");

		if (count ($check) == 0)
		{
			$id = $db->query
			("
				INSERT INTO
					n_chat_channels
				SET
					c_c_name = '{$db->escape ($channel)}'
			");
		}
		else
		{
			$id = $check[0]['c_c_id'];
		}

		return $id;
	}

	/**
	*	Return the $since id that is $amount messages ago.
	*/
	private function getNewSince ($channelId, $limit, $before = null)
	{
		$db = Neuron_DB_Database::getInstance ();

		$where = "TRUE";
		if (isset ($before))
		{
			$where = "c_m_id < " . intval ($before);
		}

		$limit ++;

		$sql = "
			SELECT
				c_m_id
			FROM
				n_chat_messages
			WHERE
				c_c_id = '{$channelId}' AND
				{$where}
			ORDER BY
				c_m_id DESC
			LIMIT
				{$limit}
		";

		$messages = $db->query ($sql);

		$count = count ($messages);
		if ($count > 0)
		{
			// minus one, because we need to get the first
			// message as well. This might result in showing
			// 21 messages, but that's okay.
			return $messages[$count - 1]['c_m_id'] - 1;
		}
		return null;
	}

	/**
	* Return the latest messages since $since.
	* $since should be the last received message ID.
	*/
	public function getMessages ($channelId, $limit = 20, $since = null, $before = null)
	{
		$db = Neuron_DB_Database::getInstance ();

		$additionalWhere = "";

		if (!isset ($since))
		{
			$since = $this->getNewSince ($channelId, $limit, $before);

			// raise the limit to load everything at once
			$limit ++;
		}

		// If since is null & before is set, return empty array.
		if ($since === null && isset ($before))
		{
			return array ();
		}

		$additionalWhere = "AND c_m_id > '" . intval ($since) . "' ";

		if (isset ($before))
		{
			$additionalWhere .= "AND c_m_id < '" . intval ($before) . "' ";
		}

		$sql = "
			SELECT
				*,
				UNIX_TIMESTAMP(c_date) AS date
			FROM
				n_chat_messages
			WHERE
				c_c_id = '{$channelId}'
				{$additionalWhere}
			ORDER BY
				c_m_id ASC
			LIMIT
				{$limit}
		";

		//echo $sql;

		$message = $db->query ($sql);

		return $this->getDataFromReader ($message);
	}

	public function getLastReceivedMessageId ()
	{
		return $this->lastReceivedMessageId;
	}

	public function sendMessage ($channelId, $message, Neuron_GameServer_Player $player)
	{
		$profiler = Neuron_Profiler_Profiler::getInstance ();

		$db = Neuron_DB_Database::getInstance ();

		$profiler->message ('Sending message');

		return $db->query
		("
			INSERT INTO
				n_chat_messages
			SET
				c_c_id = '{$db->escape ($channelId)}',
				c_plid = '{$player->getId ()}',
				c_date = NOW(),
				c_message = '{$db->escape ($message)}'
		");
	}

	private function getDataFromReader ($data)
	{
		$out = array ();
		foreach ($data as $v)
		{
			$model = $this->getRecordFromData ($v);
			$out[] = $model;

			// Do something special here, update to latest id
			$this->lastReceivedMessageId = 
				max ($this->lastReceivedMessageId, $model->getId ());
		}
		return $out;
	}

	private function getRecordFromData ($data)
	{
		$message = new Neuron_GameServer_Models_ChatMessage ();

		$message->setId ($data['c_m_id']);
		$message->setPlid ($data['c_plid']);
		$message->setTimestamp ($data['date']);
		$message->setMessage ($data['c_message']);

		if (isset ($data['pu_read']))
		{
			$message->setRead ($data['pu_read']);
		}

		return $message;
	}

	/**
	* This method will be called whenever a user opens
	* a new window. This is basically to decide if
	* the mapper should "unblock" when receiving a
	* message or not. 
	* Information will probalby be stored in session.
	* If the user does not request new messages for
	* a "timeout" period of time, we can "unregister"
	*/
	public function joinChannel (Neuron_GameServer_Player $player, $channelId)
	{
		// Does nothing when not blocking.
	}

	/**
	* For now, probably not used.
	*/
	public function leaveChannel (Neuron_GameServer_Player $player, $channelId)
	{
		// Does nothing when not blocking.
	}

	/**
	*CREATE TABLE  `n_privatechat_updates` (
	*`pu_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
	*`pu_from` INT NOT NULL ,
	*`pu_to` INT NOT NULL ,
	*`pu_date` DATETIME NOT NULL
	*) ENGINE = INNODB
	*/
	public function addPrivateChatUpdate ($msgid, Neuron_GameServer_Player $from, Neuron_GameServer_Player $target)
	{
		$msgid = intval ($msgid);

		$db = Neuron_DB_Database::getInstance ();

		$db->query
		("
			DELETE FROM
				n_privatechat_updates
			WHERE
				pu_to = {$target->getId ()} AND
				pu_from = {$from->getId ()}
		");

		return $db->query
		("
			INSERT INTO
				n_privatechat_updates
			SET
				pu_from = {$from->getId ()},
				pu_to = {$target->getId ()},
				c_m_id = $msgid,
				pu_date = NOW()
		");
	}

	/**
	* Set all messages to a certain player to "read"
	*/
	public function setPrivateChatUpdateRead (Neuron_GameServer_Player $from, Neuron_GameServer_Player $target)
	{
		$db = Neuron_DB_Database::getInstance ();
		
		$db->query
		("
			UPDATE
				n_privatechat_updates
			SET
				pu_read = '1'
			WHERE
				pu_to = {$target->getId ()} AND
				pu_from = {$from->getId ()}
		");
	}

	public function getPrivateChats (Neuron_GameServer_Player $target, $start = 0, $end = 10)
	{
		$db = Neuron_DB_Database::getInstance ();

		$start = intval ($start);
		$end = intval ($end);

		$data = $db->query
		("
			SELECT
				*,
				UNIX_TIMESTAMP(c_date) AS date
			FROM
				n_privatechat_updates
			LEFT JOIN
				n_chat_messages USING(c_m_id)
			WHERE
				n_privatechat_updates.pu_to = {$target->getId ()}
			ORDER BY
				n_privatechat_updates.pu_date DESC
			LIMIT
				$start, $end
		");

		return $this->getDataFromReader ($data);
	}

	/**
	* If $since is said, return all events since "since".
	* If $since is not said, return the single latest event.
	*
	* ($since is obviously the pu_id)
	*/
	public function getPrivateChatUpdates (Neuron_GameServer_Player $target, $since = null)
	{
		$db = Neuron_DB_Database::getInstance ();

		if (isset ($since))
		{
			$data = $db->query
			("
				SELECT
					*
				FROM
					n_privatechat_updates
				WHERE
					pu_id > ".intval($since)." AND
					pu_to = {$target->getId ()}
			");
		}

		else
		{
			$data = $db->query
			("
				SELECT
					*
				FROM
					n_privatechat_updates
				WHERE
					pu_to = {$target->getId ()}
				ORDER BY
					pu_id DESC
				LIMIT
					1
			");
		}

		return $data;
	}

	public function countUnreadMessages (Neuron_GameServer_Player $target)
	{
		$db = Neuron_DB_Database::getInstance ();

		$data = $db->query
		("
			SELECT
				COUNT(pu_id) AS aantal
			FROM
				n_privatechat_updates
			WHERE
				pu_to = {$target->getId ()}
				AND pu_read = '0'
		");

		return $data[0]['aantal'];
	}

	public function countPrivateMessages (Neuron_GameServer_Player $target)
	{
		$db = Neuron_DB_Database::getInstance ();

		$data = $db->query
		("
			SELECT
				COUNT(pu_id) AS aantal
			FROM
				n_privatechat_updates
			WHERE
				pu_to = {$target->getId ()}
		");

		return $data[0]['aantal'];
	}
}