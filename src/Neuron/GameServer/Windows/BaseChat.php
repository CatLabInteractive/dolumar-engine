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

abstract class Neuron_GameServer_Windows_BaseChat
	extends Neuron_GameServer_Windows_Window
{
	const USE_BLOCKING = false;

	private $channelId;

	final public function setSettings ()
	{
		$text = Neuron_Core_Text::__getInstance ();
	
		// Window settings
		$this->setSize ('250px', '250px');

		// Poll once every second
		$this->setAjaxPollSeconds (1);

		// Very important, without this everything will lock
		$this->setPool ('newchat');

		$this->setClassName ('chatwindow');

		$this->setOnFirstload ("scrollDownChat('messagecontainer');");

		$this->setTitle ($this->getTitle ());

		$this->additionalSettings ();

		$this->channel->subscribe ('chat-' . $this->getChannelID ());

		$this->setContainer ('left');
	}

	protected function additionalSettings ()
	{

	}

	/**
	* Get the mapper. Depends on what system we use.
	*/
	private function getMapper ()
	{
		if (self::USE_BLOCKING)
		{
			return Neuron_GameServer_Mappers_BlockingChatMapper::getInstance ();
		}
		else
		{
			return Neuron_GameServer_Mappers_CachedChatMapper::getInstance ();
		}
	}

	protected abstract function getChannelName ();
	protected abstract function getTitle ();

	protected function getHeader ()
	{
		return '<h2>' . $this->getTitle () . '</h2>';
	}

	private function getChannelID ()
	{
		if (!isset ($this->channelId))
		{
			$mapper = $this->getMapper ();

			$channelname = $this->getChannelName ();
			$this->channelId = $mapper->getChannelID ($channelname);
		}

		return $this->channelId;
	}

	private function getOlderMessages ($before = null)
	{
		$mapper = $this->getMapper ();

		$channelId = $this->getChannelID ();
		$messages = $mapper->getMessages ($channelId, 10, null, $before);

		return $this->messagesToDiv ($messages, true);
	}

	private function getMessageContainer ($latest = null, $showOlderLink = false)
	{
		$mapper = $this->getMapper ();

		$channelId = $this->getChannelID ();
		$messages = $mapper->getMessages ($channelId, 20, $latest);

		if (count ($messages) > 0)
		{
			// also update the last id
			if ($mapper->getLastReceivedMessageId ())
			{
				$requestData = $this->getRequestData ();
				$requestData['lastMessage'] = $mapper->getLastReceivedMessageId ();

				$this->updateRequestData ($requestData);
			}

			$this->onReceivedMessages ($mapper);
		}

		return $this->messagesToDiv ($messages, $showOlderLink);
	}

	private function messagesToDiv ($messages, $showOlderLink = false)
	{
		if (count ($messages) > 0)
		{
			// Only offer older messages when there are any left to load
			if ($showOlderLink)
			{
				$showOlderLink = $this->getMapper ()->hasMessagesBefore
				(
					$this->getChannelID (),
					$messages[0]->getId ()
				);
			}

			$page = new Neuron_Core_Template ();
			$page->set ('messages', $messages);
			$page->set ('showOlderMessages', $showOlderLink);
			return $page->parse ('gameserver/chat/messages.phpt');
		}

		return false;
	}
	
	public function getContent ()
	{
		$text = Neuron_Core_Text::getInstance ();	
	
		$player = Neuron_GameServer::getPlayer ();
		if (!$player)
		{
			return $this->throwError ($text->get ('login', 'login', 'account'));
		}

		try
		{
			$channelId = $this->getChannelID ();
		}
		catch (Neuron_Exceptions_DataNotSet $e)
		{
			return '<p>Channel not found.</p>';
		}

		// Register us to this channel
		$mapper = $this->getMapper ();
		$mapper->joinChannel ($player, $channelId);

		// output
		$page = new Neuron_Core_Template ();

		$page->set ('header', $this->getHeader ());

		// Now get the initial messages
		$page->set ('messages', $this->getMessageContainer (null, true));

		return $page->parse ('gameserver/chat/baseview.phpt');
	}

	private function postMessage ($message)
	{
		$user = Neuron_GameServer::getPlayer ();

		$channelId = $this->getChannelID ();

		$mapper = $this->getMapper ();

		$msgid = $mapper->sendMessage ($channelId, $message, $user);

		$this->onPostMessage ($msgid, $mapper);

		$this->channel->refresh ('chat-' . $this->getChannelID ());
	}

	protected function onPostMessage ($msgId, Neuron_GameServer_Mappers_ChatMapper $mapper)
	{

	}

	protected function onReceivedMessages (Neuron_GameServer_Mappers_ChatMapper $mapper)
	{

	}

	public function processInput ()
	{	
		$login = Neuron_Core_Login::getInstance ();
		$text = Neuron_Core_Text::getInstance ();

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

		// Check for input: load older messages
		if (isset ($input['loadprevious']))
		{
			$before = $input['loadprevious'];

			if ($html = $this->getOlderMessages ($before))
			{
				$this->addHtmlToElement ('messagecontainer', $html, 'top');
			}
			else
			{
				$this->addHtmlToElement ('messagecontainer', '<div></div>', 'top');	
			}
		}

		// If not using blocking, we can "safely" process it right now
		if (!self::USE_BLOCKING)
		{
			$this->getRefresh ();
		}
	}

	/*
		Equals: checks if this window is a duplicate of another window	
	*/
	public function equals (Neuron_GameServer_Windows_Window $window)
	{
		if ($window instanceof self)
		{
			// Check the id
			if ($this->getChannelName () == $window->getChannelName ())
			{
				return true;
			}
		}
		return false;
	}

	public function getRefresh ()
	{
		$data = $this->getRequestData ();
		$newStuff = isset ($data['lastMessage']) ? $data['lastMessage'] : null;

		if ($html = $this->getMessageContainer ($newStuff, false))
		{
			$this->addHtmlToElement ('messagecontainer', $html, 'bottom');
		}
	}
}