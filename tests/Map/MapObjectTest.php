<?php

use PHPUnit\Framework\TestCase;

class MapObjectTest extends TestCase
{
	private function object(): Neuron_GameServer_Map_MapObject
	{
		$o = new class extends Neuron_GameServer_Map_MapObject {
			public function getDisplayObject () { return new Neuron_GameServer_Map_Display_Sprite ('x.png'); }
			public function getName () { return 'test'; }
		};
		$o->setLocation(new Neuron_GameServer_Map_Location(0, 0));
		$o->addMovement(new Neuron_GameServer_Map_Movement(1000, 1100,
			new Neuron_GameServer_Map_Location(0, 0), new Neuron_GameServer_Map_Location(10, 0)));
		$o->addMovement(new Neuron_GameServer_Map_Movement(1100, 1200,
			new Neuron_GameServer_Map_Location(10, 0), new Neuron_GameServer_Map_Location(10, 10)));
		return $o;
	}

	public function testLocationFollowsActiveMovement(): void
	{
		$o = $this->object();
		$this->assertEqualsWithDelta(5.0, $o->getLocation(1050)->x(), 0.001);
		$this->assertEqualsWithDelta(5.0, $o->getLocation(1150)->y(), 0.001);
	}

	public function testLocationBeforeFirstMovementIsStart(): void
	{
		$this->assertEqualsWithDelta(0.0, $this->object()->getLocation(500)->x(), 0.001);
	}

	public function testLocationAfterLastMovementIsEnd(): void
	{
		$l = $this->object()->getLocation(5000);
		$this->assertEqualsWithDelta(10.0, $l->x(), 0.001);
		$this->assertEqualsWithDelta(10.0, $l->y(), 0.001);
	}

	public function testDefaultsAreRelatedAndKindObject(): void
	{
		$this->assertTrue($this->object()->isRelatedTo(null));
		$this->assertSame('object', $this->object()->getKind());
		$this->assertCount(2, $this->object()->getMovements());
	}
}
