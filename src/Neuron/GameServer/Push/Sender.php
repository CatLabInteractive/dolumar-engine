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

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/*
	Builds push payloads and delivers the queued ones.

	Players get notifications through Neuron_GameServer_Player::sendPushNotification(),
	which only queues. The game's cron calls flushQueue() every minute, so a
	web request never waits on a push service.
*/
class Neuron_GameServer_Push_Sender
{
	// A notification that sat in the queue longer than this is stale.
	const MAX_AGE = 3600;

	// How long a push service keeps trying to reach an offline browser.
	const TTL = 6 * 3600;

	const MAX_BODY_LENGTH = 300;

	/** @var WebPush|null */
	private $webPush;

	public function __construct (?WebPush $webPush = null)
	{
		$this->webPush = $webPush;
	}

	/*
		The JSON the service worker receives (assets/javascript/push/sw.js).
	*/
	public static function buildPayload ($title, $body, $url, $tag = null, $icon = null)
	{
		$body = trim (preg_replace ('/\s+/u', ' ', (string) $body));
		if (mb_strlen ($body) > self::MAX_BODY_LENGTH)
		{
			$body = mb_substr ($body, 0, self::MAX_BODY_LENGTH - 1) . '…';
		}

		$payload = array
		(
			'title' => (string) $title,
			'body' => $body,
			'url' => (string) $url
		);

		if ($tag !== null && $tag !== '')
		{
			$payload['tag'] = (string) $tag;
		}

		if ($icon !== null && $icon !== '')
		{
			$payload['icon'] = (string) $icon;
		}

		return json_encode ($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	/*
		Send what is in the queue.
		@return array with counts: queued, stale, sent, expired, failed.
	*/
	public function flushQueue ($limit = 500, $now = null)
	{
		$stats = array ('queued' => 0, 'stale' => 0, 'sent' => 0, 'expired' => 0, 'failed' => 0);

		if (!Neuron_GameServer_Push_Config::isEnabled ())
		{
			return $stats;
		}

		$now = $now === null ? time () : intval ($now);
		$mapper = Neuron_GameServer_Mappers_PushMapper::getInstance ();

		$items = $mapper->takeQueue ($limit);
		$stats['queued'] = count ($items);

		$fresh = array ();
		foreach ($items as $item)
		{
			if ($item['created'] < $now - self::MAX_AGE)
			{
				$stats['stale'] ++;
			}
			else
			{
				$fresh[] = $item;
			}
		}

		if (count ($fresh) === 0)
		{
			return $stats;
		}

		$playerIds = array ();
		foreach ($fresh as $item)
		{
			$playerIds[] = $item['plid'];
		}

		$byPlayer = array ();
		foreach ($mapper->getSubscriptions ($playerIds) as $subscription)
		{
			$byPlayer[$subscription['plid']][] = $subscription;
		}

		$webPush = $this->getWebPush ();
		$pending = 0;

		foreach ($fresh as $item)
		{
			if (!isset ($byPlayer[$item['plid']]))
			{
				continue;
			}

			foreach ($byPlayer[$item['plid']] as $subscription)
			{
				$webPush->queueNotification
				(
					Subscription::create (array
					(
						'endpoint' => $subscription['endpoint'],
						'keys' => array
						(
							'p256dh' => $subscription['p256dh'],
							'auth' => $subscription['auth']
						)
					)),
					$item['payload'],
					array ('TTL' => self::TTL, 'urgency' => 'high')
				);
				$pending ++;
			}
		}

		if ($pending === 0)
		{
			return $stats;
		}

		foreach ($webPush->flush () as $report)
		{
			$endpoint = $report->getEndpoint ();

			if ($report->isSuccess ())
			{
				$stats['sent'] ++;
				$mapper->markSuccess ($endpoint);
			}
			elseif ($report->isSubscriptionExpired ())
			{
				$stats['expired'] ++;
				$mapper->removeEndpoint ($endpoint);
			}
			else
			{
				$stats['failed'] ++;
			}
		}

		return $stats;
	}

	private function getWebPush ()
	{
		if (!isset ($this->webPush))
		{
			$this->webPush = new WebPush (Neuron_GameServer_Push_Config::getAuth ());
			$this->webPush->setReuseVAPIDHeaders (true);
		}
		return $this->webPush;
	}
}
