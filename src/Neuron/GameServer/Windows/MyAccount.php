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

class Neuron_GameServer_Windows_MyAccount extends Neuron_GameServer_Windows_Window
{
	public function setSettings ()
	{
		$text = Neuron_Core_Text::__getInstance ();
	
		// Window settings
		$this->setSize ('250px', '250px');
		$this->setTitle ($text->get ('myAccount', 'menu', 'main'));
		
		$this->setPosition (15, 40);
		
		$this->setAllowOnlyOnce ();
	}
	
	final public function getContent ($ignoreInitialLoad = true)
	{
		$login = Neuron_Core_Login::__getInstance ();
		$server = Neuron_GameServer::getServer();

		if (!$server->isOnline ())
		{
			return $this->getClosedGame ($server);
		}
		else
		{		
			// Don't show window unless actually wanted
			$data = $this->getRequestData ();
			
			if ($login->isLogin ())
			{
				$me = Neuron_GameServer::getPlayer ();

				// isPlaying: Did this player select race etc.
				if ($me->isPlaying ())
				{	
					if
					(
						$ignoreInitialLoad
						&& isset ($data['load'])
						&& strpos ($data['load'], 'autoload') !== false
					)
					{
						return false;
					}
					else
					{
						$html = $this->showMyAccount ();

						if (isset ($_SESSION['just_registered']) && $_SESSION['just_registered'])
						{
							$html .= '<iframe src="'.htmlentities ($me->getTrackerUrl ('registration')).'" width="1" '.
								'height="1" border="0" class="hidden-iframe"></iframe>';
						}
					}
				}

				// Hide for openAuth applications that have their own "greetings".
				/*
				elseif
				(
					isset ($_SESSION['hideMyAccount'])
					&& $_SESSION['hideMyAccount']
					&& strpos ($data['load'], 'autoload') !== false
				)
				{
					$html = false;
				}
				*/
				
				else
				{
					// Select a nickname
					//$username = $me->getNickname ();
					if (!$me->isNicknameSet ())
					{
						$html = $this->chooseNickname ();
					}
					else
					{
						$html = $this->getPlayerInitialization ();
						$_SESSION['just_registered'] = true;
					}
				}
				
				return $html;
			}
			
			else {
				return $this->showLoginForm ();
			}
		}
	}

	private function chooseNickname ()
	{
		$db = Neuron_Core_Database::__getInstance ();
		
		// Check for input
		$data = $this->getInputData ();

		$login = Neuron_Core_Login::__getInstance ();

		$error = '';
		$username = '';
		$showForm = true;

		$me = Neuron_GameServer::getPlayer ();
		
		if (isset ($data['username']))
		{
			$username = $data['username'];

			if ($me->setNickname ($username))
			{
				return $this->getPlayerInitialization (true);
			}
			else
			{
				$error = $me->getError ();
			}
		}

		if ($showForm)
		{
			$text = Neuron_Core_Text::__getInstance ();
			$text->setFile ('account');
			$text->setSection ('nickname');

			$page = new Neuron_Core_Template ();

			if (!empty ($error))
			{
				$page->set ('error', $text->get ($error));
			}
			
			if (empty ($username) && isset ($_SESSION['openid_nickname']))
			{
				$username = $_SESSION['openid_nickname'];
			}

			$page->set ('chooseName', $text->get ('chooseName'));
			$page->set ('welcome', $text->get ('welcome'));
			$page->set ('username', $text->get ('username'));
			$page->set ('submit', $text->get ('submit'));

			$page->set ('username_value', Neuron_Core_Tools::output_form ($username));

			return $page->parse ('gameserver/account/chooseNickname.tpl');
		}
	}
	
	private function showLoginForm ($error = false)
	{
		// 3rd party login: prevent normal logins
		if (defined ('NOLOGIN_REDIRECT'))
		{
			return '<p>Please go to <a href="' . NOLOGIN_REDIRECT . '">' 
				. NOLOGIN_REDIRECT . '</a> in order to login.</p>';
		}

		if (defined('OPENID_CONNECT_AUTHORIZE_URL') && OPENID_CONNECT_AUTHORIZE_URL) {
			return '<p>Please click <a href="' . Neuron_URLBuilder::getInstance()->getRawURL('oauth2/login', []) . '">here</a> to login.</p>';
		}

		$text = Neuron_Core_Text::__getInstance ();
		$text->setFile ('account');
		$text->setSection ('login');
	
		$page = new Neuron_Core_Template ();
		
		if ($error)
		{
			$page->setVariable ('error', $text->get ($error, 'errors'));
		}
		
		// Set window id
		$page->set ('welcome', $text->get ('welcome'));
		$page->set ('login', $text->get ('login'));
		$page->set ('username', $text->get ('username'));
		$page->set ('password', $text->get ('password'));
		$page->set ('submit', $text->get ('submit'));
		$page->set ('login_title', $text->get ('login_title'));
		
		$page->set ('register', $text->getClickTo ($text->get ('toRegister')));
		$page->set ('request', $text->getClickTo ($text->get ('toRequest')));
		
		return $page->parse ('gameserver/account/login.tpl');
	}
	
