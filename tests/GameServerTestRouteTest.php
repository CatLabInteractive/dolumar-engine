<?php

use PHPUnit\Framework\TestCase;

class GameServerTestRouteTest extends TestCase
{
	public function testTraversalIsRejected(): void
	{
		$this->assertNull(Neuron_GameServer::resolveTestScript('../../../../etc/passwd'));
		$this->assertNull(Neuron_GameServer::resolveTestScript('../tests/giveresources'));
		$this->assertNull(Neuron_GameServer::resolveTestScript('giveresources/../giveresources'));
		$this->assertNull(Neuron_GameServer::resolveTestScript("giveresources\0"));
		$this->assertNull(Neuron_GameServer::resolveTestScript('GiveResources'));
	}

	public function testKnownScriptResolves(): void
	{
		$path = Neuron_GameServer::resolveTestScript('giveresources');
		$this->assertNotNull($path);
		$this->assertStringEndsWith('tests/giveresources.php', $path);
		$this->assertFileExists($path);
		$this->assertSame($path, realpath($path));
	}

	public function testUnknownOrEmptyIsNull(): void
	{
		$this->assertNull(Neuron_GameServer::resolveTestScript('nope'));
		$this->assertNull(Neuron_GameServer::resolveTestScript(''));
		$this->assertNull(Neuron_GameServer::resolveTestScript(null));
	}
}
