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

global $imageCache;
global $output;
global $imagecounter;

$imagecounter = 1;

$diameter = Neuron_Core_Tools::clampInt ($_GET['tiles'] ?? null, 1, 10, 5);
$loadExtra = Neuron_Core_Tools::clampInt ($_GET['overlap'] ?? null, 0, 3, 3);

// Start profiler
$profiler = Neuron_Profiler_Profiler::__getInstance ();
$profiler->start ('Generating all JSON map requests');

$output = '';
$imageCache = array ();
function map_image_cache (Neuron_GameServer_Map_Display_Sprite $image)
{
	global $imageCache;
	global $output;
	global $imagecounter;
	
	$id = $image->getURI ();
	
	if (!isset ($imageCache[$id]))
	{
		$intid = md5 ($id);
		
		$imageCache[$id] = $imagecounter;
	
		$output['images'][$imagecounter] = array 
		(
			'id' => $intid,
			'url' => $image->getURI ()
		);
		
		$imagecounter ++;
	}
	
	return $imageCache[$id];
}

function checkBuildings ($buildings, $sQ, $i, $j, $tx, $ty)
{
	global $output;
	
	// Objects & buildings
	if (isset ($buildings[$tx]) && isset ($buildings[$tx][$ty]))
	{
		$loc = array ();
		foreach ($buildings[$tx][$ty] as $b)
		{
			$displayobject = $b->getDisplayObject ();
			
			$offset = $displayobject->getOffset ();
			
			$title = Neuron_Core_Tools::output_varchar ($b->getName ());
			$location = $b->getLocation ();

			if (!isset ($loc[$location[0].'p'.$location[1]]))
			{
				if (!isset ($output['regions'][$sQ]['objects'][$i]))
				{
					$output['regions'][$sQ]['objects'][$i] = array ();
				}
				
				if (!isset ($output['regions'][$sQ]['objects'][$i][$j]))
				{
					$output['regions'][$sQ]['objects'][$i][$j] = array ();
				}
				
				$id = time ();
			
				$output['regions'][$sQ]['objects'][$i][$j][] = array
				(
					'tx' => $tx,
					'ty' => $ty,
					'id' => $id,
					'i' => $i - $offset[0],
					'j' => $j - $offset[1],
					'm' => map_image_cache ($displayobject),
					'n' => $title,
					's' => $b->getMapObjectStatus ()->getArray (),
					'c' => $b->getEvents ('click'),
					'z' => 0,
					'd' => true
				);

				$loc[$location[0].'p'.$location[1]] = true;
			}
		}
	}
}

//$_POST['squares'] = array (array (-1, 0), array (-2, 0));
if (isset ($_GET['x']) && isset ($_GET['y']))
{
	$_POST['regions'][] = array ($_GET['x'], $_GET['y']);
	/*
	$_POST['regions'][] = array ($_GET['x']+1, $_GET['y']);
	$_POST['regions'][] = array ($_GET['x']+1, $_GET['y']+1);
	$_POST['regions'][] = array ($_GET['x'], $_GET['y']+1);
	*/
}

$_POST['regions'] = Neuron_Core_Tools::sanitizeMapRegions ($_POST['regions'] ?? null, 100);

$db = Neuron_Core_Database::__getInstance ();

