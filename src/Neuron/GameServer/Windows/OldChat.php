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

class Neuron_GameServer_Windows_OldChat extends Neuron_GameServer_Windows_Window
{
	private $sCacheKey;
	private $objCache;
	
	private $user;
	private $sWhere;
	
	public $channel = 0;
	
	private $aMessages = array ();
	
	const CHAT_GROUP_ALL = 0;
	const CHAT_GROUP_USER = 1;
	const CHAT_GROUP_CLAN = 2;
	
	const CHAT_CLASS_REGULAR = 0;
	const CHAT_CLASS_ME = 1;
	
	const DATE_FORMAT = 'H:i';
	
	const USE_PERSISTENT_CONNECTIONS = true;
	
	public function __construct ()
	{
		$this->sCacheKey = md5 (DB_DATABASE.'_last_chat_message');
		$this->objCache = Neuron_Core_Memcache::getInstance ();
	
		parent::__construct ();
	}

	public function setSettings ()
	{	
		$text = Neuron_Core_Text::__getInstance ();
		$text->setFile ('chat');
		$text->setSection ('chat');
	
		// Window settings
		$this->setSize ('250px', '250px');
		$this->setTitle ($text->get ('chat'));
		
		$this->setAllowOnlyOnce ();
		$this->setAjaxPollSeconds (1);
		
		$this->setClassName ('chat');
		
		// Set different pool for chat
		$this->setPool ('chat');
		
		$this->setMinSize (250, 20);
		
		$this->user = Neuron_GameServer::getPlayer ();
	}
	
	private function switchChannel ($sChannel)
	{
		$this->channel = $this->getChannelID ($sChannel);

		$input = $this->getRequestData ();
		
		$this->updateRequestData 
		(
			array
			(
				'channel' => $this->channel,
				'lastMessage' => $this->getInitialLastMessage (3)
			)
		);
		
		$text = Neuron_Core_Text::getInstance ();
		
		$txt = Neuron_Core_Tools::putIntoText
		(
			$text->get ('channel', 'chat', 'chat'),
			array
			(
				'channel' => $sChannel
			)
		);
		
		// TODO
		// Instead of using this, just sent a new
		// message to yourself.
		
		$this->addHtmlToElement ('chatdiv', '<div class="chat-message system"><div class="text">'.
			'<p>'.$txt.'</p></div></div>', 'bottom');
			
		
		$_SESSION['chat_channel_move'] = true;
	}
	
	private function getDefaultChannel ()
	{
		$text = Neuron_Core_Text::getInstance ();
		return '#' . $text->getCurrentLanguage ();
	}
	
