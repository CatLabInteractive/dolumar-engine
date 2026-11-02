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

class Neuron_GameServer_Map_Movement
{
	private $startTime;
	private $endTime;

	private $locations;

	private $acceleration = 0;
	private $startSpeed = 0;

	private $startRotation = null;
	private $endRotation = null;

	private $startUp = null;
	private $endUp = null;

	/**
	* A bezier curved movement description
	* @param $startTime: Start timestamp
	* @param $endTime: End timestamp
	* @param $more: locations
	*/
	public function __construct 
	(
		$startTime, 
		$endTime, 
		Neuron_GameServer_Map_Location $l1, 
		Neuron_GameServer_Map_Location $l2
	)
	{
		$arguments = func_get_args ();

		$this->setStartTime (array_shift ($arguments));
		$this->setEndTime (array_shift ($arguments));
		
		$this->setPoints ($arguments);
	}

	protected function setStartTime ($startTime)
	{
		$this->startTime = $startTime;
	}

	protected function setEndTime ($endTime)
	{
		$this->endTime = $endTime;
	}

	protected function setPoints ($arguments)
	{
		if (count ($arguments) < 2)
		{
			throw new Exception ("Path must have at least two points.");	
		}
		else if (count ($arguments) > 4)
		{
			throw new Exception ("The maximal amount of points is 4 right now.");
		}

		$this->locations = $arguments;	
	}

	public static function getFromPath ($startTime, $endTime, $points)
	{
		$path = array ();
		foreach ($points as $v)
		{
			$path[] = new Neuron_GameServer_Map_Location ($v[0], $v[1], $v[2]);
		}

		if (count ($path) < 2)
		{
			throw new Exception ("Path must have at least two points.");
		}

		else if (count ($path) === 2)
		{
			return new self ($startTime, $endTime, $path[0], $path[1]);
		}

		else if (count ($path) === 3)
		{
			return new self ($startTime, $endTime, $path[0], $path[1], $path[2]);
		}

		else if (count ($path) === 4)
		{
			return new self ($startTime, $endTime, $path[0], $path[1], $path[2], $path[3]);
		}

		else
		{
			throw new Exception ("The maximal amount of points is 4 right now.");
		}
	}

	public function getStartTime ()
	{
		return $this->startTime;
	}

	public function getEndTime ()
	{
		return $this->endTime;
	}

	public function isActive ($time = NOW)
	{
		return $this->startTime <= $time && $this->endTime > $time;
	}

	private function calcPositionFromVelocity ($progress)
	{
		// A movement that does not go anywhere has no velocity to speak of.
		if ($this->getDistance () == 0)
		{
			return $progress;
		}

		$progress *= $this->getDuration ();
		$speed = $this->calculateSpeed ();
		return (( $speed['start'] * $progress) + (0.5 * $speed['acceleration'] * $progress * $progress)) / $this->getDistance ();
	}

	private function calcPositionFromBezierCurve ($t)
	{
		$u = (1 - $t);

		$location = array (0, 0, 0);
		if (count ($this->locations) == 2)
		{
			for ($i = 0; $i <= 2; $i ++)	
			{
				$location[$i] = ($u * $this->locations[0][$i]) 
					+ ($t * $this->locations[1][$i]);
			}
		}

		else if (count ($this->locations) == 3)
		{
			$uu = $u * $u;
			$tt = $t * $t;

			for ($i = 0; $i <= 2; $i ++)	
			{
				$location[$i] = ($uu * $this->locations[0][$i]) 
					+ (2 * $u * $t * $this->locations[1][$i]) 
					+ ($tt * $this->locations[2][$i]);
			}
		}

		else if (count ($this->locations) == 4)
		{
			$uu = $u * $u;
			$tt = $t * $t;
			$uuu = $uu * $u;
			$ttt = $tt * $t;

			for ($i = 0; $i <= 2; $i ++)	
			{
				$location[$i] = ($uuu * $this->locations[0][$i]) 
					+ (3 * $uu * $t * $this->locations[1][$i]) 
					+ (3 * $u * $tt * $this->locations[2][$i]) 
					+ ($ttt * $this->locations[3][$i]);
			}
		}

		return new Neuron_GameServer_Map_Location ($location[0], $location[1], $location[2]);;
	}

	public function getCurrentLocation ($time = NOW)
	{
		$diff = $this->endTime - $this->startTime;
		if ($diff <= 0)
		{
			return $this->getEndLocation ();
		}

		$percentage = ($time - $this->startTime) / $diff;

		//customMail ('daedelson@gmail.com', 'coortest', $percentage);
		$percentage = $this->calcPositionFromVelocity ($percentage);

		return $this->calcPositionFromBezierCurve ($percentage);
	}

	public function getCurrentUp ($time = NOW)
	{
		if (isset ($this->startUp) && isset ($this->endUp))
		{
			$diff = $this->endTime - $this->startTime;
			$percentage = ($time - $this->startTime) / $diff;

			$rotation = $this->startUp->transform ($this->endUp->transform ($this->startUp->inverse ())->scale ($percentage));
			return $rotation->normalize ();
		}
		else
		{
			return null;
		}
	}

