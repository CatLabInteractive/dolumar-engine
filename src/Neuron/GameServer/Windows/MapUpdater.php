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

/*
	Windows_MapUpdater
	
	This (invisible) window will make sure the map is up to date.
*/
class Neuron_GameServer_Windows_MapUpdater extends Neuron_GameServer_Windows_Window
{	
	public function setSettings ()
	{
		$this->setClassName ('invisible');
		$this->setType ('invisible');
		$this->setAllowOnlyOnce ();

		//$this->setPool ('mapupdater');
		
		$this->setAjaxPollSeconds (5);
	}
	
	public function getContent ()
	{
		$data = $this->getRequestData ();
		return '<p>'.print_r ($data, true).'</p>';
	}
	
	private function getInitialLogID ()
	{
		$db = Neuron_DB_Database::getInstance ();
		
		$last = $db->query
		("
			SELECT
				COALESCE(MAX(mu_id), 0) AS laatste
			FROM
				n_map_updates
		");
		
		return $last[0]['laatste'];
	}
	
	public function processInput ()
	{
		$this->getRefresh ();
	}
	
	public function getRefresh ()
	{
		$data = $this->getRequestData ();
		
		if (!isset ($data['lastlog']))
		{
			$data['lastlog'] = $this->getInitialLogID ();
		}
		else
		{
			// Update all map areas that have been changed
			$db = Neuron_DB_Database::getInstance ();
			
			$ll = intval ($data['lastlog']);
			
			$q = $db->query
			("
				SELECT
					*
				FROM
					n_map_updates
				WHERE
					mu_id > {$ll}
			");
			
			foreach ($q as $v)
			{
				//$this->alert ('reloading ' . $v['mu_x'] . ',' . $v['mu_y']);
			
				$this->reloadLocation ($v['mu_x'], $v['mu_y']);
				
				if ($v['mu_id'] > $data['lastlog'])
				{
					$data['lastlog'] = $v['mu_id'];
				}
			}
		}
		
		$this->updateRequestData ($data);
		//$this->updateContent ();
	}
}
?>
