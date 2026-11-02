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

abstract class Neuron_GameServer_Map_Managers_MapObjectManager
{
	/**
	*	Return all display objects that are within $radius
	*	of $location (so basically loading a bubble)
	*/
	public abstract function getDisplayObjects (Neuron_GameServer_Map_Area $area);
	
	/**
	* Called once in a while. Used to remove old objects.
	*/
	public function clean ()
	{

	}

	/**
	*	Move an object to another position
	*/	
	public function move 
	(
		Neuron_GameServer_Map_MapObject $object,
		Neuron_GameServer_Map_Location $location,
		Neuron_GameServer_Map_Date $start, 
		Neuron_GameServer_Map_Date $end
	)
	{
		throw new Neuron_Exceptions_NotImplemented ("The move method is not implemented in this map.");
	}
	
	/**
	*	Return the moving objects (armies, ...) that touch any of $areas.
	*	$viewer is the player looking at the map (or null); games may use it
	*	to decide what the object reveals (see MapObject::isRelatedTo).
	*	Games that have no moving objects keep this default.
	*
	*	@param Neuron_GameServer_Map_Area[] $areas
	*	@return Neuron_GameServer_Map_MapObject[]
	*/
	public function getMovingObjects (array $areas, $viewer = null)
	{
		return array ();
	}

	/**
	*	Return all objects on one specific location
	*/
	public function getFromLocation (Neuron_GameServer_Map_Location $location)
	{
		$area = new Neuron_GameServer_Map_Area ($location, 1);
		
		$objects = $this->getDisplayObjects ($area);
		$out = array ();
		
		foreach ($objects as $v)
		{
			if ($v->getLocation ()->equals ($location))
			{
				$out[] = $v;
			}
		}
		
		return $out;
	}

	public function getFromUOID ($id)
	{
		return Neuron_GameServer_Mappers_IDMapper::getObject ($id);
	}
	
	/**
	*	Load all objects with the radius of the points
	*	This method combines a set of getDisplayObjects calls
	*	
	*	$requests contains an array of boundaries
	*
	*	Overload this method to increase performance
	*/	
	public function getMultipleDisplayObjects ($areas)
	{
		$out = array ();
		foreach ($areas as $v)
		{
			if (! ($v instanceof Neuron_GameServer_Map_Area))
			{
				throw new Neuron_Exceptions_InvalidParameter ("Parameters must be an array of area objects.");
			}
		
			foreach ($this->getDisplayObjects ($v) as $v)
			{
				if (! $v instanceof Neuron_GameServer_Map_MapObject)
				{
					throw new Neuron_Core_Error ("All map objects MUST implement Neuron_GameServer_Map_MapObject");
				}
				
				$out[] = $v;
			}
		}
		
		return $out;
	}
}
?>
