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
* Turns moving map objects into the plain array that map/objects sends
* to the client as JSON, hiding everything an unrelated viewer may not
* learn: real ids, kinds, names, click actions and any path outside the
* current poll window.
*/
class Neuron_GameServer_Map_MovingObjectsExporter
{
	const NEUTRAL_KIND = 'neutral';

	private $secret;
	private $poll;

	/**
	* @param string $secret per-deploy salt for anonymous ids
	* @param int $poll client poll interval in seconds
	*/
	public function __construct ($secret, $poll = 30)
	{
		$this->secret = (string) $secret;
		$this->poll = intval ($poll);
	}

	/**
	* @param Neuron_GameServer_Map_MapObject[] $objects
	* @param mixed $viewer the player looking at the map, or null
	* @param int $now server time
	* @return array with keys servertime, poll, objects
	*/
	public function export (array $objects, $viewer, $now)
	{
		$now = intval ($now);

		$out = array ();
		foreach ($objects as $object)
		{
			try
			{
				$out[] = $this->exportObject ($object, $viewer, $now);
			}
			catch (Throwable $e)
			{
				error_log ('MovingObjectsExporter: skipping object: ' . $e->getMessage ());
			}
		}

		return array
		(
			'servertime' => $now,
			'poll' => $this->poll,
			'objects' => $out
		);
	}

	/**
	* Opaque, per-deploy stable id for an object an unrelated viewer sees.
	*/
	public function anonymousId ($uoid)
	{
		return 'a:' . substr (sha1 ($this->secret . $uoid), 0, 12);
	}

	private function exportObject (Neuron_GameServer_Map_MapObject $object, $viewer, $now)
	{
		$location = $object->getLocation ($now);
		$related = $object->isRelatedTo ($viewer);
		$display = $related ? $object->getDisplayObject () : $object->getNeutralDisplayObject ();

		$data = array
		(
			'x' => (float) $location->x (),
			'y' => (float) $location->y (),
			'sprite' => $display->getDisplayData ()
		);

		if ($related)
		{
			$data['id'] = $object->getUOID ();
			$data['kind'] = $object->getKind ();
			$data['name'] = $object->getName ();
			$data['click'] = implode (' ', $object->getEvents ('click'));

			$paths = array ();
			foreach ($object->getMovements () as $movement)
			{
				$paths[] = $movement->getPathData ();
			}
			$data['paths'] = $paths;
		}
		else
		{
			$data['id'] = $this->anonymousId ($object->getUOID ());
			$data['kind'] = self::NEUTRAL_KIND;
			$data['paths'] = array ();

			foreach ($object->getMovements () as $movement)
			{
				if ($movement->isActive ($now))
				{
					$data['paths'][] = $movement->truncate ($now, $now + $this->poll)->getPathData ();
					break;
				}
			}
		}

		return $data;
	}
}
