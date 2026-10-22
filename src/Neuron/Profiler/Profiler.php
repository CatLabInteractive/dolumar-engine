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

if (!defined ('USE_PROFILE'))
{
	define ('USE_PROFILE', false);
}

class Neuron_Profiler_Profiler
{
	public static function __getInstance ($forceusage = false)
	{
		static $in;
		if (!isset ($in))
		{
			$in = new self ();
		}
		
		if ($forceusage)
		{
			$in->setForceActivate ($forceusage);
		}
		
		return $in;
	}
	
	public static function getInstance ($forceusage = false)
	{
		return self::__getInstance ($forceusage);
	}
	
	private $oRoot;
	private $oCurAction;
	private $active = false;
	
	public function __construct ()
	{
		$this->oRoot = new Neuron_Profiler_Action ('Initializing profiler', null);
		$this->oCurAction = $this->oRoot;
		$this->active = USE_PROFILE;
	}
	
	public function setForceActivate ($activate = true)
	{
		$this->active = true;
	}
	
	public function start ($sAction)
	{
		if ($this->active)
		{
			// Make new action
			$oAction = new Neuron_Profiler_Action ($sAction, $this->oCurAction);
		
			// Append action
			$this->oCurAction->appendChild ($oAction);
		
			// Set current action
			$this->oCurAction = $oAction;
		}
	}
	
	public function stop ()
	{
		if ($this->active)
		{
			$this->oCurAction->stop ();
			$this->oCurAction = $this->oCurAction->getParent ();
		}
	}
	
	public function message ($sMessage)
	{
		$this->start ($sMessage);
		$this->stop ();
	}
	
	public function getTotalDuration ()
	{
		return $this->oRoot->getDuration ();
	}
	
	public function getOutput ()
	{
		return (string)$this->oCurAction;
	}
	
	public function __toString ()
	{
		return $this->getOutput ();
	}
}
?>
