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

/**
 * Lists players with premium left and (admin, POST with token) refunds it:
 * per player the newest ceil(days left / 15) granted premium purchases, one
 * purchase being one 15-day block of PREMIUM_COST_CREDITS.
 */
class Neuron_GameServer_Pages_Admin_Premium extends Neuron_GameServer_Pages_Admin_Page
{
	const BLOCK_SECONDS = 1296000; // 15 days, one premium purchase
	const TOKEN_KEY = 'admin_premium_refund_token';

	/**
	 * Premium blocks (purchases) still running at $now.
	 */
	public static function blocksLeft (int $endDate, int $now): int
	{
		return $endDate > $now ? (int) ceil (($endDate - $now) / self::BLOCK_SECONDS) : 0;
	}

	/**
	 * Anti-forgery token for the refund form, kept in the session.
	 */
	public static function token (bool $renew = false): string
	{
		if ($renew || !isset ($_SESSION[self::TOKEN_KEY]) || !is_string ($_SESSION[self::TOKEN_KEY]) || strlen ($_SESSION[self::TOKEN_KEY]) < 32)
		{
			$_SESSION[self::TOKEN_KEY] = bin2hex (random_bytes (16));
		}
		return $_SESSION[self::TOKEN_KEY];
	}

	/**
	 * Only a POST carrying refund=1 and the session's token refunds.
	 */
	public static function isRefundRequest (string $method, array $post, string $token): bool
	{
		return strtoupper ($method) === 'POST'
			&& isset ($post['refund'], $post['token'])
			&& (string) $post['refund'] === '1'
			&& is_string ($post['token'])
			&& $token !== ''
			&& hash_equals ($token, $post['token']);
	}

	public function getBody ()
	{
		$db = Neuron_DB_Database::getInstance ();
		
		$data = $db->query
		("
			SELECT
				*
			FROM
				n_players
			WHERE
				premiumEndDate > FROM_UNIXTIME('".NOW."')
		");

		$myself = Neuron_GameServer::getPlayer ();
		$isAdmin = $myself && $myself->isAdmin ();

		$token = self::token ();
		$refund = $isAdmin && self::isRefundRequest ($_SERVER['REQUEST_METHOD'] ?? 'GET', $_POST, $token);
		if ($refund)
		{
			// One refund per token: a resubmitted form does nothing.
			$token = self::token (true);
		}
		
		$page = new Neuron_Core_Template ();
		$page->set ('canRefund', $isAdmin);
		$page->set ('token', $token);
		$page->set ('formAction', $this->getUrl ('premium', array ()));
		$page->set ('didRefund', $refund);

		$client = Neuron_GameServer_Credits_AccountsClient::fromConfig ();
		$service = $client ? new Neuron_GameServer_Credits_PurchaseService ($client) : null;
		$skipped = array ();
		$partial = array ();
		
		foreach ($data as $v)
		{
			$player = Neuron_GameServer::getPlayer ($v['plid'], $v);
			
			$date = $player->getPremiumEndDate ();
			$blocks = self::blocksLeft ((int) $date, (int) NOW);
			$credits = $blocks * PREMIUM_COST_CREDITS;

			$result = null;
			if ($refund)
			{
				$result = $service
					? $service->refundNewestPremium ($player, $blocks, 'premium account refund')
					: array ('refunded' => 0, 'pending' => 0, 'failed' => 0, 'purchases' => 0);

				if ($result['purchases'] === 0)
				{
					$skipped[] = $player->getDisplayName ();
				}
				elseif ($result['purchases'] < $blocks)
				{
					$partial[] = $player->getDisplayName ();
				}
			}
			
			$page->addListValue
			(
				'players',
				array
				(
					'name' => $player->getDisplayName (),
					'enddate' => date ('d m Y H:i:s', $date),
					'credits' => $credits,
					'refunded' => $result ? $result['refunded'] : null,
					'refund_pending' => $result ? $result['pending'] : null,
					'refund_failed' => $result ? $result['failed'] : null
				)
			);
		}
		
		$out = $page->parse ('pages/admin/premium/premium.phpt');

		if (count ($skipped) > 0)
		{
			$out .= '<p>Skipped (no granted premium purchase to refund): ';
			$out .= htmlspecialchars (implode (', ', $skipped)) . '</p>';
		}

		if (count ($partial) > 0)
		{
			$out .= '<p>Fewer granted premium purchases than premium left (the rest was bought on the old credits service): ';
			$out .= htmlspecialchars (implode (', ', $partial)) . '</p>';
		}

		return $out;
	}
}
