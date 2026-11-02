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

class Neuron_GameServer_View_SelectLocationAction
{
	private $sendformdata = false;
	private $value;
	private $sprite;

	public function __construct ($data, $value, ?Neuron_GameServer_Map_Display_Sprite $sprite = null)
	{
		$this->value = $value;
		if (isset ($sprite))
		{
			$this->setSprite ($sprite);
		}
	}
	
	public function setSendFormData ()
	{
		$this->sendformdata = true;
	}
	
	public function setSprite (Neuron_GameServer_Map_Display_Sprite $sprite)
	{
		$this->sprite = $sprite;
	}
	
	public function getAction ()
	{
		$data = htmlentities (json_encode ($this->data), ENT_COMPAT);
		
		$image = null;
		if ($this->sprite)
			$image = htmlentities (json_encode ($this->sprite), ENT_COMPAT);
		
		$sendformdata = $this->sendformdata ? 'true' : 'false';
	
		return 'selectLocation (this, '.$data.', '.$sendformdata.', '.$image.');';
	}
	
	public function getHTML ()
	{
		$data = htmlentities (json_encode ($this->data), ENT_COMPAT);
		
		$image = null;
		if ($this->sprite)
			$image = htmlentities (json_encode ($this->sprite->getDisplayData ()), ENT_COMPAT);
		
		$sendformdata = $this->sendformdata ? 'true' : 'false';
	
		return '<a href="javascript:void(0);" ' .
			'onclick="'.$this->getAction ().'">' . $this->value . '</a>';
	}
	
	public function __toString ()
	{
		return $this->getHTML ();
	}
}
?>