	public function getCurrentDirection ($time = NOW)
	{
		if (isset ($this->startRotation) && isset ($this->endRotation))
		{
			$diff = $this->endTime - $this->startTime;
			$percentage = ($time - $this->startTime) / $diff;

			$rotation = $this->startRotation->transform ($this->endRotation->transform ($this->startRotation->inverse ())->scale ($percentage));
			return $rotation->normalize ();
		}

		else
		{
			$t1 = $this->getCurrentLocation ($time);
			$t2 = $this->getCurrentLocation ($time + 0.001);

			return $t2->transform ($t1)->normalize ();
		}
	}

	public function getStartLocation ()
	{
		$values = array_values ($this->locations);
		return array_shift ($values);
	}

	public function getEndLocation ()
	{
		$values = array_values ($this->locations);
		return array_pop ($values);
	}

	public function getDuration ()
	{
		return $this->endTime - $this->startTime;	
	}

	public function getDistance ()
	{
		// TODO! Fix this, currently we just calculate the distance between the start & end point
		return $this->getEndLocation ()->getDistance ($this->getStartLocation ());
	}

	public function setAcceleration ($startSpeed, $acceleration)
	{
		$this->startSpeed = $startSpeed;
		$this->acceleration = $acceleration;
	}

	public function getInitialSpeed ()
	{
		$a = $this->calculateSpeed ();
		return $a['start'];
	}

	public function getAcceleration ()
	{
		$a = $this->calculateSpeed ();
		return $a['acceleration'];
	}

	/**
	* Return an array of speed & acceleration
	* Speed is defined in pixels / second
	*/
	private function calculateSpeed ()
	{
		$startSpeed;
		$endSpeed;
		$acceleration = 0;

		// Accelerated speed
		if (abs ($this->acceleration) > 0)
		{
			$startSpeed = $this->startSpeed;
			$acceleration = $this->acceleration;
			$endSpeed = $this->startSpeed + ($acceleration * $this->getDuration ());
		}

		// Constant velocity
		else
		{
			$startSpeed = $this->getDistance () / $this->getDuration ();
			$endSpeed = $startSpeed;
		}

		return array 
		(
			'start' => $startSpeed,
			'end' => $endSpeed,
			'acceleration' => $acceleration
		);
	}

	/**
	* Return a new straight movement covering only [$from, $to],
	* clamped to this movement's own time range.
	*/
	public function truncate ($from, $to)
	{
		$from = max ($this->startTime, $from);
		$to = min ($this->endTime, $to);

		return new self
		(
			$from,
			$to,
			$this->getCurrentLocation ($from),
			$this->getCurrentLocation ($to)
		);
	}

	/**
	* Plain array for JSON export: start, end and the path points.
	*/
	public function getPathData ()
	{
		$points = array ();
		foreach ($this->locations as $v)
		{
			$points[] = array ((float) $v->x (), (float) $v->y ());
		}

		return array
		(
			'start' => $this->startTime,
			'end' => $this->endTime,
			'points' => $points
		);
	}

	public function getPath ()
	{
		$out = array ();

		foreach ($this->locations as $v)
		{
			$out[] = $v->getData (false);
		}

		return $out;
	}

	/**
	* If no rotation is set, object will face in direction of movement
	*/
	public function setDirection (Neuron_GameServer_Map_Vector3 $start, Neuron_GameServer_Map_Vector3 $end)
	{
		$this->startRotation = $start;
		$this->endRotation = $end;
	}

	/**
	* Set "UP" vector that will be interpolated
	*/
	public function setUp (Neuron_GameServer_Map_Vector3 $start, Neuron_GameServer_Map_Vector3 $end)
	{
		$this->startUp = $start->normalize ();
		$this->endUp = $end->normalize ();
	}

	public function getStartUp ()
	{
		return $this->startUp;
	}

	public function getEndUp ()
	{
		return $this->endUp;
	}

	public function getData ()
	{
		$out = array ();

		$out['attributes'] = array
		(
			'duration' => $this->getDuration (),
			'distance' => $this->getDistance ()
		);

		$start = $this->getStartLocation ()->getData ();
		$start['timestamp'] = $this->getStartTime ();

		$end = $this->getEndLocation ()->getData ();
		$end['timestamp'] = $this->getEndTime ();

		$out['start'] = array  ( 'attributes' => $start );
		$out['end'] = array ( 'attributes' => $end );

		$path = array ();
		foreach ($this->locations as $v)
		{
			$path[] = array ('attributes' => $v->getData (true));
		}

		$out['points'] = $path;

		$out['velocity'] = array 
		(
			'attributes' => $this->calculateSpeed ()
		);

		if (isset ($this->startRotation) && isset ($this->endRotation))
		{
			$out['direction'] = array 
			(
				'start' => array ('attributes' => $this->startRotation->getData ()),
				'end' => array ('attributes' => $this->endRotation->getData ())
			);
		}

		if (isset ($this->startUp) && isset ($this->endUp))
		{
			$out['up'] = array
			(
				'start' => array ('attributes' => $this->startUp->getData ()),
				'end' => array ('attributes' => $this->endUp->getData ())
			);
		}

		return $out;
	}
}