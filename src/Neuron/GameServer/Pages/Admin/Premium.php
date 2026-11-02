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

class Neuron_GameServer_Pages_Admin_Premium extends Neuron_GameServer_Pages_Admin_Page
{
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
		
		$refund = Neuron_Core_Tools::getInput ('_GET', 'refund', 'int');
		$refund = $refund == 1;
		
		$page = new Neuron_Core_Template ();

		$client = Neuron_GameServer_Credits_AccountsClient::fromConfig ();
		$service = $client ? new Neuron_GameServer_Credits_PurchaseService ($client) : null;
		$skipped = array ();
		
		foreach ($data as $v)
		{
			$player = Neuron_GameServer::getPlayer ($v['plid'], $v);
			
			$date = $player->getPremiumEndDate ();
			$diff = $date - NOW;
			
			$amounts = ceil ($diff / (60*60*24*15));
			$credits = $amounts * PREMIUM_COST_CREDITS;
			
			if ($refund)
			{
				// Only granted purchases with action premium can be refunded.
				$amref = false;
				$found = false;
				if ($service)
				{
					foreach ($service->getPlayerPurchases ($player) as $purchase)
					{
						$action = $purchase['action_data']['action'] ?? '';
						if ($purchase['status'] === 'granted' && ($action === '' || $action === 'premium'))
						{
							$found = true;
							$amref = $service->refundPurchase ($purchase['id'], 'premium account refund') || $amref;
						}
					}
				}
				if (!$found)
				{
					$skipped[] = $player->getDisplayName ();
				}
			}
			else
			{
				$amref = false;
			}
			
			$page->addListValue
			(
				'players',
				array
				(
					'name' => $player->getDisplayName (),
					'enddate' => date ('d m Y H:i:s', $date),
					'credits' => $credits,
					'refunded' => $amref
				)
			);
		}
		
		$out = $page->parse ('pages/admin/premium/premium.phpt');

		if (count ($skipped) > 0)
		{
			$out .= '<p>Skipped (no granted premium purchase to refund): ';
			$out .= htmlspecialchars (implode (', ', $skipped)) . '</p>';
		}

		return $out;
	}
}
?>