if (count ($_POST['regions']) > 0)
{
	$profiler->start ('Calculating regions average point + loading buildings');
	
	$profiler->start ('Calculating regions average point');
	
	$sQ = 0;
	
	$switchpoint = $diameter;

	// Zoek zo'n beetje het middelpunt:
	$minx = $_POST['regions'][0][0];
	$miny = $_POST['regions'][0][1];

	$maxx = $minx;
	$maxy = $miny;

	$squarePoints = array ();

	foreach ($_POST['regions'] as $v)
	{
		if ($v[0] > $maxx) { $maxx = $v[0]; }
		if ($v[1] > $maxy) { $maxy = $v[1]; }
		
		if ($v[0] < $minx) { $minx = $v[0]; }
		if ($v[1] < $miny) { $miny = $v[1]; }

		$squarePoints[] = array
		(
			(($v[0] + $v[1]) * $switchpoint) + ($switchpoint / 2),
			(($v[0] - $v[1]) * $switchpoint) - ($switchpoint / 2)
		);
	}

	$x = floor ($maxx - ( ($maxx - $minx) / 2));
	$y = floor ($maxy - ( ($maxy - $miny) / 2));

	// Calculate iso x & y
	$startX = ( $x + $y ) * $switchpoint;
	$startY = ( $x - $y ) * $switchpoint;

	$radius = max
	(
		($maxx - $minx + 1) * 50,
		($maxy - $miny + 1) * 50
	);
	
	$profiler->stop ();
	
	$profiler->start ('Loading buildings');

	// Load buildings from SQL
	//$buildingSQL = Dolumar_Map_Map::getBuildings ($startX, $startY, $radius);
	$map = $this->getMap ();
	
	// Make the requests parameter
	$areas = array ();
	foreach ($squarePoints as $v)
	{
		$location = new Neuron_GameServer_Map_Location ($v[0], $v[1]);
		$areas[] = new Neuron_GameServer_Map_Area ($location, $switchpoint * 2);
	}
	
	$objects = $map->getMapObjectManager ()->getMultipleDisplayObjects ($areas);
	$buildings  = array ();

	foreach ($objects as $v)
	{
		list ($x, $y) = $v->getLocation ();
		
		$x = floor ($x);
		$y = floor ($y);
		
		if (!isset ($buildings[$x]))
		{
			$buildings[$x] = array ();
		}
		
		if (!isset ($buildings[$x][$y]))
		{
			$buildings[$x][$y] = array ();
		}
		
		$buildings[$x][$y][] = $v;
	}

	// Loop trough squares
	$output = array ();
	$output['images'] = array ();
	
	$profiler->stop ();
	
	// Stop profier
	$profiler->stop ();

	foreach ($_POST['regions'] as $v)
	{
		$profiler->start ('Generating one map region ('.$v[0].','.$v[1].')');
		
		// Initialise array
		$output['regions'][$sQ] = array ();
		$output['regions'][$sQ]['objects'] = array ();
		
		$x = $v[0];
		$y = $v[1];
		
		$output['regions'][$sQ]['x'] = $x;
		$output['regions'][$sQ]['y'] = $y;
		
		// Squares: Background		
		$startX = ( $x + $y ) * $switchpoint;
		$startY = ( $x - $y ) * $switchpoint;
		
		$output['regions'][$sQ]['tiles'] = array ();
		
		// Start the writing
		$profiler->start ('Iterating the location tiles and load them');
		for ($i = (0 - $loadExtra); $i <= ($switchpoint * 2); $i ++)
		{
			if ($i > $switchpoint)
			{
				$offset = ($i - $switchpoint + 1) * 2;
			}
			else 
			{
				$offset = 0;
			}
			
			$colStart = 0 - $i  + $offset - $loadExtra;
			$colEnd = $i - $offset + $loadExtra + 1;
			
			$output['regions'][$sQ]['tiles'][$i] = array ();
			
			$tx = $startX + $i;
			
			for ($j = $colStart; $j < $colEnd; $j ++)
			{
				$ty = $startY - $j;
				
				// Draw background
				if 
				(
					$i > (0 - $loadExtra - 1) 
					&& $i < ($switchpoint * 2) + $loadExtra + 1
					&& $j > ($colStart - 1) 
					&& $j < ($colEnd + 1)
				)
				{
					// Coordinates
					$loc = new Neuron_GameServer_Map_Location ($tx, $ty);
					
					$objects = isset ($buildings[$tx][$ty]) ? count ($buildings[$tx][$ty]) : 0;
					
					$tiles = $map->getBackgroundManager ()->getLocation ($loc, $objects);
					
					$output['regions'][$sQ]['tiles'][$i][$j] = array ();
					
					foreach ($tiles as $v)
					{
						if (! ($v instanceof Neuron_GameServer_Map_Display_Sprite))
						{
							throw new Neuron_Core_Error ("region locations must be Neuron_GameServer_Map_Display_Sprite");
						}
					
						$output['regions'][$sQ]['tiles'][$i][$j][] = map_image_cache ($v);
					}
				}
				
				checkBuildings ($buildings, $sQ, $i, $j, $tx, $ty);
			}
		}
		$sQ ++;
		
		$profiler->stop ();
		
		$profiler->stop ();
	}
}

$pgen->stop ();
$output['parsetime'] = $pgen->gen (4);
$output['servertime'] = date (API_DATE_FORMAT);
$output['session_id'] = session_id ();

$output['profiler'] = $profiler->getOutput ();

// Output JSON
$profiler->start ('Generating output');

switch ($sOutput)
{
	case 'print':
		$profiler->start ('print_r');
		echo '<pre>';
		print_r ($output);
		echo '</pre>';
		$profiler->stop ();
	break;

	case 'json':
	default:
		$profiler->start ('json_encode');
		echo json_encode ($output);
		$profiler->stop ();
	break;
}
$profiler->stop ();