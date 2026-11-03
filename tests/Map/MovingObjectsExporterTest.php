<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/ExportableObject.php';

class MovingObjectsExporterTest extends TestCase
{
	private function obj($uoid, $related, $kind, $name = null, $click = null): ExportableObject
	{
		return ExportableObject::withTwoMovements($uoid, $related, $kind, $name, $click);
	}

	public function testRelatedObjectIsExportedInFull(): void
	{
		$out = (new Neuron_GameServer_Map_MovingObjectsExporter('s', 30))->export(
			[$this->obj('b:1', true, 'attack', 'A → B', "openWindow('battle',{'vid':1});")], 'viewer', 1050);
		$this->assertSame(1050, $out['servertime']);
		$this->assertSame(30, $out['poll']);
		$o = $out['objects'][0];
		$this->assertSame('b:1', $o['id']);
		$this->assertSame('attack', $o['kind']);
		$this->assertSame('A → B', $o['name']);
		$this->assertSame("openWindow('battle',{'vid':1});", $o['click']);
		$this->assertCount(2, $o['paths']);
		$this->assertSame(1000, $o['paths'][0]['start']);
		$this->assertSame('army.png', $o['sprite']['url']);
		$this->assertEqualsWithDelta(5.0, $o['x'], 0.001);
		$this->assertEqualsWithDelta(0.0, $o['y'], 0.001);
	}

	public function testUnrelatedObjectIsRedactedToOnePollWindow(): void
	{
		$o = (new Neuron_GameServer_Map_MovingObjectsExporter('s', 30))->export(
			[$this->obj('b:1', false, 'attack', 'A → B', 'x')], 'viewer', 1050)['objects'][0];
		$this->assertSame('neutral', $o['kind']);
		$this->assertStringStartsWith('a:', $o['id']);
		$this->assertSame(14, strlen($o['id']));
		$this->assertArrayNotHasKey('name', $o);
		$this->assertArrayNotHasKey('click', $o);
		$this->assertCount(1, $o['paths']);
		$this->assertSame(1050, $o['paths'][0]['start']);
		$this->assertSame(1080, $o['paths'][0]['end']);
		$this->assertEqualsWithDelta(5.0, $o['paths'][0]['points'][0][0], 0.001);
		$this->assertEqualsWithDelta(8.0, $o['paths'][0]['points'][1][0], 0.001);
	}

	public function testUnrelatedTruncationStopsAtMovementEnd(): void
	{
		$p = (new Neuron_GameServer_Map_MovingObjectsExporter('s', 30))->export(
			[$this->obj('b:1', false, 'attack')], null, 1090)['objects'][0]['paths'][0];
		$this->assertSame(1100, $p['end']);
	}

	public function testUnrelatedIdleObjectHasNoPaths(): void
	{
		$o = (new Neuron_GameServer_Map_MovingObjectsExporter('s', 30))->export(
			[$this->obj('b:1', false, 'attack')], null, 5000)['objects'][0];
		$this->assertSame([], $o['paths']);
		$this->assertEqualsWithDelta(10.0, $o['x'], 0.001);
	}

	public function testAnonymousIdIsStableAndDependsOnSecret(): void
	{
		$a = new Neuron_GameServer_Map_MovingObjectsExporter('s1');
		$b = new Neuron_GameServer_Map_MovingObjectsExporter('s2');
		$this->assertSame($a->anonymousId('b:1'), $a->anonymousId('b:1'));
		$this->assertNotSame($a->anonymousId('b:1'), $b->anonymousId('b:1'));
	}

	public function testExporterSkipsObjectsThatThrow(): void
	{
		$bad = new class extends Neuron_GameServer_Map_MapObject {
			public function getDisplayObject () { throw new RuntimeException ('no town centre'); }
			public function getName () { return 'bad'; }
		};
		$bad->setLocation(new Neuron_GameServer_Map_Location(0, 0));
		$out = (new Neuron_GameServer_Map_MovingObjectsExporter('s'))->export(
			[$bad, $this->obj('b:2', true, 'squad', 'S')], null, 1050);
		$this->assertCount(1, $out['objects']);
		$this->assertSame('b:2', $out['objects'][0]['id']);
	}

	public function testExporterSkipsObjectsThatRaiseAnError(): void
	{
		$bad = new class extends Neuron_GameServer_Map_MapObject {
			public function getDisplayObject () { return intdiv (1, 0); }
			public function getName () { return 'bad'; }
		};
		$bad->setLocation(new Neuron_GameServer_Map_Location(0, 0));
		$out = (new Neuron_GameServer_Map_MovingObjectsExporter('s'))->export(
			[$bad, $this->obj('b:3', true, 'squad', 'S')], null, 1050);
		$this->assertCount(1, $out['objects']);
		$this->assertSame('b:3', $out['objects'][0]['id']);
	}

	public function testUnrelatedViewerGetsTheNeutralSprite(): void
	{
		$out = (new Neuron_GameServer_Map_MovingObjectsExporter('s'))->export(
			[$this->obj('b:4', false, 'attack', 'A → B')], null, 1050);
		$this->assertSame('neutral.png', $out['objects'][0]['sprite']['url']);
	}

	public function testResponseOrderDoesNotFollowTheInputOrder(): void
	{
		// A game may hand over its objects grouped by kind; the order must not leak that.
		$exporter = new Neuron_GameServer_Map_MovingObjectsExporter('s');
		$a = $this->obj('b:1', false, 'attack');
		$b = $this->obj('s:2', true, 'squad');
		$c = $this->obj('b:3', false, 'attack');

		$ids = function (array $out) { return array_map(function ($o) { return $o['id']; }, $out['objects']); };
		$first = $ids($exporter->export([$a, $b, $c], null, 1050));
		$second = $ids($exporter->export([$c, $a, $b], null, 1050));

		$this->assertSame($first, $second, 'same set, same order whatever the input order');
		$sorted = $first;
		sort($sorted, SORT_STRING);
		$this->assertSame($sorted, $first, 'sorted by the exported id');
	}

	public function testWithoutASecretUnrelatedObjectsAreNotSentAtAll(): void
	{
		// Fail closed: with a public salt the anonymous ids could be reversed by enumeration.
		$out = (new Neuron_GameServer_Map_MovingObjectsExporter(''))->export(
			[$this->obj('b:1', false, 'attack'), $this->obj('s:2', true, 'squad', 'S')], null, 1050);
		$this->assertCount(1, $out['objects']);
		$this->assertSame('s:2', $out['objects'][0]['id']);
	}

	public function testAnonymousIdIsAKeyedHashNotASaltedDigest(): void
	{
		$e = new Neuron_GameServer_Map_MovingObjectsExporter('k');
		$this->assertSame('a:' . substr(hash_hmac('sha256', 'b:1', 'k'), 0, 12), $e->anonymousId('b:1'));
	}
}
