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
 * The native Android/iOS app (mobile/ in the game repository) loads the game
 * in a WebView and appends "DolumarApp/<version> (<platform>)" to the user
 * agent. The app's own pages (world picker, store) live on a local origin:
 * https://localhost on Android, app://localhost on iOS.
 */
class Neuron_GameServer_App
{
	const ORIGIN_ANDROID = 'https://localhost';
	const ORIGIN_IOS = 'app://localhost';

	/**
	 * True when the request comes from the app's WebView.
	 */
	public static function isApp (?string $userAgent = null): bool
	{
		return self::parse ($userAgent) !== null;
	}

	/**
	 * 'android', 'ios', or null when the app did not say (first app builds) or
	 * the request is not from the app.
	 */
	public static function getPlatform (?string $userAgent = null): ?string
	{
		$app = self::parse ($userAgent);
		return $app === null ? null : $app['platform'];
	}

	/**
	 * Origin of the app's bundled pages. Android when the platform is unknown.
	 */
	public static function getOrigin (?string $userAgent = null): string
	{
		return self::getPlatform ($userAgent) === 'ios' ? self::ORIGIN_IOS : self::ORIGIN_ANDROID;
	}

	/**
	 * Origins a bundled app page may call the api from (CORS).
	 *
	 * @return string[]
	 */
	public static function getOrigins (): array
	{
		return [self::ORIGIN_ANDROID, self::ORIGIN_IOS];
	}

	/**
	 * @return array{version:string, platform:?string}|null
	 */
	private static function parse (?string $userAgent): ?array
	{
		if ($userAgent === null)
		{
			$userAgent = isset ($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
		}

		if (!preg_match ('#(?:^|\s)DolumarApp/([0-9][0-9.]*)(?:\s*\((android|ios)\))?#i', $userAgent, $m))
		{
			return null;
		}

		return ['version' => $m[1], 'platform' => isset ($m[2]) ? strtolower ($m[2]) : null];
	}
}
