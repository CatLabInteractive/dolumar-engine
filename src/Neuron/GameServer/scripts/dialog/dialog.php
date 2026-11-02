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

// TO add: a function to open windows while being in a class ;-)
function openNewWindow ($sWindow, $aParams = array ())
{
	$server = Neuron_GameServer::getInstance ();
	$server->openWindow ($sWindow, $aParams);
}

$javascriptAlertErrors = array ();
function throwAlertError ($msg)
{
	$GLOBALS['javascriptAlertErrors'][] = $msg;
}


// Creat new xml file
global $dom;
global $root;

$dom = new DOMDocument('1.0', 'utf-8');
$root = $dom->createElement ('root');

// Check for map reloads
$login = Neuron_Core_Login::__getInstance ();

$profiler = Neuron_Profiler_Profiler::getInstance ();
$profiler->start ('Processing windows');

function handleOpenWindowRequest ($windows)
{
	global $dom;
	global $root;

	foreach ($windows as $window)
	{
		if ($window)
		{
			// One window failing must not drop the windows after it. The initial windows end with the invisible
			// map updater, so a single broken window used to stop the map from ever showing new buildings.
			try
			{
				$window->setSettings ();

				$window->setDom ($dom);

				// Append new window xml
				$node = $window->getNewWindow ();
				if ($node)
				{
					$root->appendChild ($node);
				}
			}
			catch (Throwable $e)
			{
				Neuron_ErrorHandler_Handler::getInstance ()->notify ($e);

				if (defined ('OUTPUT_DEBUG_DATA') && OUTPUT_DEBUG_DATA)
				{
					echo $e;
				}
			}
		}
	}
}

// List all windows
$openwindows = isset ($_POST['openwindow']) ? $_POST['openwindow'] : null;
if (is_array ($openwindows))
{
	$profiler->start ('Opening new windows');
	foreach ($openwindows as $v)
	{
		try
		{
			if (is_array ($v))
			{
				$profiler->start ('Processing window '.$v['sWindowId']);
				$windows = array ();
				if ($v['sWindowId'] == 'game:Initialize')
				{
					$windows = $this->getInitialWindows ();
				}
				else
				{
					$window = $this->getWindow ($v['sWindowId']);

					if (isset ($v['sRequestData']))
					{
						$window->setRequestData ($v['sRequestData']);
					}

					$window->setJsonInputData ($v['sInputData']);

					// Append to array
					$windows[] = $window;
				}

				handleOpenWindowRequest ($windows);

				$profiler->stop ();
			}
		}
		catch (Throwable $e)
		{
			// Send a mail
            Neuron_ErrorHandler_Handler::getInstance()->notify($e);

			if (defined ('OUTPUT_DEBUG_DATA') && OUTPUT_DEBUG_DATA)
			{
				echo $e;
			}
		}
	}
	$profiler->stop ();
}

$updatewindows = $this->getOpenWindows ();
$firstUpdate = true;

foreach ($updatewindows as $window)
{
	// Put everything in a big TRY
	$profiler->start ('Processing window '.$window->getWindowId ());

	try
	{
		$window->setDom ($dom);

		// IF a class called reloadEverything: reload class
		if (defined ('RELOAD'))
		{
			$window->reloadContent ();

			if ($firstUpdate)
			{
				$window->channel->refresh ('__session__');
				$firstUpdate = false;
			}
		}

		else if ($window->getInput ('action') == '__reloadContent__')
		{
			$window->reloadContent ();
		}

		// Load updates
		$updates = $window->getDOMRefresh ();

		if ($updates)
		{
			// Append new window xml
			$root->appendChild ($updates);
		}
	}
	catch (Throwable $e)
	{
		// Send a mail
        Neuron_ErrorHandler_Handler::getInstance()->notify($e);

		if (defined ('OUTPUT_DEBUG_DATA') && OUTPUT_DEBUG_DATA)
		{
			echo $e;
		}
	}

	$profiler->stop ();
}

$profiler->stop ();

$pgen->stop ();

// Database
$db = Neuron_Core_Database::__getInstance ();

$profiler = Neuron_Profiler_Profiler::getInstance ();

// Let's add some additional data
$run = $dom->createElement ('runtime');
$run->appendChild ($dom->createElement ('session_id', session_id ()));
$run->appendChild ($dom->createElement ('parsetime', $pgen->gen (4)));
$run->appendChild ($dom->createElement ('mysqlcount', $db->getCounter ()));

$content = $dom->createCDATASection ($profiler);
$element = $run->appendChild ($dom->createElement ('profiler'));
$element->appendChild ($content);

$content = $dom->createCDATASection (print_r ($_REQUEST, true));
$run->appendChild ($dom->createElement ('request'))->appendChild ($content);


$root->appendChild ($run);

// Go for alerts
if (isset ($GLOBALS['javascriptAlertErrors']))
{
	foreach ($GLOBALS['javascriptAlertErrors'] as $v)
	{
		$alert = $dom->createCDATASection ($v);
		$alertCon = $dom->createElement ('javascriptAlerts');
		$alertCon->appendChild ($alert);
		$root->appendChild ($alertCon);
	}
}

$dom->appendChild ($root);

// Output XML
$output = $dom->saveXML();


// Set header to xml
if (!ob_get_contents()) {
	header("Content-Type: text/xml; charset=utf-8;");
}

echo $output;