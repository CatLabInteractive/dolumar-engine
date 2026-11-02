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

class Neuron_GameServer_Pages_Statistics extends Neuron_GameServer_Pages_Page
{
	public function getBody ()
	{
		$player = Neuron_GameServer::getPlayer (Neuron_Core_Tools::getInput ('_GET', 'id', 'int'));

		$statistics = $player->getStatistics ();
		$out = $this->linearArray ($statistics);

		$page = new Neuron_Core_Template ();
		$page->set ('statistics', $out);
		return $page->parse ('gameserver/pages/statistics.phpt');
	}

	private function linearArray ($statistics, $out = array (), $keyprefix = '')
	{
		foreach ($statistics as $k => $v)
		{
			if (is_array ($v))
			{
				$out = $this->linearArray ($v, $out, $k . '_');
			}
			else
			{
				$out[$keyprefix . $k] = $v;
			}
		}
		return $out;
	}
}