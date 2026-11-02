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

$key = Neuron_Core_Tools::getInput ('_REQUEST', 'key', 'md5', false);
$request = isset ($sInputs[1]) ? strtolower ($sInputs[1]) : null;

$parameters = array ();

$xml_name = 'browsergameshub';
$xml_version = '1';

/**
 * Stop with HTTP 403 unless the current player is an admin.
 */
function requireApiAdmin ()
{
	if (!Neuron_GameServer::isAdminRequest ())
	{
		http_response_code (403);
		echo '<p>Forbidden.</p>';
		exit;
	}
}

$login = Neuron_Core_Login::getInstance (0, false);
if (isset ($_SERVER['PHP_AUTH_USER']) && 
	isset ($_SERVER['PHP_AUTH_PW']))
{
	$login->login ($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
}

if ($request == 'emailcert')
{
	$key = Neuron_Core_Tools::getInput ('_REQUEST', 'certkey', 'varchar', false);
	$id = Neuron_Core_Tools::getInput ('_REQUEST', 'id', 'int', false);
	
	$player = Neuron_GameServer::getPlayer ($id);
	if ($player && $player->isFound ())
	{
		if ($player->certifyEmail ($key))
		{
			echo '<p>Your E-mail adress has been verified.</p>';
		}
		else
		{
			echo '<p>Invalid link.</p>';
		}
	}
	else
	{
		echo '<p>Invalid input: player not found.</p>';
	}
}

elseif ($request === 'unsubscribe')
{
	$id = Neuron_Core_Tools::getInput ('_GET', 'id', 'varchar', false);
	$key = Neuron_Core_Tools::getInput ('_GET', 'key', 'varchar');
	$confirm = Neuron_Core_Tools::getInput ('_GET', 'confirm', 'varchar');

	$player = Neuron_GameServer::getPlayer ($id);
	if ($player && $player->isFound ())
	{
		if ($player->isValidUnsubsribeKey ($key))
		{
			if ($confirm)
			{
				$player->setPreference ('emailNotifs', 0);
				echo '<p>You have been unsubscribed from Dolumar notifications.<br />';
				echo '<a href="' . ABSOLUTE_URL . '">Go back to Dolumar</a>';
				echo '</p>';
			}
			else
			{
				echo '<p>Are you sure you want to unsubscribe from receiving Dolumar notifications?<br />';
				echo '<a href="' . htmlspecialchars (API_FULL_URL . 'unsubscribe?id=' . urlencode ($id) . '&key=' . urlencode ($key) . '&confirm=1') . '">Yes</a> | ';
				echo '<a href="' . ABSOLUTE_URL . '">No</a> ';
				echo '</p>';
			}
		}
		else
		{
			echo '<p>Invalid link.</p>';
		}
	}
}

elseif ($request == 'reset')
{
	$key = Neuron_Core_Tools::getInput ('_REQUEST', 'certkey', 'varchar', false);
	$id = Neuron_Core_Tools::getInput ('_REQUEST', 'id', 'int', false);
	
	$player = Neuron_GameServer::getPlayer ($id);
	if ($player && $player->isFound ())
	{
		if ($player->resetAccount ($key))
		{
			echo '<p>Your account has been reset.</p>';
		}
		else
		{
			echo '<p>I could not reset your account. It seems like your link is not valid.</p>';
		}
	}
	else
	{
		echo '<p>Invalid input: player not found.</p>';
	}
}

elseif ($request == 'spendcredit')
{
	$profiler = Neuron_Profiler_Profiler::getInstance (true);

	$plid = Neuron_Core_Tools::getInput ('_GET', 'id', 'int');

	$transaction_id 	= Neuron_Core_Tools::getInput ('_GET', 'transaction_id', 'varchar');
	$transaction_key 	= Neuron_Core_Tools::getInput ('_GET', 'transaction_key', 'varchar');

	$player = Neuron_GameServer::getPlayer ($plid);
	if ($player)
	{
		if ($player->handleUseRequest ($_GET, $transaction_id, $transaction_key))
		{
			echo '<p>Premium action was executed.</p>';
		}
		else
		{
			echo '<p>Something went wrong: '.$player->getError ().'</p>';
		}
	}
	else
	{
		echo '<p>Invalid input: user not found.</p>';
	}
	
	echo "\n\nProfiler: \n" . $profiler;
}

else
{

	// Start counter
	$pgen = Neuron_Core_PGen::__getInstance ();
	$pgen->start ();

	// Fetch input
	$output_type = Neuron_Core_Tools::getInput ('_REQUEST', 'output', 'varchar', false);

	$output = array ();

	$output['request'] = $_GET;
	$output['content'] = array ();

	// Requests
	switch ($request)
	{
		case 'getlogs':

			requireApiAdmin ();

			$id = Neuron_Core_Tools::getInput ('_GET', 'id', 'int', isset ($sInputs[2]) ? $sInputs[2] : 0);
			$start = Neuron_Core_Tools::getInput ('_GET', 'start', 'int', 0);
			$objLogs = Dolumar_Players_Logs::__getInstance ();
			$output['content'] = $objLogs->getLogs ($id, $start, 1000000);
			
		break;

		case 'getvillageinfo':
			$id = Neuron_Core_Tools::getInput ('_GET', 'id', 'int', 0);
			$village = Dolumar_Players_Village::getVillage ($id);
			if ($village && $village->isFound ())
			{
				$output['content'] = $village->getAPIData ();
			}
		break;

		case 'getplayerinfo':
			$id = Neuron_Core_Tools::getInput ('_GET', 'id', 'int', 0);
			$player = Neuron_GameServer::getPlayer ($id);
			if ($player && $player->isFound ())
			{
				$output['content'] = $player->getAPIData ();
			}
		break;

		case 'getonlineplayers':
			$start = Neuron_Core_Tools::getInput ('_GET', 'start', 'int', 0);
			$players = Neuron_GameServer::getServer()->getOnlineUser (($start)*250, 250);

			$output['content'] = array ();
			foreach ($players as $v)
			{
				$output['content'][] = $v->getAPIData (false);
			}
		break;

		case 'countplayers':
		
			$extended = Neuron_Core_Tools::getInput ('_GET', 'extended', 'varchar');

			// Players
			$output['content']['players'] = array ();
			$output['content']['players']['online'] = Neuron_GameServer::getServer()->countOnlineUsers ();
			$output['content']['players']['lastFive'] = Neuron_GameServer::getServer()->countOnlineUsers (60*5);
			$output['content']['players']['lastHour'] = Neuron_GameServer::getServer()->countOnlineUsers (60*60);
			$output['content']['players']['lastDay'] = Neuron_GameServer::getServer()->countOnlineUsers (60*60*24);
			$output['content']['players']['lastWeek'] = Neuron_GameServer::getServer()->countOnlineUsers (60*60*24*7);
			$output['content']['players']['lastMonth'] = Neuron_GameServer::getServer()->countOnlineUsers (60*60*24*31);
			$output['content']['players']['total'] = Neuron_GameServer::getServer()->countTotalPlayers ();
			
			if ($extended)
			{
				$output['content']['premium'] = array ();
				$output['content']['premium']['online'] = Neuron_GameServer::getServer()->countPremiumPlayers ();
				$output['content']['premium']['lastFive'] = Neuron_GameServer::getServer()->countPremiumPlayers (60*5);
				$output['content']['premium']['lastHour'] = Neuron_GameServer::getServer()->countPremiumPlayers (60*60);
				$output['content']['premium']['lastDay'] = Neuron_GameServer::getServer()->countPremiumPlayers (60*60*24);
				$output['content']['premium']['lastWeek'] = Neuron_GameServer::getServer()->countPremiumPlayers (60*60*24*7);
				$output['content']['premium']['lastMonth'] = Neuron_GameServer::getServer()->countPremiumPlayers (60*60*24*31);
				$output['content']['premium']['total'] = Neuron_GameServer::getServer()->countPremiumPlayers ();
			}
			//$output['content']['players']['sponsors'] = Neuron_GameServer::getServer()->countSponsors ();

			// Villages
			$output['content']['villages'] = array ();
			$output['content']['villages']['total'] = Neuron_GameServer::getServer()->countVillages ();
			$output['content']['villages']['active'] = Neuron_GameServer::getServer()->countVillages (false);
			$output['content']['villages']['races'] = array ();
			foreach (Dolumar_Races_Race::getRaces () as $k => $v)
			{
				$output['content']['villages']['races'][$v] = Neuron_GameServer::getServer()->countVillagesFromRace ($k);
			}
			
			// Invitations
			//$output['content']['invitations'] = Neuron_GameServer::getServer()->countInvitations ();
			
			// Auth types
			//$output['content']['auths'] = Neuron_GameServer::getServer()->countAuthTypes ();
			
		break;
		
		case 'clearcache':
			requireApiAdmin ();
			$output['content'] = Neuron_GameServer::getServer()->clearCache (isset ($_GET['clearMapCache']) && $_GET['clearMapCache'] == 'yes');
		break;
		
		case 'ranking':
		
			// Ranking is handling its own output.
			include ('ranking.php');
			exit ();

		break;
		
		case 'rss':
		
			$text = Neuron_Core_Text::__getInstance ();
		
			$output_type = 'xml';
			$xml_name = 'rss';
			$xml_version = '2.0';
			
			// Check for login
			if 
			(
				$login->isLogin ()
			)
			{
				$myself = Neuron_GameServer::getPlayer ();
			
				$output['content']['channel'] = array ();
				$output['content']['title'] = Neuron_Core_Tools::putIntoText 
				(
					$text->get ('rss_title', 'main', 'main'), 
					array 
					(
						'username' => Neuron_Core_Tools::output_varchar ($myself->getName ())
					)
				);
				
				$output['content']['link'] = ABSOLUTE_URL;
				$output['content']['description'] = 'Player logs';
				
				$objLogs = Dolumar_Players_Logs::__getInstance ();
				
				// Only village
				//$village = $myself->getMainVillage ();
				
				//if ($village) {
					foreach ($objLogs->getLogs ($myself, 0, 50, 'DESC', false) as $v)
					{
						// <pubDate>Sun, 19 May 2002 15:21:36 GMT</pubDate>
				
						$output['content']['items'][] = array
						(
							'title' => $objLogs->getLogText ($v, false, false),
							'link' => ABSOLUTE_URL,
							'description' => null,
							'pubDate' => gmdate ('r', $v['unixtime'])
						);
					}
				//}
			}
			else
			{
				header('WWW-Authenticate: Basic realm="Profile Logs"');
				header('HTTP/1.0 401 Unauthorized');
				echo 'Please login to access your profile logs.';
				exit;
			}
			
		break;
		
		case 'messagebundle':
		
			/*
			$output_type = 'xml';
			$xml_name = 'messagebundle';
			$xml_version = null;
			*/
			
			header ('Content-type: text/xml');
			
			$text = Neuron_Core_Text::getInstance ();
			echo Neuron_Core_MessageBundle::text2bundle ($text);
			
			exit;
		
		break;

		case 'getsession':

			session_write_close ();
			session_start ();
			$id = session_id ();

			echo $id;
			exit;

		break;

		default:
			$output['content'] = array ();
		break;
	}

	$pgen->stop ();

	$db = Neuron_Core_Database::__getInstance ();

	$output['info'] = array ();
	$output['info']['mysqlCount'] = $db->getCounter ();
	$output['info']['runduration'] = $pgen->gen (6);
	$output['info']['contentCount'] = count ($output['content']);

	// Output
	switch ($output_type)
	{
		case 'json':
			header ('Content-Type: application/json');
			echo json_encode ($output);
		break;

		case 'serialize':
			header ('Content-Type: text/plain');
			echo serialize ($output);
		break;
		
		case 'xml':
			header ('Content-type: text/xml');
			echo Neuron_Core_Tools::output_xml ($output['content'], $xml_version, $xml_name, $parameters);
		break;

		case 'print':
		default:
			echo '<pre>';
			echo htmlspecialchars (print_r ($output, true));
			echo '</pre>';
		break;
	}
}
?>
