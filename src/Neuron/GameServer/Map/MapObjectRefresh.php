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
*	Per-request flag: something changed the moving objects (an army was sent or
*	recalled), so the client should ask map/objects now instead of on its next
*	poll. Window::getDOMRefresh() takes it and sends a refreshMapObjects update.
*/
class Neuron_GameServer_Map_MapObjectRefresh
{
	private static $requested = false;

	public static function request ()
	{
		self::$requested = true;
	}

	/** @return bool whether a refresh was requested since the last take() */
	public static function take ()
	{
		$out = self::$requested;
		self::$requested = false;
		return $out;
	}
}
