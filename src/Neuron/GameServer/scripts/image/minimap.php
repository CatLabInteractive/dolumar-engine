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

$width = isset ($_GET['width']) ? intval ($_GET['width']) : 200;
$tilesToLoad = isset ($_GET['tiles']) ? intval ($_GET['tiles']) : (int)ceil ($width / 4);

$nocache = isset ($_GET['nocache']) ? true : false;

/*
	Return the background image.
*/
function getBackgroundImage ($x, $y, $tilesToLoad, $usecache = true)
{
	global $color_cache;

	$cachename = 'i'.intval($_GET['x']).'p'.intval($_GET['y']).$tilesToLoad.'.png';
	$cache = Neuron_Core_Cache::__getInstance ('minimapbg/');
	
	if ($usecache && $cache->hasCache ($cachename, 0))
	{
		$img = $cache->getFileName ($cachename);
		return imagecreatefrompng ($img);
	}
	else
	{
		$color_cache = array ();
	
		// Build the new background image.
		$tileSizeX = 8;
		$tileSizeY = $tileSizeX / 2;
	
		$halfTileX = floor ($tileSizeX / 2);
		$halfTileY = floor ($tileSizeY / 2);
	
		$im = imagecreate ($tileSizeX * $tilesToLoad, $tileSizeY * $tilesToLoad);
	
		$background = imagecolorallocate ($im, 0, 0, 0);
	
		$switchpoint = $tilesToLoad;
		$loadExtra = 1;
	
		$startX = ( $x + $y ) * $switchpoint;
		$startY = ( $x - $y ) * $switchpoint;
	
		for ($i = (0 - $loadExtra - 1); $i < ($switchpoint * 2) + $loadExtra + 1; $i ++)
		{
			if ($i > $switchpoint)
			{
				$offset = ($i - $switchpoint) * 2;
			}
			else {
				$offset = 0;
			}
		
			$colStart = 0 - $i  + $offset - $loadExtra;
			$colEnd = $i - $offset + $loadExtra + 1;
		
			//$output['sq'][$sQ]['tl'][$i] = array ();
		
			$tx = $startX + $i;
		
			$white = imagecolorallocate ($im, 255, 255, 255);
		
			for ($j = $colStart - 1; $j < $colEnd + 1; $j ++)
			{
				$ty = $startY - $j;
			
				$px = round (($i - $j) * floor ($tileSizeX / 2));
				$py = round (($i + $j) * floor ($tileSizeY / 2));
			
				// Check for building
				/*
				if (isset ($buildings[$tx]) && isset ($buildings[$tx][$ty]))
				{
					$color = color_cache ($im, $buildings[$tx][$ty][0]->getMapColor ());
				}
			
				else
				{
				*/
					$location = Dolumar_Map_Location::getLocation ($tx, $ty);

					$c = $location->getHeightIntencity ();
					$col = $location->getMapColor ();

					$col[0] = floor ($col[0] * $c);
					$col[1] = floor ($col[1] * $c);
					$col[2] = floor ($col[2] * $c);
				
					$color = color_cache ($im, $col);
				//}
			
				$punten = array
				(
					// Startpunt
					$px + $halfTileX, $py,
					$px + $tileSizeX, $py + $halfTileY,
					$px + $halfTileX, $py + $tileSizeY,
					$px, $py + $halfTileY
				
				);
			
				imagefilledpolygon($im, $punten, $color);
			}
		}
		
		ob_start ();
		imagepng ($im, null);
		
		$cache->setCache ($cachename, ob_get_clean ());
		
		return $im;
	}
}

function color_cache ($im, $color)
{
	global $color_cache;
	
	if (!isset ($color_cache[$color[0].'_'.$color[1].'_'.$color[2]]))
	{
		$color_cache[$color[0].'_'.$color[1].'_'.$color[2]] = imagecolorallocate ($im, $color[0], $color[1], $color[2]);
	}
	return $color_cache[$color[0].'_'.$color[1].'_'.$color[2]];
}

if (isset ($_GET['x']) && isset ($_GET['y']))
{
	$extension = 'png';
	
	$cache = Neuron_Core_Cache::__getInstance ('minimap/');
	
	// Fetch cache
	$cachename = 'i'.intval($_GET['x']).'p'.intval($_GET['y']).$tilesToLoad.$extension;
	
	$image = $cache->getCache ($cachename, 60 * 60 * 6);
	//$image = false;
	
	if ($image && !$nocache)
	{
		header("Content-type: image/".$extension);
		header("Expires: " . gmdate("D, d M Y H:i:s", time() + 60*60*12) . " GMT");
		echo $image;
	}
	
	else
	{
		//$cache->setCache ($cachename, 'locked');
	
		$x = $_GET['x'];
		$y = $_GET['y'];
		
		$im = getBackgroundImage ($x, $y, $tilesToLoad);
		
		$color_cache = array ();
	
		// Build the new background image.
		$tileSizeX = 8;
		$tileSizeY = $tileSizeX / 2;
	
		$halfTileX = floor ($tileSizeX / 2);
		$halfTileY = floor ($tileSizeY / 2);
	
		$switchpoint = $tilesToLoad;
		$loadExtra = 1;
	
		$startX = ( $x + $y ) * $switchpoint;
		$startY = ( $x - $y ) * $switchpoint;
	
		$db = Neuron_Core_Database::__getInstance ();

		$locations = array
		(
			array
			(
				$startX + ($switchpoint/2),
				$startY - ($switchpoint/2)
			)
		);
		
		// Load buildings from SQL
		$buildingSQL = Dolumar_Map_Map::getBuildingsFromLocations ($locations, $switchpoint + 25);
		
		foreach ($buildingSQL as $buildingV)
		{
			$race = Dolumar_Races_Race::getRace ($buildingV['race']);
			
			$b = Dolumar_Buildings_Building::getBuilding 
			(
				$buildingV['buildingType'], 
				$race, 
				$buildingV['xas'], $buildingV['yas']
			);
			
			$b->setData ($buildingV['bid'], $buildingV);	
			//$buildings[floor ($buildingV['xas'])][floor ($buildingV['yas'])][] = $b;
			
			$x = floor ($buildingV['xas']);
			$y = floor ($buildingV['yas']);
			
			$color = color_cache ($im, $b->getMapColor ());
			
			$i = $x - $startX;
			$j = $startY - $y;
			
			$px = round (($i - $j) * floor ($tileSizeX / 2));
			$py = round (($i + $j) * floor ($tileSizeY / 2));
			
			$tileSizeX = 8;
			$tileSizeY = $tileSizeX / 2;

			$halfTileX = floor ($tileSizeX / 2);
			$halfTileY = floor ($tileSizeY / 2);
			
			$punten = array
			(
				// Startpunt
				$px + $halfTileX, 	$py,			// Boven
				$px + $tileSizeX, 	$py + $halfTileY, 	// Rechts
				$px + $halfTileX, 	$py + $tileSizeY, 	// Onder
				$px, 			$py + $halfTileY 	// Links
			);
			
			imagefilledpolygon($im, $punten, $color);
		}
		
		// Start output buffering
		ob_start ();
		
		if ($extension == 'gif')
		{
			imagegif ($im);
		}
		
		else
		{			
			imagepng ($im, null);
		}
		
		imagedestroy ($im);
		
		// Fetch thze output & flush it down the toilet
		header("Content-type: image/".$extension);
		header("Expires: " . gmdate("D, d M Y H:i:s", time() + 60*60*12) . " GMT");
		
		$output = ob_get_flush();		
		$cache->setCache ($cachename, $output);
	}
}