	private function canChangePassword ()
	{
		return !isset ($_SESSION['neuron_openid_identity']);
	}
	
	private function canSetEmail ()
	{
		return !isset ($_SESSION['neuron_openid_identity']);
	}
	
	protected function showMyAccount ()
	{
		$me = Neuron_GameServer::getPlayer ();
		
		$input = $this->getInputData ();

		if 
		(
			!$me->isEmailCertified () && 
			isset ($input['formAction']) && 
			$input['formAction'] == 'email'
		)
		{
			return $this->getEmailCertification ();
		}
		elseif (isset ($input['action']) && $input['action'] == 'changePassword')
		{
			return $this->getChangePassword ();
		}
		elseif (isset ($input['action']) && $input['action'] == 'resetAccount')
		{
			return $this->getResetAccount ();
		}
		else
		{
			$text = Neuron_Core_Text::__getInstance ();
			$text->setFile ('account');
			$text->setSection ('myAccount');
		
			$page = new Neuron_Core_Template ();
			
			$page->set ('welcome', Neuron_Core_Tools::putIntoText
			(
				$text->get ('welcome'),
				array
				(
					Neuron_Core_Tools::output_varchar ($me->getNickname ())
				)
			));
			
			$page->set ('todo', $text->get ('todo'));
			$page->set ('logout', $text->get ('logout'));
			$page->set ('layout', $me->getPreference ('layout', Neuron_GameServer::LAYOUT_DEFAULT));
			
			if ($this->canChangePassword ())
			{
				$page->set ('changePassword', true);
			}
			
			if (!$me->isEmailCertified () && $this->canSetEmail ())
			{
				$page->set ('setEmail', $text->get ('setEmail'));
			}
			
			return $page->parse ('gameserver/account/myAccount.tpl');
		}
	}
	
	/*
		Returns the HTML for changing password
	*/
	private function getChangePassword ()
	{
		$input = $this->getInputData ();
		$login = Neuron_Core_Login::__getInstance ();
	
		$page = new Neuron_Core_Template ();
		
		// Check for input
		$pass1 = isset ($input['newPassword1']) ? $input['newPassword1'] : null;
		$pass2 = isset ($input['newPassword2']) ? $input['newPassword2'] : null;
		
		if (!empty ($pass1) && $pass1 && $pass2)
		{
			if ($pass1 == $pass2)
			{
				// Try to change the password
				if ($login->doChangePassword ($pass1))
				{
					$page->set ('success', 'changed');
				}
				else
				{
					$page->set ('error', $login->getError ());
				}
			}
			else
			{
				$page->set ('error', 'password_mismatch');
			}
		}
		
		return $page->parse ('gameserver/account/changePass.phpt');
	}

	/*
		Returns the HTMT for an account reset.
	*/
	private function getResetAccount ()
	{
		$text = Neuron_Core_Text::__getInstance ();
	
		$input = $this->getInputData ();
		$myself = Neuron_GameServer::getPlayer ();
		
		$page = new Neuron_Core_Template ();
		
		// This only works if an E-mail is set.
		if (!$myself->isEmailCertified () && $this->canSetEmail ())
		{
			$page->set ('noEmail', true);
		}
		
		// E-mail address is certified: account reset can only go trough mail.		
		elseif (isset ($input['confirm']) && $input['confirm'] == 'true' && $myself->isEmailCertified ())
		{
			$myself->startResetAccount ();
			$page->set ('success', 'done');
		}
		
		// Email address is not certified (and cannot be certified)
		// That means the user can reset account without confirmation.
		elseif (isset ($input['confirm']) && $input['confirm'] == 'true')
		{
			$myself->execResetAccount ();
		
			$page->set ('success', 'instadone');
			reloadEverything ();
		}
		
		return $page->parse ('gameserver/account/resetAccount.phpt');
	}
	
