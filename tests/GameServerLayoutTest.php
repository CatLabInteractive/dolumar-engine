<?php

use PHPUnit\Framework\TestCase;

class GameServerLayoutTest extends TestCase
{
	public function testQueryOverridesStored(): void
	{
		$this->assertSame('mobile', Neuron_GameServer::resolveLayoutPreference('desktop', 'mobile'));
	}

	public function testStoredIsUsedWithoutQuery(): void
	{
		$this->assertSame('auto', Neuron_GameServer::resolveLayoutPreference('auto', null));
	}

	public function testUnknownValuesFallBackToDefault(): void
	{
		$this->assertSame('desktop', Neuron_GameServer::resolveLayoutPreference('foo', 'bar'));
		$this->assertSame('desktop', Neuron_GameServer::resolveLayoutPreference(null, null));
		$this->assertSame('mobile', Neuron_GameServer::resolveLayoutPreference('mobile', 'x'));
	}
}