	private function getChannelID ($sChannel)
	{
		$db = Neuron_DB_Database::getInstance ();
		
		$channel = $db->query
		("
			SELECT
				cc_id
			FROM
				chat_channels
			WHERE
				cc_name = '{$db->escape ($sChannel)}'
		");
		
		if (count ($channel) == 0)
		{
			$channelid = $db->query
			("
				INSERT INTO
					chat_channels
				SET
					cc_name = '{$db->escape ($sChannel)}'
			");
		}
		else
		{
			$channelid = $channel[0]['cc_id'];
		}
	
		return $channelid;
	}
	
	private function getInitialLastMessage ($amount = 20)
	{
		$db = Neuron_Core_Database::__getInstance ();
	
		// Only get the last message ID
		$lastRow = $db->select
		(
			'chat',
			array ('msgId'),
			$this->getUserRightWhere (),
			"msgId DESC",
			$amount
		);

		if (count ($lastRow) > 0)
		{
			$last = $lastRow[count ($lastRow) - 1]['msgId'];
		}

		else
		{
			$last = 0;
		}
		
		return $last;
	}
	
	public function getContent ()
	{
		$text = Neuron_Core_Text::__getInstance ();
		$text->setFile ('chat');
		$text->setSection ('chat');
		
		$page = new Neuron_Core_Template ();
		$page->set ('send', $text->get ('send'));
		
		$last = $this->getInitialLastMessage ();
		
		$this->channel = $this->getChannelID ($this->getDefaultChannel ());
		
		$this->updateRequestData (array ('lastMessage' => $last));
		
		return $page->parse ('chat.tpl');
	}

	public function processInput ()
	{	
		$text = Neuron_Core_Text::__getInstance ();
		$text->setFile ('chat');
		$text->setSection ('chat');
		
		$login = Neuron_Core_Login::__getInstance ();
		
		$input = $this->getRequestData ();
		if (!isset ($input['channel']))
		{
			$this->switchChannel ($this->getDefaultChannel ());
		}
		else
		{
			$this->channel = intval ($input['channel']);
		}
		
		$input = $this->getInputData ();
		if ($login->isLogin ())
		{
			if (isset ($input['message']))
			{
				$message = trim ($input['message']);
				
				if (!empty ($message))
				{
					$user = Neuron_GameServer::getPlayer ();
					
					if (!$user->isPlaying ())
					{
						$this->alert ($text->get ('registerfirst'));
					}
					
					elseif (!$user->isEmailVerified ())
					{
						$this->alert ($text->get ('validateEmail_short', 'main', 'account'));
					}
					
					elseif ($user->isBanned ('chat'))
					{
						$end = $user->getBanDuration ('chat');
						
						$duration = Neuron_Core_Tools::getCountdown ($end);
					
						$this->alert 
						(
							Neuron_Core_Tools::putIntoText
							(
								$text->get ('banned'),
								array
								(
									'duration' => $duration
								)
							)
						);
					}
					else
					{
						// Post message
						$this->postMessage ($input['message']);
					}
				}
			}
		}

		else
		{
			$this->alert ($text->get ('login'));
		}
	}
	
	/*
		Post the message
	*/
	private function postMessage ($msg)
	{		
		// Fetch the prefix
		$group = self::CHAT_GROUP_ALL;
		$mtype = self::CHAT_CLASS_REGULAR;
		
		$id = $this->channel;
		
		$msg = trim ($msg);
		
		$shortcuts = array
		(
			'#' => '/clan ',
			'@' => '/msg '
		);
		
		foreach ($shortcuts as $k => $v)
		{
			if (substr ($msg, 0, 1) == $k)
			{
				$msg = $v . substr ($msg, 1);
			}
		}

		if (substr ($msg, 0, 1) == '/')
		{
			$params = explode (' ', substr ($msg, 1));
			$length = strlen ($params[0]) + 1;
			
			switch (strtolower ($params[0]))
			{
				case 'join':
					$channel = isset ($params[1]) ? $params[1] : false;
					
					if ($channel)
					{
						$this->switchChannel ($channel);
					}
					
					return;
				break;
			
				case 'msg':
					// Fetch the user
					$username = isset ($params[1]) ? $params[1] : false;
					$objUser  = isset ($params[1]) ? Dolumar_Players_Player::getFromName ($params[1]) : false;
					
					if ($username && $objUser)
					{
						$length += strlen ($username) + 2;
						
						$group = self::CHAT_GROUP_USER;
						$id = $objUser->getId ();
					}
					
				break;
				
				case 'clan':
					$clan = $this->user->getMainClan ();
					if ($clan)
					{
						$group = self::CHAT_GROUP_CLAN;
						$id = $clan->getId ();
					} 
				break;
				
				case 'me':
					$msg = Neuron_Core_Tools::output_varchar ($this->user->getNickname ()).' '.substr ($msg, $length);
					$length = 0;
					
					$mtype = self::CHAT_CLASS_ME;
				break;
			}
			
			if ($length > 0)
			{
				$msg = substr ($msg, $length);
			}
		}
		
		if (!empty ($msg))
		{
			$mtype = intval ($mtype);
		
			$db = Neuron_DB_Database::getInstance ();
			$biggest = $db->query
			("
				INSERT INTO
					chat
				SET
					msg = '{$db->escape($msg)}',
					datum = '".time()."',
					plid = {$this->user->getId()},
					target_group = '{$db->escape ($group)}',
					target_id = '{$db->escape ($id)}',
					mtype = $mtype
			");
			
			/*
			$biggest = $db->insert
			(
				'chat',
				array
				(
					'msg' => $msg,
					'datum' => time (),
					'plid' => $this->user->getId (),
					'target_group' => $group,
					'target_id' => $id
				)
			);
			*/
			
			$this->objCache->setCache ($this->sCacheKey, $biggest);
		}
	}
	
	/*
		Return the SQL statement
		to get all messages for this player
	*/
	private function getUserRightWhere ()
	{
		if (!isset ($this->sWhere))
		{
			$groups = array ();
			
			$this->sWhere = "(";
		
			if (!$this->user)
			{
				// Only allowed to see the public messages (0/0)
				$groups[self::CHAT_GROUP_ALL] = array (array ($this->channel), false);
			}
			
			else
			{
				// Always show your own message 
				// or don't.
				//$this->sWhere .= 'chat.plid = '.$this->user->getId ().' OR ';
			
				// Allowed to see private messages and clan
				$groups[self::CHAT_GROUP_ALL] = array (array ($this->channel), false);
				$groups[self::CHAT_GROUP_USER] = array (array ($this->user->getId ()), true);
				
				$clans = $this->user->getClans ();
				if (count ($clans) > 0)
				{
					$groups[self::CHAT_GROUP_CLAN] = array (array (), true);
				
					foreach ($clans as $clan)
					{
						$groups[self::CHAT_GROUP_CLAN][0][] = $clan->getId ();
					}
				}
			}
			
			// Build the actual *where*
			foreach ($groups as $k => $v)
			{
				if (is_array ($v) && count ($v) > 0)
				{
					$this->sWhere .= "(chat.target_group = {$k} AND (";
					
					if ($v[1])
					{
						$this->sWhere .= "chat.plid = {$this->user->getId()} OR ";
					}
					
					foreach ($v[0] as $vv)
					{
						$this->sWhere .= "chat.target_id = {$vv} OR ";
					}
					$this->sWhere = substr ($this->sWhere, 0, -4).")) OR ";
				}
				else
				{
					$this->sWhere .= "chat.target_group = {$k} OR ";
				}
			}
			$this->sWhere = substr ($this->sWhere, 0, -4).")";
		}
		
		return $this->sWhere;
	}
	
	/*
		Returns the last 20 messages
	*/
	private function getLastMessages ($newStuff)
	{
		// Let's memcache this!		
		$iLastMessage = $this->objCache->getCache ($this->sCacheKey);
		
		if (isset ($_SESSION['chat_channel_move']) && $_SESSION['chat_channel_move'])
		{
			$_SESSION['chat_channel_move'] = false;
			unset ($_SESSION['chat_channel_move']);
			return false;
		}
		
		// If iLastMessage is smaller then the stuff we want...
		if ($iLastMessage && $iLastMessage <= $newStuff)
		{
			return array ();
		}
	
		$db = Neuron_DB_Database::getInstance ();
		
		// Load the user rights
		$msgs = $db->query
		("
			SELECT
				chat.msgId,
				chat.msg,
				chat.datum,
				chat.plid,
				chat.target_group,
				chat.target_id,
				chat.mtype
			FROM
				chat
			WHERE
				chat.msgId > $newStuff AND 
				{$this->getUserRightWhere()}
			ORDER BY
				chat.msgId ASC
			LIMIT
				20
		");
		
		return $msgs;
	}

	public function getRefresh ()
	{
		$db = Neuron_Core_Database::__getInstance ();
		
		$input = $this->getRequestData ();
		if (!isset ($input['channel']))
		{
			$this->switchChannel ($this->getDefaultChannel ());
		}
		else
		{
			$this->channel = intval ($input['channel']);
		}
		
		$data = $this->getRequestData ();
		$newStuff = $data['lastMessage'];
		
		$maxtime = NOW + 25;
		
		// Check for new messages
		$messages = $this->getLastMessages ($newStuff);		
		if ($messages === false)
		{
			return;
		}
		
		// Close the session (lock)
		session_write_close ();
		
		// Only use persistent connection if we also use memcache
		// Otherwise it would stress out the database
		$usePersistence = self::USE_PERSISTENT_CONNECTIONS && defined ('MEMCACHE_IP');
		
		// Sleep until there are messages
		if ($usePersistence)
		{
			while (count ($messages) == 0 && time () < $maxtime)
			{			
				// Check again
				$messages = $this->getLastMessages ($newStuff);
				
				if ($messages === false)
				{
					return;
				}
			
				// Sleep half a second
				//usleep (500);
				usleep (0.5 * 1000000);
			}
		}
		else
		{
			$messages = $this->getLastMessages ($newStuff);
			
			if ($messages == false)
			{
				return;
			}
		}
		
		// Debug check
		if (count ($messages) == 0)
		{
			return;
		}

		$page = new Neuron_Core_Template ();
		$i = 0;
		
		$login = Neuron_Core_Login::__getInstance ();
		$plid = $login->isLogin () ? $login->getUserId () : 0;
		
		$player = Neuron_GameServer::getPlayer ();
		
		foreach ($messages as $v)
		{
			$sender = Neuron_GameServer::getPlayer ($v['plid']);
			
			$biggest = $v['msgId'];
		
			if ($player && $player->isIgnoring ($v['plid']))
			{
				continue;
			}
		
			$i ++;
		
			$sTarget = null;
			$iTarget = null;
			
			$message = $v['msg'];
			
			/*
				const CHAT_GROUP_ALL = 0;
				const CHAT_GROUP_USER = 1;
				const CHAT_GROUP_CLAN = 2;
			*/
			
			switch ($v['target_group'])
			{
				case self::CHAT_GROUP_USER:
					$sClassname = 'message';
					$sTarget = Dolumar_Players_Player::getFromId ($v['target_id'])->getDisplayName ();
						
					$iTarget = $v['target_id'];
				break;
				
				case self::CHAT_GROUP_CLAN:
					$sClassname = 'clan';
				break;
			
				case self::CHAT_GROUP_ALL:
				default:
				
					switch ($v['mtype'])
					{
						case self::CHAT_CLASS_ME:
							$sClassname = 'all me';
						break;
						
						case self::CHAT_CLASS_REGULAR:
						default:
							$sClassname = 'all';
						break;
					}
				break;
				
				
			}
			
			$page->addListValue
			(
				'msgs',
				array
				(
					'message' => Neuron_Core_Tools::output_text ($message, true, true, false, false),
					'date' => date (self::DATE_FORMAT, $v['datum']),
					'nickname' => $sender->getDisplayName (),
					'plid' => $v['plid'],
					'class' => $sClassname,
					'target' => $sTarget,
					'targetId' => $iTarget, 
					'isMine' => $v['plid'] == $plid
				)
			);
		}
		
		if ($i > 0)
		{
			// Shouldn't ever be true... but well, just to be sure.
			if ($biggest > $this->objCache->getCache ($this->sCacheKey))
			{
				$this->objCache->setCache ($this->sCacheKey, $biggest);
			}
		
			$this->addHtmlToElement ('chatdiv', $page->parse ('chatmsgs.tpl'), 'bottom');
		}
		
		if ($biggest > $newStuff)
		{
			$this->updateRequestData (array ('lastMessage' => $biggest, 'channel' => $this->channel));
		}
	}

}

?>
