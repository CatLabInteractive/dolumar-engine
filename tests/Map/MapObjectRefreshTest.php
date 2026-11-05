<?php

use PHPUnit\Framework\TestCase;

class MapObjectRefreshTest extends TestCase
{
	protected function setUp(): void
	{
		Neuron_GameServer_Map_MapObjectRefresh::take();
	}

	public function testNothingRequestedByDefault(): void
	{
		$this->assertFalse(Neuron_GameServer_Map_MapObjectRefresh::take());
	}

	public function testARequestIsTakenOnce(): void
	{
		Neuron_GameServer_Map_MapObjectRefresh::request();
		Neuron_GameServer_Map_MapObjectRefresh::request();
		$this->assertTrue(Neuron_GameServer_Map_MapObjectRefresh::take());
		$this->assertFalse(Neuron_GameServer_Map_MapObjectRefresh::take());
	}
}