	public function getEmailCertification ()
	{
		$me = Neuron_GameServer::getPlayer ();
		
		$text = Neuron_Core_Text::__getInstance ();
		$text->setFile ('account');
		$text->setSection ('choosemail');

		$page = new Neuron_Core_Template ();

		$page->set ('welcome', Neuron_Core_Tools::putIntoText
		(
			$text->get ('welcome'),
			array
			(
				Neuron_Core_Tools::output_varchar ($me->getNickname ())
			)
		));
		
		$page->set ('return', $text->getClickTo ($text->get ('toReturn')));
		
		$input = $this->getInputData ();

		// Set e-mail (certification required)			
		if (isset ($input['email']))
		{
			if (!$me->setEmail ($input['email']))
			{
				$page->set ('error', $text->get ($me->getError ()));
			}
		}
	
		if ($me->isEmailSet ())
		{	
			if (isset ($input['action']) && $input['action'] == 'resend')
			{
				$me->sendCertificationMail ();
				return '<p>Your verification email is sent.</p>';
			}
		
			$page->set ('section', 'notcertified');
			
			$page->set ('email_title', $text->get ('email_title'));
			$page->set ('email_about', $text->get ('certification'));
			$page->set ('cert_again', $text->getClickTo ($text->get ('toCertAgain')));
			
			return $page->parse ('gameserver/account/myAccount_email.tpl');
		}
		else
		{
			$page->set ('section', 'choosemail');

			$page->set ('email_title', $text->get ('email_title'));
			$page->set ('email', $text->get ('email'));
			$page->set ('submit', $text->get ('submit'));
			$page->set ('about', $text->get ('about'));

			return $page->parse ('gameserver/account/myAccount_email.tpl');
		}
	}

	public function getRefresh () {}
	
	public function processInput ()
	{	
		$login = Neuron_Core_Login::__getInstance ();
		$data = $this->getInputData ();
		
		if ($login->isLogin ())
		{
			$me = Neuron_GameServer::getPlayer ();
			if ($me->isPlaying ())
			{
				$this->processIngameInput ($data);
			}
			
			// Player didn't select location & race yet.
			else
			{
				$username = $me->getNickname ();
				if (empty ($username))
				{
					$this->updateContent ($this->chooseNickname ());
				}
				
				else
				{
					$this->updateContent ();
				}
			}
		}
		
		elseif (isset ($data['action']) && $data['action'] == 'openid')
		{
			return $this->updateContent ($this->getOpenId ());
		}
		
		elseif (isset ($data['username']) && isset ($data['password'])) 
		{
			// Login form			
			if ($login->login ($data['username'], $data['password']))
			{
				$this->onLogin ();
			}
			
			else 
			{
				// Wrong password: show form again + warnings
				$this->updateContent ($this->showLoginForm ($login->getWarnings ()));
			}
		}
		else
		{
			$this->updateContent ();
		}
	}
	
	private function processIngameInput ($data)
	{
		if
		(
			isset ($data['action']) &&
			$data['action'] === 'layout' &&
			isset ($data['layout']) &&
			is_string ($data['layout']) &&
			in_array ($data['layout'], Neuron_GameServer::LAYOUT_VALUES, true)
		)
		{
			Neuron_GameServer::getPlayer ()->setPreference ('layout', $data['layout']);
			return $this->reloadWindow ();
		}

		return $this->updateContent ($this->showMyAccount ());
	}

	private function getClosedGame ($server)
	{
		$page = new Neuron_Core_Template ();
		$page->setTextSection ('serverOffline', 'account');
		
		$page->set ('error', $server->getError ());
		
		return $page->parse ('gameserver/account/serverOffline.phpt');
	}
	
	protected function onLogin ()
	{
		// Login succeeded
		$html = $this->getContent (false);
		
		$me = Neuron_GameServer::getPlayer ();
		
		$this->updateContent ($html);
		
		if ($me->isPlaying ())
		{
			// Scroll to the right location
			$home = $me->getHomeLocation ();
			$this->mapJump ($home[0], $home[1]);
		}
		
		// Reload everything (all open windows)
		reloadEverything ();
		
		// Set the request data
		$this->updateRequestData ('');
	}
	
	private function getOpenId ()
	{
		$input = $this->getInputData ();
		return $this->getOpenIdForm ();
	}
	
	private function getOpenIdForm ($sError = false)
	{
		$page = new Neuron_Core_Template ();
		$page->setTextSection ('openid', 'account');
		
		$page->set ('error', $sError);
		
		$page->set ('url', API_OPENID_URL.'login/');
		
		$page->addListValue ('popular', array('Yahoo', 'http://www.yahoo.com/'));
		
		return $page->parse ('gameserver/account/openid.phpt');
	}
	
	protected function getPlayerInitialization ()
	{
		return '<p>There should be a form here to initialize the account ("choose race" etc).</p>';
	}
}

?>
