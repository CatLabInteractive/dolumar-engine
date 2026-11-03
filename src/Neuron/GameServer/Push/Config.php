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
	Web Push configuration.

	Push is only on when the game defines VAPID_PUBLIC_KEY and VAPID_PRIVATE_KEY.
	Without them nothing is queued, no route accepts subscriptions and the
	client never asks for permission: the game behaves exactly as before.
*/
class Neuron_GameServer_Push_Config
{
	public static function isEnabled ()
	{
		return self::getPublicKey () !== null && self::getPrivateKey () !== null;
	}

	public static function getPublicKey ()
	{
		return self::getConstant ('VAPID_PUBLIC_KEY');
	}

	private static function getPrivateKey ()
	{
		return self::getConstant ('VAPID_PRIVATE_KEY');
	}

	/*
		The contact push services use when something is wrong with our pushes.
		Must be a mailto: or https: URL.
	*/
	public static function getSubject ()
	{
		$subject = self::getConstant ('VAPID_SUBJECT');
		if ($subject !== null)
		{
			return $subject;
		}

		if (defined ('ABSOLUTE_URL') && strpos (ABSOLUTE_URL, 'https://') === 0)
		{
			return ABSOLUTE_URL;
		}

		return 'mailto:support@catlab.be';
	}

	/*
		Auth array in the format minishlink/web-push expects.
	*/
	public static function getAuth ()
	{
		return array
		(
			'VAPID' => array
			(
				'subject' => self::getSubject (),
				'publicKey' => self::getPublicKey (),
				'privateKey' => self::getPrivateKey ()
			)
		);
	}

	private static function getConstant ($name)
	{
		if (!defined ($name))
		{
			return null;
		}

		$value = trim ((string) constant ($name));
		return $value === '' ? null : $value;
	}
}
