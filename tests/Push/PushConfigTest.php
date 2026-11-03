<?php

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class PushConfigTest extends TestCase
{
	#[RunInSeparateProcess]
	public function testDisabledWithoutKeys(): void
	{
		$this->assertFalse(Neuron_GameServer_Push_Config::isEnabled());
		$this->assertNull(Neuron_GameServer_Push_Config::getPublicKey());
	}

	#[RunInSeparateProcess]
	public function testDisabledWithEmptyKeys(): void
	{
		define('VAPID_PUBLIC_KEY', '');
		define('VAPID_PRIVATE_KEY', ' ');
		$this->assertFalse(Neuron_GameServer_Push_Config::isEnabled());
	}

	#[RunInSeparateProcess]
	public function testDisabledEndpointAnswers404(): void
	{
		[$status] = Neuron_GameServer_Push_Endpoint::handle('subscribe', null, 'POST', 'application/json', '{}');
		$this->assertSame(404, $status);
	}

	public function testEnabledWithKeys(): void
	{
		require_once __DIR__ . '/PushTestHelper.php';
		PushTestHelper::enablePush();

		$this->assertTrue(Neuron_GameServer_Push_Config::isEnabled());
		$this->assertSame(VAPID_PUBLIC_KEY, Neuron_GameServer_Push_Config::getPublicKey());

		$auth = Neuron_GameServer_Push_Config::getAuth();
		$this->assertSame('mailto:test@example.com', $auth['VAPID']['subject']);
		$this->assertSame(VAPID_PRIVATE_KEY, $auth['VAPID']['privateKey']);
	}
}
