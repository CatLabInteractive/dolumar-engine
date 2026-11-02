<?php

use PHPUnit\Framework\TestCase;

/**
 * The game used to show an advertisement in a loading dialog for 20 seconds on start-up and slowed the
 * map load down while it was open. It was removed; make sure it does not come back through the shell.
 */
class LoadingAdvertisementRemovedTest extends TestCase
{
	public function testShellDoesNotLoadAdvertisementScript(): void
	{
		$template = file_get_contents(__DIR__ . '/../templates/gameserver/index.phpt');
		$this->assertStringNotContainsString('advertisement.js', $template);
		$this->assertStringNotContainsString('CONFIG_HIDE_ADVERTISEMENT', $template);
	}

	public function testAdvertisementAssetIsGone(): void
	{
		$this->assertNull(Neuron_GameServer::resolveAssetPath(['javascript', 'advertisement.js']));
	}
}
