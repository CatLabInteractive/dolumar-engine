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
	Storage for Web Push: the browsers a player subscribed (n_push_subscriptions)
	and the outbox the cron job flushes (n_push_queue).
*/
class Neuron_GameServer_Mappers_PushMapper
{
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

	/*
		Store a subscription. A browser has one endpoint; when another player
		logs in on the same browser and subscribes, the endpoint moves to them.
	*/
	public function addSubscription ($playerId, $endpoint, $p256dh, $auth, $userAgent = '')
	{
		$db = Neuron_DB_Database::getInstance ();

		$playerId = intval ($playerId);
		$userAgent = mb_substr ((string) $userAgent, 0, 255);

		$db->query
		("
			INSERT INTO
				n_push_subscriptions
			SET
				ps_plid = {$playerId},
				ps_endpoint = '{$db->escape ($endpoint)}',
				ps_p256dh = '{$db->escape ($p256dh)}',
				ps_auth = '{$db->escape ($auth)}',
				ps_useragent = '{$db->escape ($userAgent)}',
				ps_created = NOW()
			ON DUPLICATE KEY UPDATE
				ps_plid = {$playerId},
				ps_p256dh = '{$db->escape ($p256dh)}',
				ps_auth = '{$db->escape ($auth)}',
				ps_useragent = '{$db->escape ($userAgent)}'
		");
	}

	public function removeSubscription ($playerId, $endpoint)
	{
		$db = Neuron_DB_Database::getInstance ();

		$playerId = intval ($playerId);

		$db->query
		("
			DELETE FROM
				n_push_subscriptions
			WHERE
				ps_plid = {$playerId} AND
				ps_endpoint = '{$db->escape ($endpoint)}'
		");
	}

	/*
		Remove an endpoint for whoever owns it: the push service said it is gone.
	*/
	public function removeEndpoint ($endpoint)
	{
		$db = Neuron_DB_Database::getInstance ();

		$db->query
		("
			DELETE FROM
				n_push_subscriptions
			WHERE
				ps_endpoint = '{$db->escape ($endpoint)}'
		");
	}

	public function hasSubscriptions ($playerId)
	{
		$db = Neuron_DB_Database::getInstance ();

		$playerId = intval ($playerId);

		$data = $db->query
		("
			SELECT
				ps_id
			FROM
				n_push_subscriptions
			WHERE
				ps_plid = {$playerId}
			LIMIT
				1
		");

		return count ($data) > 0;
	}

	/*
		@return array of rows with id, plid, endpoint, p256dh, auth.
	*/
	public function getSubscriptions (array $playerIds)
	{
		$ids = array_unique (array_map ('intval', $playerIds));
		if (count ($ids) === 0)
		{
			return array ();
		}

		$db = Neuron_DB_Database::getInstance ();

		$data = $db->query
		("
			SELECT
				ps_id,
				ps_plid,
				ps_endpoint,
				ps_p256dh,
				ps_auth
			FROM
				n_push_subscriptions
			WHERE
				ps_plid IN (" . implode (',', $ids) . ")
		");

		$out = array ();
		foreach ($data as $v)
		{
			$out[] = array
			(
				'id' => intval ($v['ps_id']),
				'plid' => intval ($v['ps_plid']),
				'endpoint' => $v['ps_endpoint'],
				'p256dh' => $v['ps_p256dh'],
				'auth' => $v['ps_auth']
			);
		}
		return $out;
	}

	public function markSuccess ($endpoint)
	{
		$db = Neuron_DB_Database::getInstance ();

		$db->query
		("
			UPDATE
				n_push_subscriptions
			SET
				ps_lastsuccess = NOW()
			WHERE
				ps_endpoint = '{$db->escape ($endpoint)}'
		");
	}

	public function enqueue ($playerId, $payload)
	{
		$db = Neuron_DB_Database::getInstance ();

		$playerId = intval ($playerId);

		$db->query
		("
			INSERT INTO
				n_push_queue
			SET
				pn_plid = {$playerId},
				pn_payload = '{$db->escape ($payload)}',
				pn_created = NOW()
		");
	}

	/*
		Take (fetch and delete) the oldest queued messages.
		Taking before sending means a crash can lose a notification but
		never sends the same one twice.

		@return array of rows with id, plid, payload, created (unix time).
	*/
	public function takeQueue ($limit)
	{
		$db = Neuron_DB_Database::getInstance ();

		$limit = max (1, intval ($limit));

		$data = $db->query
		("
			SELECT
				pn_id,
				pn_plid,
				pn_payload,
				UNIX_TIMESTAMP(pn_created) AS created
			FROM
				n_push_queue
			ORDER BY
				pn_id ASC
			LIMIT
				{$limit}
		");

		$out = array ();
		$ids = array ();
		foreach ($data as $v)
		{
			$ids[] = intval ($v['pn_id']);
			$out[] = array
			(
				'id' => intval ($v['pn_id']),
				'plid' => intval ($v['pn_plid']),
				'payload' => $v['pn_payload'],
				'created' => intval ($v['created'])
			);
		}

		if (count ($ids) > 0)
		{
			$db->query
			("
				DELETE FROM
					n_push_queue
				WHERE
					pn_id IN (" . implode (',', $ids) . ")
			");
		}

		return $out;
	}
}
