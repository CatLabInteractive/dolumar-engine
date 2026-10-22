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

// Only used by the admin-only /test/worldmap page.
if (!Neuron_GameServer::isAdminRequest ())
{
	http_response_code (403);
	return;
}

define ('MAP_PERLIN_NO_CACHE', true);

function color_cache ($im, $color)
{
	global $color_cache;
	
	if (!isset ($color_cache[$color[0].'_'.$color[1].'_'.$color[2]]))
	{
		$color_cache[$color[0].'_'.$color[1].'_'.$color[2]] = imagecolorallocate ($im, $color[0], $color[1], $color[2]);
	}
	return $color_cache[$color[0].'_'.$color[1].'_'.$color[2]];
}



Neuron_Profiler_Profiler::getInstance ()->setForceActivate (false);

if (isset ($_GET['x']) && isset ($_GET['y']))
{
	$extension = 'png';
	
	$cache = Neuron_Core_Cache::__getInstance ('worldmap/');
	
	$width = Neuron_Core_Tools::getInput ('_GET', 'width', 'int', 250);
	$height = Neuron_Core_Tools::getInput ('_GET', 'height', 'int', 250);
	$zoom = Neuron_Core_Tools::getInput ('_GET', 'zoom', 'int', 0);
	$x = Neuron_Core_Tools::getInput ('_GET', 'x', 'int', 0);
	$y = Neuron_Core_Tools::getInput ('_GET', 'y', 'int', 0);
	
	// Fetch cache
	$cachename = 'gw4_'.$zoom.'_'.$x.'_'.$y.'_'.$width.'x'.$height;
	
	$image = $cache->getCache ($cachename, 60 * 60 * 24 * 7);
	//$image = false;
	
	if ($image)
	{
		header("Content-type: image/".$extension);
		header("Expires: " . gmdate("D, d M Y H:i:s", time() + 60*60*12) . " GMT");
		echo $image;
	}
	
	else
	{
		$im = imagecreate ($width, $height);
		$background = imagecolorallocate ($im, 0, 0, 0);
		
		// Allocate black
		//$black = imagecolorallocate ($im, array (0, 0, 0));
		
		// Prepare all colours
		$colors = array ();

		$areaToLoad = MAXMAPSTRAAL + (MAXMAPSTRAAL * 0.1);

		// The whole wide world:
		$sizeX = $areaToLoad;
		$sizeY = $areaToLoad;

		$zoom = max (0, $zoom);
		$zoom = pow (2, $zoom);

		$dx = (($areaToLoad * 2) / $zoom) / $width;
		$dy = (($areaToLoad * 2) / $zoom) / $height;

		$startX = ((($areaToLoad * 2) / $zoom) * $x) - $areaToLoad;
		$startY = ((($areaToLoad * 2) / $zoom) * $y) - $areaToLoad;

		// Show all these pixels
		for ($i = 0; $i < $width; $i ++)
		{
			for ($j = 0; $j < $height; $j ++)
			{
				$lx = round ($startX + ($dx * $i));
				$ly = round ($startY + ($dy * $j));

				$location = Dolumar_Map_Map::getLocation ($lx, $ly, false, false);

				$px = $i;
				$py = $height - $j;

				$c = $location->getHeightIntencity ();
				$col = $location->getMapColor ();

				$col[0] = floor ($col[0] * $c);
				$col[1] = floor ($col[1] * $c);
				$col[2] = floor ($col[2] * $c);
			
				$color = color_cache ($im, $col);
			
				imagesetpixel ($im, $i, $j, $color);
			}
		}

		$locations = array
		(
			array
			(
				$startX + 125,
				$startY + 125
			)
		);

		// Load buildings from SQL
		/*
		$buildingSQL = Dolumar_Map_Map::getBuildingsFromLocations ($locations, 125);


		
		foreach ($buildingSQL as $buildingV)
		{

			
			$x = floor ($buildingV['xas']);
			$y = floor ($buildingV['yas']);
			
			$color = color_cache ($im, array (255, 0, 0));

			$tx = $x - $startX;
			$ty = $height - ($y - $startY);
			
			imagesetpixel ($im, $tx, $ty, $color);
		}
		*/
		
		// Start output buffering
		ob_start ();

		//imagestring ($im, 2, 10, 10, $_GET['x'] . "." . $_GET['y'], color_cache ($im, array (255, 255, 255)));
		
		if ($extension == 'gif')
		{
			imagegif ($im);
		}
		
		else
		{			
			imagepng ($im, null);
		}
		
		imagedestroy($im);
		
		// Fetch thze output & flush it down the toilet
		header("Content-type: image/".$extension);
		header("Expires: " . gmdate("D, d M Y H:i:s", time() + 60*60*12) . " GMT");
		header("Cache-Control: max-age=315360000");
		
		$output = ob_get_flush();		
		$cache->setCache ($cachename, $output);
	}
}
?>
