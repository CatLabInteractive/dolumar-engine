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

class Neuron_GameServer_Pages_Admin_User extends Neuron_GameServer_Pages_Admin_Page
{
	public function getBody ()
	{
		$plid = Neuron_Core_Tools::getInput ('_GET', 'plid', 'int');
		$id = Neuron_Core_Tools::getInput ('_GET', 'id', 'int', $plid);
		$objUser = Neuron_GameServer::getPlayer ($id);
	
		/*
			Check for actions
		*/
		$action = Neuron_Core_Tools::getInput ('_GET', 'action', 'varchar');
		
		switch ($action)
		{
			case 'reset':
				return $this->getResetPlayer ($objUser); 
			break;
		
			default:
				return $this->getUserOverview ($objUser);
			break;
		}
	}
	
	private function getUserOverview (Neuron_GameServer_Player $objUser)
	{
		$admin = Neuron_GameServer::getPlayer ();
	
		$page = new Neuron_Core_Template ();
		
		$page->set ('username', Neuron_Core_Tools::output_varchar ($objUser->getName ()));		
		$page->set ('email', $objUser->getEmail ());
		$page->set ('registration', date ('d/m/Y H:i:s', $objUser->getCreationDate ()));
		$page->set ('lastrefresh', date ('d/m/Y H:i:s', $objUser->getLastRefresh ()));
		$page->set ('premiumend', date ('d/m/Y H:i:s', $objUser->getPremiumEndDate ()));
		
		foreach ($objUser->getVillages () as $v)
		{
			$page->addListValue
			(
				'villages',
				array
				(
					'village' => Neuron_Core_Tools::output_varchar ($v->getName ()),
					'url' => ABSOLUTE_URL.'#'.implode (',', $v->buildings->getTownCenterLocation ())
				)
			);
		}
		
		$page->set ('reset_url', $this->getUrl ('user', array ('id' => $objUser->getId (), 'action' => 'reset')));
		
		if ($admin->isModerator ())
		{
			$page->set ('logs_url', $this->getUrl ('gamelogs', array ('players' => $objUser->getId ())));
			$page->set ('contact_url', $this->getUrl ('messages', array ('view' => 'write', 'target' => $objUser->getNickname ())));		
		}
		
		if ($admin->isAdmin ())
		{
			if (isset ($_POST['admin_status']))
			{
				$status = Neuron_Core_Tools::getInput ('_POST', 'admin_status', 'int');
				$objUser->setAdminStatus ($status);
			}
		
			$page->set ('admin_action', $this->getUrl ('user', array ('id' => $objUser->getId ())));
			
			$modes = array ();
			foreach ($objUser->getAdminModes () as $k => $v)
			{
				if ($k < $admin->getAdminStatus ())
				{
					$modes[$k] = $v;
				}
			}
			$page->set ('admin_modes', $modes);
		}
		
		$page->set ('adminmode', $objUser->getAdminStatus ());
		$page->set ('adminmodestring', $objUser->getAdminStatusString ());
		
		$page->set 
		(
			'banoptions', 
			array
			(
				300 => '5 minutes',
				1800 => '30 minutes',
				3600 => '1 hour',
				21600 => '6 hours',
				86400 => '1 day',
				604800 => '1 week',
				1209600 => '2 weeks',
				2678400 => '1 month',
				31536000 => '1 year'
			)
		);
		
		$this->addBans ($page, $objUser);
		
		$data = $this->getModeratorHistory ($objUser);
		foreach ($data as $v)
		{
			if (!$v['isProcessed'])
				$status = 'pending';
			elseif ($v['isExecuted'])
				$status = 'approved';
			else
				$status = 'declined';
			
			$reason = Neuron_Core_Tools::output_text ($v['reason']);
			
			$rcheck = strip_tags ($reason);
			$rcheck = trim ($rcheck);
		
			$page->addListValue
			(
				'history',
				array
				(
					'date' => date (DATETIME, $v['date']),
					'action' => $v['action'],
					'reason' => !empty ($rcheck) ? $reason : null,
					'admin' => $v['admin']->getDisplayName (),
					'isExecuted' => $v['isExecuted'],
					'isProcessed' => $v['isProcessed'],
					'status' => $status
				)
			);
		}
		
		$openids = $objUser->getOpenIDs ();
		
		foreach ($openids as $v)
		{
			$page->addListValue
			(
				'openids',
				array
				(
					'url' => $v
				)
			);
		}

		if (Neuron_GameServer::getPlayer ()->getAdminStatus () >= 9)
		{
			$page->set ('refundcredits', $this->getUrl ('user', array ('id' => $objUser->getId ())));

			$credits = Neuron_Core_Tools::getInput ('_POST', 'refundcredits', 'int');
			$reason = Neuron_Core_Tools::getInput ('_POST', 'refundreason', 'varchar');

			if ($credits && $reason)
			{
				if ($this->refundCredits ($objUser, $credits, $reason))
				{
					$page->set ('refunddone', true);
				}
			}
		}
		
		return $page->parse ('pages/admin/user/overview.phpt');
	}

