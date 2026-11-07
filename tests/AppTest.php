<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AppTest extends TestCase
{
	const CHROME = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36';

	public static function userAgents (): array
	{
		return [
			'browser' => [self::CHROME, false, null, 'https://localhost'],
			'first app builds' => [self::CHROME . ' DolumarApp/1.0', true, null, 'https://localhost'],
			'android' => [self::CHROME . ' DolumarApp/1.0 (android)', true, 'android', 'https://localhost'],
			'ios' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148 DolumarApp/1.2.3 (ios)', true, 'ios', 'app://localhost'],
			'case' => [self::CHROME . ' dolumarapp/1.0 (iOS)', true, 'ios', 'app://localhost'],
			'look-alike' => [self::CHROME . ' NotDolumarApp/1.0', false, null, 'https://localhost'],
			'empty' => ['', false, null, 'https://localhost'],
		];
	}

	#[DataProvider('userAgents')]
	public function testTheAppIsRecognisedByItsUserAgent (string $ua, bool $isApp, ?string $platform, string $origin): void
	{
		$this->assertSame ($isApp, Neuron_GameServer_App::isApp ($ua));
		$this->assertSame ($platform, Neuron_GameServer_App::getPlatform ($ua));
		$this->assertSame ($origin, Neuron_GameServer_App::getOrigin ($ua));
	}

	public function testTheAllowedOriginsAreTheBundledPages (): void
	{
		$this->assertSame (['https://localhost', 'app://localhost'], Neuron_GameServer_App::getOrigins ());
	}
}
