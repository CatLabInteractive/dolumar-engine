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

class Neuron_Auth_OpenID
{
	public function dispatch ()
	{
		$sInputs = explode ('/', isset ($_GET['module']) ? $_GET['module'] : '');
		unset ($_GET['module']);

		$sAction = isset ($sInputs[1]) ? $sInputs[1] : null;
		switch ($sAction)
		{
			case 'register':
				$this->newAccount ();
			break;

			default:
				// OpenID 2.0 login was removed; only OpenID Connect (oauth2/login) is supported.
				echo '<h2>Error in OpenID!</h2>';
				echo '<p>OpenID 2.0 login is no longer supported.</p>';
			break;
		}
	}

    /**
     * @param $esc_identity
     * @param $email
     * @throws Neuron_Core_Error
     */
	public function handleAuthentication(
	    $esc_identity,
        $email,
        $notify_url = null,
        $profilebox_url = null,
        $openid_userstats = null
    ) {
        // Fetch a fresh user ID
        $db = Neuron_Core_Database::__getInstance ();
        $login = Neuron_Core_Login::__getInstance ();

        // See if there is an account available
        $acc = $db->select
        (
            'n_auth_openid',
            array ('user_id'),
            "openid_url = '".$db->escape ($esc_identity)."'"
        );

        $_SESSION['neuron_openid_identity'] = $esc_identity;

        if (count ($acc) == 1 && $acc[0]['user_id'] > 0)
        {
            $id = $acc[0]['user_id'];
            loginAndRedirect ($acc[0]['user_id'], $email);
        }
        else
        {
            if (count ($acc) == 0)
            {
                // Create a new account
                $db->insert
                (
                    'n_auth_openid',
                    array
                    (
                        'openid_url' => $esc_identity,
                        'user_id' => 0
                    )
                );
            }

            // Set a session key to make sure
            // that the server still knows you
            // when you hit submit.
            $_SESSION['dolumar_openid_identity'] = $esc_identity;
            $_SESSION['dolumar_openid_email'] = $email;

            $url = ABSOLUTE_URL.'dispatch.php?module=openid/register/&session_id='.session_id ();

            header ('Location: ' . $url);
        }

        // Update this ID
        $db->update
        (
            'n_auth_openid',
            array
            (
                'notify_url' => $notify_url,
                'profilebox_url' => $profilebox_url,
                'userstats_url' => $openid_userstats
            ),
            "openid_url = '".$db->escape ($esc_identity)."'"
        );
    }

	private function newAccount ()
	{
		// This script should only be executed if an openid is set in the session
		if (isset ($_SESSION['dolumar_openid_identity']))
		{
			$email = $_SESSION['dolumar_openid_email'];

			$login = Neuron_Core_Login::__getInstance ();
			$id = $login->registerAccount (null, $email);

			if ($id > 0)
			{
				registerWithOpenid ($id, $_SESSION['dolumar_openid_identity'], $email);
			}
			else
			{
				die ('Could not register a new account: '.$login->getError ());
			}

			// Skip all the login magic, combining account is now only done on a bigger level.
			/*

			header("Content-Type: text/html; charset=UTF-8");

			$page = new Neuron_Core_Template ();
			
			$server = Neuron_GameServer::getServer();
			$name = $server->getServerName ();
			
			$page->set ('name', $name);
			$page->set ('session_id', session_id ());
			
			$page->set ('showDetails', Neuron_Core_Tools::getInput ('_GET', 'details', 'varchar'));

			$login = Neuron_Core_Login::__getInstance ();
			$db = Neuron_Core_Database::__getInstance ();

			// Check for username and password. If these are found, put them in the database.
			$username = isset ($_POST['username']) ? $_POST['username'] : false;
			$password = isset ($_POST['password']) ? $_POST['password'] : false;
			
			if ($username && $password)
			{
				// Check for details:
				$player = $login->checkLoginDetails ($username, $password);
				
				if ($player)
				{
					registerWithOpenid ($player->getId (), $_SESSION['dolumar_openid_identity']);
				}
				else
				{
					$page->set ('error', true);
				}
			}

			elseif (isset ($_GET['do']) 
				&& $_GET['do'] == 'register' 
				|| (
					defined ('OPENID_SKIP_LOGIN') 
					&& OPENID_SKIP_LOGIN
				)
			)
			{
				$email = isset ($_SESSION['dolumar_openid_email']) ? $_SESSION['dolumar_openid_email'] : null;
			
				if (defined ('OPENID_SKIP_NICKNAME') 
					&& OPENID_SKIP_NICKNAME
					&& !empty ($_SESSION['openid_nickname'])
				)
				{
					$id = $login->registerAccount ($_SESSION['openid_nickname'], $email);
				}
				else
				{
					$id = $login->registerAccount (null, $email);
				}

				if ($id > 0)
				{
					registerWithOpenid ($id, $_SESSION['dolumar_openid_identity']);
				}
				else
				{
					die ('Could not register a new account: '.$login->getError ());
				}
			}
			
			$page->set ('static_client_url', BASE_URL . 'gameserver/');
			
			// Plugins
			$header = '';
			if (Neuron_Core_Template::hasTemplate ('gameserver/openid/header.phpt'))
				$header = $page->parse ('gameserver/openid/header.phpt');
			
			$page->set ('header', $header);
			
			echo $page->parse ('openid/register.phpt');
			*/
		}
		else
		{
			echo '<h2>Error in OpenID!</h2>';
			echo '<p>You shouldn\'t be here. No openid is set.</p>';
		}
	}
}

header('Cache-Control: no-cache');
header('Pragma: no-cache');


restore_error_handler ();
error_reporting (E_ERROR);


function loginAndRedirect ($id, $email)
{
	$login = Neuron_Core_Login::__getInstance ();

	if ($login->doLogin ($id, false, $email))
	{
		//$_SESSION['just_logged_in'] = true;
	
		$url = ABSOLUTE_URL.'?session_id='.session_id ();
		header ('Location: '.$url);
		echo '<p>You are logged in.<br />Click <a href="'.$url.'">here</a> to continue.</p>';
		echo '<script type="text/javascript">document.location = \''.$url.'\';</script>';
	}
	else
	{
		echo ('<p>Could not login to account '.$id.'</p>');
		echo '<p>Error: '.$login->getError ().'</p>';
		exit (1);
	}
}

function registerWithOpenid ($id, $identity, $email)
{
	$db = Neuron_Core_Database::__getInstance ();

	// Update this ID
	$db->update
	(
		'n_auth_openid',
		array
		(
			'user_id' => $id
		),
		"user_id = 0 AND openid_url = '".$db->escape ($identity)."'"
	);

	// Load the id, just to beware of copies
	$data = $db->query
	("
		SELECT
			*
		FROM
			n_auth_openid
		WHERE
			openid_url = '".$db->escape ($identity)."'
	");

	$id = $data[0]['user_id'];
	
	// now login and redirect.
	loginAndRedirect ($id, $email);
}
