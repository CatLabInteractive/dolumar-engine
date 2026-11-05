<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Warnings that Errbit reported from production (dolumar #26, #30-#36).
 * Each test turns PHP warnings into exceptions so a regression fails loudly.
 */
class ProductionWarningsTest extends TestCase
{
	protected function setUp(): void
	{
		set_error_handler(static function (int $no, string $msg, string $file, int $line): bool {
			throw new ErrorException($msg, 0, $no, $file, $line);
		});

		if (!defined('ALLOW_VACATION_MODE')) {
			define('ALLOW_VACATION_MODE', true);
		}

		if (!defined('ABSOLUTE_URL')) {
			define('ABSOLUTE_URL', 'https://game.example.test/');
		}
	}

	protected function tearDown(): void
	{
		restore_error_handler();
	}

	public function testVacationStartWithoutColumn(): void
	{
		// NPC owners (player 0) have no row; data without the column hits the same read.
		$player = new Neuron_GameServer_Player(0);
		$player->setData([]);
		$this->assertNull($player->getVacationStart());
		$this->assertFalse($player->inVacationMode());
	}

	public function testAdminPageUrlWithoutParameters(): void
	{
		$_COOKIE['session_id'] = 'set';
		try {
			$this->assertSame(ABSOLUTE_URL . 'admin/players', Neuron_GameServer_Pages_Admin_Page::getUrl('players'));
		} finally {
			unset($_COOKIE['session_id']);
		}
	}

	#[DataProvider('readOnlySessionModules')]
	public function testReadOnlySessionRequest(mixed $module, bool $expected): void
	{
		$this->assertSame($expected, Neuron_Core_Tools::isReadOnlySessionRequest($module));
	}

	public static function readOnlySessionModules(): array
	{
		return [
			['image/minimap/', true],
			['/image/snapshot/', true],
			['image', true],
			['dialog/', false],
			['map/', false],
			['images/', false],
			['', false],
			[null, false],
			[['image'], false],
		];
	}

	#[DataProvider('sessionlessModules')]
	public function testSessionlessRequest(mixed $module, bool $expected): void
	{
		$this->assertSame($expected, Neuron_Core_Tools::isSessionlessRequest($module));
	}

	public static function sessionlessModules(): array
	{
		return [
			['gameserver/assets/css/main.css', true],
			['/gameserver/assets/javascript/gui.js', true],
			['dialog/', false],
			['image/minimap/', false],
			['gameservers/', false],
			['', false],
			[null, false],
			[['gameserver'], false],
		];
	}
}
