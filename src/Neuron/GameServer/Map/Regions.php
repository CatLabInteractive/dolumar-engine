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

/**
* Helpers to turn the client's map region list (as posted to map/region
* and map/objects) into areas, using the same maths as jsonmap.php.
*/
class Neuron_GameServer_Map_Regions
{
	/**
	* @param array $regions list of array (x, y) region coordinates
	* @param int $tilesPerRegion
	* @return Neuron_GameServer_Map_Area[]
	*/
	public static function toAreas ($regions, $tilesPerRegion)
	{
		$switchpoint = intval ($tilesPerRegion);

		$out = array ();
		foreach ($regions as $v)
		{
			$x = intval ($v[0]);
			$y = intval ($v[1]);

			$location = new Neuron_GameServer_Map_Location
			(
				(($x + $y) * $switchpoint) + ($switchpoint / 2),
				(($x - $y) * $switchpoint) - ($switchpoint / 2)
			);

			$out[] = new Neuron_GameServer_Map_Area ($location, $switchpoint * 2);
		}

		return $out;
	}
}
