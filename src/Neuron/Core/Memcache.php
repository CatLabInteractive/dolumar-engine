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

class Neuron_Core_Memcache
{
	public static function getInstance ()
	{
		static $in;
		if (!isset ($in))
		{
			$in = new self ();
		}
		return $in;
	}
	
	private $objCache;
	
	// Small cache to speed up issues
	private $sLastCache;
	private $sLastValue;
	
	/**
	 * Parse a server list: "host", "host:port", "memcached://host:port",
	 * or comma-separated combinations of those.
	 *
	 * @return list<array{0:string,1:int}>
	 */
	public static function parseServers (string $servers): array
	{
		$out = [];
		foreach (explode (',', $servers) as $s)
		{
			$s = trim ($s);
			if ($s === '')
			{
				continue;
			}

			$s = preg_replace ('#^memcached://#i', '', $s);
			$s = rtrim ($s, '/');
			$parts = explode (':', $s);
			$out[] = [$parts[0], isset ($parts[1]) && $parts[1] !== '' ? (int)$parts[1] : 11211];
		}
		return $out;
	}

	/**
	 * Build an instance for an explicit server string (used by tests).
	 */
	public static function forServers (string $servers, ?string $username = null, ?string $password = null): self
	{
		return new self ($servers, $username, $password);
	}

	private function __construct (?string $servers = null, ?string $username = null, ?string $password = null)
	{
		$this->objCache = false;

		if ($servers === null && defined ('MEMCACHE_SERVERS'))
		{
			$servers = MEMCACHE_SERVERS;
			$username = defined ('MEMCACHE_USERNAME') ? MEMCACHE_USERNAME : null;
			$password = defined ('MEMCACHE_PASSWORD') ? MEMCACHE_PASSWORD : null;
		}

		if ($servers === null || !class_exists ('Memcached'))
		{
			return;
		}

		try
		{
			// create a new persistent client (one pool per server list)
			$m = new Memcached ('memcached_' . md5 ($servers));
			$m->setOption (Memcached::OPT_BINARY_PROTOCOL, TRUE);

			// some nicer default options
			$m->setOption (Memcached::OPT_NO_BLOCK, TRUE);
			$m->setOption (Memcached::OPT_AUTO_EJECT_HOSTS, TRUE);
			$m->setOption (Memcached::OPT_CONNECT_TIMEOUT, 2000);
			$m->setOption (Memcached::OPT_POLL_TIMEOUT, 2000);
			$m->setOption (Memcached::OPT_RETRY_TIMEOUT, 2);

			// setup authentication
			if (is_string ($username) && $username !== '' && is_string ($password) && $password !== '')
			{
				$m->setSaslAuthData ($username, $password);
			}

			// We use a persistent connection, so only add the servers the
			// first time through, otherwise connections get duplicated.
			if (!$m->getServerList ())
			{
				foreach (self::parseServers ($servers) as $server)
				{
					$m->addServer ($server[0], $server[1]);
				}
			}

			$this->objCache = $m;
		}
		catch (Exception $e)
		{
			$this->objCache = false;
		}
	}
	
	public function hasCache ($sKey)
	{
		// Check if this object even exists
		if (!$this->objCache)
		{
			return false;
		}
	
		$this->sLastCache = $sKey;
		$this->sLastValue = @$this->objCache->get ($sKey);
		
		return $this->sLastValue !== false;
	}
	
	public function setCache ($sKey, $sContent)
	{
		// Check if this object even exists
		if (!$this->objCache)
		{
			return false;
		}
	
		return @$this->objCache->set ($sKey, $sContent, 0);
	}
	
	public function getCache ($sKey)
	{
		// Check if this object even exists
		if (!$this->objCache)
		{
			return false;
		}
	
		if ($this->sLastCache == $sKey)
		{
			return $this->sLastValue;
		}
		else
		{
			return @$this->objCache->get ($sKey);
		}
	}
	
	public function removeCache ($sKey)
	{
		if (!$this->objCache)
		{
			return false;
		}
		
		//customMail ('daedelson@gmail.com', 'deleting cache '.$sKey, 'test');
		
		$this->objCache->delete ($sKey);
	}
	
	public function flush ()
	{
		// Check if this object even exists
		if (!$this->objCache)
		{
			return false;
		}
		
		//customMail ('daedelson@gmail.com', 'clearing all cache', 'test');
		
		$this->objCache->flush ();
	}
	
	public function __toString ()
	{
		if ($this->objCache)
		{
			return print_r ($this->objCache->getExtendedStats (), true);
		}
		else
		{
			return 'No memcache support configured.';
		}
	}
}
?>
