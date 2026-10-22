<?php

use PHPUnit\Framework\TestCase;

class GameServerAssetRouteTest extends TestCase
{
	public function testKnownAssetResolves(): void
	{
		$path = Neuron_GameServer::resolveAssetPath(['css', 'admin.css']);
		$this->assertNotNull($path);
		$this->assertStringEndsWith('assets/css/admin.css', $path);
		$this->assertFileExists($path);
	}

	public function testTraversalIsRejected(): void
	{
		$this->assertNull(Neuron_GameServer::resolveAssetPath(['..', 'composer.json']));
		$this->assertNull(Neuron_GameServer::resolveAssetPath(['..', '..', '..', '..', '..', '..', 'etc', 'hostname']));
		$this->assertNull(Neuron_GameServer::resolveAssetPath(['css', '..', '..', 'composer.json']));
	}

	public function testMissingFileOrDirectoryIsNull(): void
	{
		$this->assertNull(Neuron_GameServer::resolveAssetPath(['css', 'nope.css']));
		$this->assertNull(Neuron_GameServer::resolveAssetPath(['css']));
		$this->assertNull(Neuron_GameServer::resolveAssetPath([]));
	}
}
