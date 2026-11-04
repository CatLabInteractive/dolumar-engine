<?php

use PHPUnit\Framework\TestCase;

class GameServerLayoutTest extends TestCase
{
	public function testDefaultIsAuto(): void
	{
		// Auto picks the mobile layout on narrow (phone) viewports and desktop elsewhere.
		$this->assertSame('auto', Neuron_GameServer::LAYOUT_DEFAULT);
	}

	public function testQueryOverridesStored(): void
	{
		$this->assertSame('mobile', Neuron_GameServer::resolveLayoutPreference('desktop', 'mobile'));
	}

	public function testStoredIsUsedWithoutQuery(): void
	{
		$this->assertSame('desktop', Neuron_GameServer::resolveLayoutPreference('desktop', null));
	}

	public function testUnknownValuesFallBackToDefault(): void
	{
		$this->assertSame('auto', Neuron_GameServer::resolveLayoutPreference('foo', 'bar'));
		$this->assertSame('auto', Neuron_GameServer::resolveLayoutPreference(null, null));
		$this->assertSame('mobile', Neuron_GameServer::resolveLayoutPreference('mobile', 'x'));
	}

	public function testChoiceUnderTheNewKeyWins(): void
	{
		$this->assertSame('desktop', Neuron_GameServer::storedLayoutPreference('desktop', 'mobile'));
		$this->assertSame('auto', Neuron_GameServer::storedLayoutPreference('auto', null));
	}

	public function testLegacyDesktopAndAutoAreNotAnOptOut(): void
	{
		$this->assertNull(Neuron_GameServer::storedLayoutPreference(null, 'desktop'));
		$this->assertNull(Neuron_GameServer::storedLayoutPreference(null, 'auto'));
		$this->assertNull(Neuron_GameServer::storedLayoutPreference(null, null));
		$this->assertNull(Neuron_GameServer::storedLayoutPreference('foo', 'foo'));
	}

	public function testLegacyMobileIsKept(): void
	{
		$this->assertSame('mobile', Neuron_GameServer::storedLayoutPreference(null, 'mobile'));
	}

	public function testPlayerPreferenceReadsBothKeys(): void
	{
		$player = new class {
			public array $prefs = [];
			public function getPreference($key, $default = false) { return $this->prefs[$key] ?? $default; }
		};

		$this->assertNull(Neuron_GameServer::getPlayerLayoutPreference(null));
		$this->assertNull(Neuron_GameServer::getPlayerLayoutPreference($player));

		$player->prefs = ['layout' => 'desktop'];
		$this->assertNull(Neuron_GameServer::getPlayerLayoutPreference($player));

		$player->prefs = ['layout' => 'desktop', 'layoutMode' => 'desktop'];
		$this->assertSame('desktop', Neuron_GameServer::getPlayerLayoutPreference($player));
	}
}
