<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Map/ExportableObject.php';

class MapObjectsStubGame implements Neuron_GameServer_Interfaces_Game
{
	public function getWindow ($sWindowName) { return false; }
	public function getPlayer ($id) { return null; }
	public function getInitialWindows ($objServer) { return array (); }
	public function getServer () { return null; }
	public function getCustomOutput () { return null; }

	public function getMap ()
	{
		return new class implements Neuron_GameServer_Map_Map {
			public function getPreloadDisplayObjects () { return array (); }
			public function getBackgroundManager () { return null; }
			public function addMapUpdate (Neuron_GameServer_Map_Location $location, $action) { }
			public function getInitialLocation () { return new Neuron_GameServer_Map_Location (0, 0); }
			public function getMapObjectManager ()
			{
				return new class extends Neuron_GameServer_Map_Managers_MapObjectManager {
					public $areasSeen = null;
					public function getDisplayObjects (Neuron_GameServer_Map_Area $area) { return array (); }
					public function getMovingObjects (array $areas, $viewer = null)
					{
						$this->areasSeen = $areas;
						return array (ExportableObject::withTwoMovements ('b:1', true, 'attack', 'A → B'));
					}
				};
			}
		};
	}
}

class GameServerMapObjectsRouteTest extends TestCase
{
	private function server(): Neuron_GameServer
	{
		if (!defined('ABSOLUTE_URL')) {
			define('ABSOLUTE_URL', 'http://localhost/');
		}
		if (!defined('BASE_URL')) {
			define('BASE_URL', 'http://localhost/');
		}
		$server = Neuron_GameServer::bootstrap();
		$server->setGame(new MapObjectsStubGame());
		return $server;
	}

	public function testAnonymousIsForbiddenWithEmptyList(): void
	{
		$r = $this->server()->renderMovingObjects([[0, 0]], 10, false, 1000);
		$this->assertSame(403, $r['status']);
		$this->assertSame(['objects' => []], $r['body']);
	}

	public function testLoggedInGetsExportFromManager(): void
	{
		$r = $this->server()->renderMovingObjects([[0, 0]], 10, 'player', 1050);
		$this->assertSame(200, $r['status']);
		$this->assertSame(1050, $r['body']['servertime']);
		$this->assertSame(30, $r['body']['poll']);
		$this->assertSame('b:1', $r['body']['objects'][0]['id']);
		$this->assertSame('A → B', $r['body']['objects'][0]['name']);
	}
}
