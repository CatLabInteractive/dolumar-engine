<?php

use PHPUnit\Framework\TestCase;

class MovementTest extends TestCase
{
	private function movement(): Neuron_GameServer_Map_Movement
	{
		return new Neuron_GameServer_Map_Movement(
			1000, 1100,
			new Neuron_GameServer_Map_Location(0, 0),
			new Neuron_GameServer_Map_Location(10, 20)
		);
	}

	public function testCurrentLocationInterpolatesLinearly(): void
	{
		$m = $this->movement();
		$this->assertEqualsWithDelta(0.0, $m->getCurrentLocation(1000)->x(), 0.001);
		$this->assertEqualsWithDelta(0.0, $m->getCurrentLocation(1000)->y(), 0.001);
		$this->assertEqualsWithDelta(5.0, $m->getCurrentLocation(1050)->x(), 0.001);
		$this->assertEqualsWithDelta(10.0, $m->getCurrentLocation(1050)->y(), 0.001);
		$this->assertEqualsWithDelta(10.0, $m->getCurrentLocation(1100)->x(), 0.001);
	}

	public function testIsActiveBoundaries(): void
	{
		$m = $this->movement();
		$this->assertFalse($m->isActive(999));
		$this->assertTrue($m->isActive(1000));
		$this->assertTrue($m->isActive(1099));
		$this->assertFalse($m->isActive(1100));
	}

	public function testTruncateReturnsSegmentBetweenTimes(): void
	{
		$t = $this->movement()->truncate(1020, 1050);
		$this->assertSame(1020, $t->getStartTime());
		$this->assertSame(1050, $t->getEndTime());
		$this->assertEqualsWithDelta(2.0, $t->getStartLocation()->x(), 0.001);
		$this->assertEqualsWithDelta(5.0, $t->getEndLocation()->x(), 0.001);
	}

	public function testTruncateClampsToMovementBounds(): void
	{
		$t = $this->movement()->truncate(900, 1200);
		$this->assertSame(1000, $t->getStartTime());
		$this->assertSame(1100, $t->getEndTime());
	}

	public function testZeroLengthMovementStaysPut(): void
	{
		$m = new Neuron_GameServer_Map_Movement(1000, 1100,
			new Neuron_GameServer_Map_Location(4, 4), new Neuron_GameServer_Map_Location(4, 4));
		$this->assertEqualsWithDelta(4.0, $m->getCurrentLocation(1050)->x(), 0.001);
		$this->assertEqualsWithDelta(4.0, $m->getCurrentLocation(1050)->y(), 0.001);
	}

	public function testZeroDurationMovementIsAtEnd(): void
	{
		$m = new Neuron_GameServer_Map_Movement(1000, 1000,
			new Neuron_GameServer_Map_Location(0, 0), new Neuron_GameServer_Map_Location(3, 0));
		$this->assertEqualsWithDelta(3.0, $m->getCurrentLocation(1000)->x(), 0.001);
	}

	public function testPathDataIsPlainArray(): void
	{
		$this->assertSame(
			['start' => 1000, 'end' => 1100, 'points' => [[0.0, 0.0], [10.0, 20.0]]],
			$this->movement()->getPathData()
		);
	}
}
