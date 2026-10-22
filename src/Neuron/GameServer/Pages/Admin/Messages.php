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

class Neuron_GameServer_Pages_Admin_Messages extends Neuron_GameServer_Pages_Admin_Page
{
	public function getBody ()
	{
		$function = function ($data, $title)
		{
			$query = "";
			foreach ($data as $k => $v)
			{
				$query .= $k . "=" . urlencode ($v) . "&";
			}
			$query = substr ($query, 0, -1);

			if (is_array ($title))
			{
				return $title[0].'<a href="'.ABSOLUTE_URL.'admin/messages/?'.$query.'">'.$title[1].'</a>'.$title[2];
			}
			else
			{
				return '<a href="'.ABSOLUTE_URL.'admin/messages/?'.$query.'">'.$title.'</a>';
			}
		};

		$function2 = function ($userid, $title)
		{
			return '<a href="'.ABSOLUTE_URL.'admin/user/?id='.$userid.'">'.$title.'</a>';
		};

		$objMessages = new Neuron_Structure_Messages (Neuron_GameServer::getPlayer (), 25);
		
		$objMessages->setGetUrl ($function);
		$objMessages->setUserUrl ($function2);
		
		return $objMessages->getPageHTML ($_GET);
	}
}
?>
