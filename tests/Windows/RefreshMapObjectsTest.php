<?php

use PHPUnit\Framework\TestCase;

class RefreshMapObjectsTestWindow extends Neuron_GameServer_Windows_Window
{
	public $sendsArmy = false;

	public function getRefresh ()
	{
		if ($this->sendsArmy)
		{
			Neuron_GameServer_Map_MapObjectRefresh::request ();
		}
	}
}

class RefreshMapObjectsTest extends TestCase
{
	protected function setUp(): void
	{
		Neuron_GameServer_Map_MapObjectRefresh::take();
	}

	private function updateActions(RefreshMapObjectsTestWindow $window): array
	{
		$dom = new DOMDocument();
		$window->setDom($dom);
		$window->setDivId('w1');
		$element = $window->getDOMRefresh();
		if (!$element)
		{
			return [];
		}
		$out = [];
		foreach ($element->getElementsByTagName('update') as $u)
		{
			$out[] = $u->getAttribute('action');
		}
		return $out;
	}

	public function testAWindowThatSendsAnArmyTellsTheClientToRefreshTheMapObjects(): void
	{
		$window = new RefreshMapObjectsTestWindow();
		$window->sendsArmy = true;
		$this->assertSame(['refreshMapObjects'], $this->updateActions($window));
	}

	public function testOtherWindowsSendNoRefresh(): void
	{
		$this->assertSame([], $this->updateActions(new RefreshMapObjectsTestWindow()));
	}
}