	private function refundCredits (Neuron_GameServer_Player $player, $credits, $reason)
	{
		return $player->refundCredits ($credits, $reason, 'manual');
	}
	
	private function getModeratorHistory (Neuron_GameServer_Player $objUser)
	{
		$db = Neuron_DB_Database::getInstance ();
		
		$data = $db->query
		("
			SELECT
				*,
				UNIX_TIMESTAMP(ma_date) AS datum
			FROM
				n_mod_actions
			WHERE
				ma_target = {$objUser->getId ()}
			ORDER BY
				ma_date DESC
		");
		
		$out = array ();
		
		foreach ($data as $v)
		{
			$data = json_decode ($v['ma_data'], true);
		
			$out[] = array
			(
				'date' => $v['datum'],
				'data' => $data,
				'admin' => Neuron_GameServer::getPlayer ($v['ma_plid']),
				'reason' => $v['ma_reason'],
				'action' => $this->getHistoryText ($v['ma_action'], $data),
				'isExecuted' => $v['ma_executed'],
				'isProcessed' => $v['ma_processed']
			);
		}
		
		return $out;
	}
	
	private function getHistoryText ($action, $data)
	{
		$name = $action;
	
		switch ($action)
		{
			case 'unban':
			case 'ban':
				$name = $data['channel'] . ' ' . $name;
			break;
		}
		
		// I want to go again, mommy!
		switch ($action)
		{
			case 'ban':
				$name .= ' ' . Neuron_Core_Tools::getDurationText ($data['duration']);
			break;
		}
		
		return $name;
	}
	
	private function addBans ($page, $objUser)
	{
		// Perform thze action
		$duration = Neuron_Core_Tools::getInput ('_POST', 'duration', 'int');
		$action = Neuron_Core_Tools::getInput ('_POST', 'action', 'varchar');
		$channel = Neuron_Core_Tools::getInput ('_GET', 'channel', 'varchar');
		
		$reason = Neuron_Core_Tools::getInput ('_POST', 'reason', 'varchar');
		
		if ($channel && $action)
		{
			switch ($action)
			{
				case 'ban':
					$objUser->ban ($channel, $duration);
					
					$this->addModeratorAction 
					(
						'ban', 
						array
						(
							'channel' => $channel,
							'duration' => $duration
						), 
						$reason, 
						$objUser, 
						true
					);
				break;
				
				case 'unban':
					$objUser->unban ($channel);
					
					$this->addModeratorAction 
					(
						'unban', 
						array
						(
							'channel' => $channel,
							'duration' => $duration
						), 
						$reason, 
						$objUser, 
						true
					);
				break;
			}
		}
	
		$channels = array
		(
			'chat' => 'Chat channel',
			'messages' => 'Messages'
		);
		
		$bans = array ();
		
		foreach ($channels as $k => $v)
		{
			$end = $objUser->getBanDuration ($k);
			
			if ($end > time ())
				$duration = Neuron_Core_Tools::getCountdown ($end);
			else
				$duration = '-';
		
			$bans[] = array
			(
				'id' => $k,
				'channel' => $v,
				'url' => $this->getUrl 
				(
					'user', 
					array 
					(
						'id' => $objUser->getId (),
						'channel' => $k
					)
				),
				'duration' => $duration
			);
		}
		
		$page->set ('bans', $bans);
	}
	
	private function getResetPlayer ($objUser)
	{
		$seckey = Neuron_Core_Tools::getInput ('_POST', 'seckey', 'varchar');
		$message = Neuron_Core_Tools::getInput ('_POST', 'message', 'varchar');
		$title = Neuron_Core_Tools::getInput ('_POST', 'title', 'varchar');
		
		// Fetch my account
		$myAccount = Neuron_GameServer::getPlayer ();
		
		$sKey = 'kick_'.$objUser->getId ();
		
		$page = new Neuron_Core_Template ();
		
		$page->set ('username', Neuron_Core_Tools::output_varchar ($objUser->getName ()));
		
		if ($sKey == $seckey && $title && $message)
		{
			// Send a mail to this user
			$this->addModeratorAction 
			(
				'reset',
				array
				(
					'plid' => $objUser->getId (),
					'title' => $title,
					'message' => $message
				),
				Neuron_Core_Tools::getInput ('_POST', 'reason', 'varchar'),
				$objUser
			);
			
			/*
			customMail ($objUser->getEmail (), $title, $message);
			$objUser->execResetAccount ();
			*/
			
			$page->set ('isDone', true);
		}
		else
		{
			$page->set ('isDone', false);
		}
		
		$page->set ('action', $this->getUrl ('user', array ('id' => $objUser->getId (), 'action' => 'reset')));
		$page->set ('seckey', $sKey);
		
		$page->set ('myname', Neuron_Core_Tools::output_varchar ($myAccount->getName ()));
		
		return $page->parse ('pages/admin/user/kick.phpt');
	}
}
?>
