<?php

use PHPUnit\Framework\TestCase;

class MemcacheTest extends TestCase
{
	public function test_parse_plain_host ()
	{
		$this->assertSame ([['localhost', 11211]], Neuron_Core_Memcache::parseServers ('localhost'));
	}

	public function test_parse_url ()
	{
		$this->assertSame (
			[['dokku-memcached-dolumar', 11211]],
			Neuron_Core_Memcache::parseServers ('memcached://dokku-memcached-dolumar:11211')
		);
	}

	public function test_parse_list ()
	{
		$this->assertSame ([['a', 1], ['b', 11211]], Neuron_Core_Memcache::parseServers ('a:1,b'));
	}

	public function test_unreachable_server_degrades_to_no_cache ()
	{
		$cache = Neuron_Core_Memcache::forServers ('127.0.0.1:1');

		$this->assertFalse ($cache->getCache ('x'));
		$this->assertFalse ($cache->hasCache ('x'));
		$cache->setCache ('x', 1);
		$this->assertFalse ($cache->getCache ('x'));
	}

	public function test_working_server_stores_values ()
	{
		$cache = Neuron_Core_Memcache::forServers ('memcached://memcached:11211');
		$key = 'test_' . uniqid ();

		$this->assertFalse ($cache->hasCache ($key));
		$cache->setCache ($key, 'value');
		$other = Neuron_Core_Memcache::forServers ('memcached:11211');
		$this->assertSame ('value', $other->getCache ($key));
		$other->removeCache ($key);
	}
}
