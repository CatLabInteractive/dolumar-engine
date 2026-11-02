<?php

use PHPUnit\Framework\TestCase;

class RegionsTest extends TestCase
{
	public function testRegionZeroMapsToJsonmapCentre(): void
	{
		$areas = Neuron_GameServer_Map_Regions::toAreas([[0, 0]], 10);
		$this->assertCount(1, $areas);
		$this->assertEqualsWithDelta(5.0, $areas[0]->getCenter()->x(), 0.001);
		$this->assertEqualsWithDelta(-5.0, $areas[0]->getCenter()->y(), 0.001);
		$this->assertEquals(20, $areas[0]->getRadius());
	}

	public function testRegionOneZero(): void
	{
		$areas = Neuron_GameServer_Map_Regions::toAreas([[1, 0]], 10);
		$this->assertEqualsWithDelta(15.0, $areas[0]->getCenter()->x(), 0.001);
		$this->assertEqualsWithDelta(5.0, $areas[0]->getCenter()->y(), 0.001);
	}

	public function testCacheKeyIsOrderIndependent(): void
	{
		$a = Neuron_GameServer_Map_Regions::cacheKey([[1, 0], [0, 0]], 10);
		$b = Neuron_GameServer_Map_Regions::cacheKey([[0, 0], [1, 0]], 10);
		$this->assertSame($a, $b);
		$this->assertSame('movingobjects:10:0,0;1,0', $a);
	}

	public function testManagerDefaultHasNoMovingObjects(): void
	{
		$m = new class extends Neuron_GameServer_Map_Managers_MapObjectManager {
			public function getDisplayObjects (Neuron_GameServer_Map_Area $area) { return array (); }
		};
		$this->assertSame([], $m->getMovingObjects([], null));
	}
}
