<?php

use PHPUnit\Framework\TestCase;

class AdminPremiumTest extends TestCase
{
	public function testBlocksLeft (): void
	{
		$now = 1800000000;
		$day = 86400;
		$this->assertSame (0, Neuron_GameServer_Pages_Admin_Premium::blocksLeft ($now - 5, $now));
		$this->assertSame (0, Neuron_GameServer_Pages_Admin_Premium::blocksLeft ($now, $now));
		$this->assertSame (1, Neuron_GameServer_Pages_Admin_Premium::blocksLeft ($now + 1, $now));
		$this->assertSame (1, Neuron_GameServer_Pages_Admin_Premium::blocksLeft ($now + 15 * $day, $now));
		$this->assertSame (2, Neuron_GameServer_Pages_Admin_Premium::blocksLeft ($now + 15 * $day + 1, $now));
		$this->assertSame (3, Neuron_GameServer_Pages_Admin_Premium::blocksLeft ($now + 40 * $day, $now));
	}

	public function testRefundNeedsPostAndToken (): void
	{
		$token = str_repeat ('ab', 16);
		$ok = ['refund' => '1', 'token' => $token];
		$this->assertTrue (Neuron_GameServer_Pages_Admin_Premium::isRefundRequest ('POST', $ok, $token));
		$this->assertFalse (Neuron_GameServer_Pages_Admin_Premium::isRefundRequest ('GET', $ok, $token));
		$this->assertFalse (Neuron_GameServer_Pages_Admin_Premium::isRefundRequest ('POST', ['refund' => '1'], $token));
		$this->assertFalse (Neuron_GameServer_Pages_Admin_Premium::isRefundRequest ('POST', ['refund' => '1', 'token' => 'nope'], $token));
		$this->assertFalse (Neuron_GameServer_Pages_Admin_Premium::isRefundRequest ('POST', ['refund' => '1', 'token' => ['x']], $token));
		$this->assertFalse (Neuron_GameServer_Pages_Admin_Premium::isRefundRequest ('POST', ['refund' => '0', 'token' => $token], $token));
		$this->assertFalse (Neuron_GameServer_Pages_Admin_Premium::isRefundRequest ('POST', ['refund' => '1', 'token' => ''], ''));
	}

	public function testTokenIsStableUntilRenewed (): void
	{
		$saved = $_SESSION ?? null;
		$_SESSION = [];
		try {
			$a = Neuron_GameServer_Pages_Admin_Premium::token ();
			$this->assertSame (32, strlen ($a));
			$this->assertSame ($a, Neuron_GameServer_Pages_Admin_Premium::token ());
			$this->assertNotSame ($a, Neuron_GameServer_Pages_Admin_Premium::token (true));
		} finally {
			$_SESSION = $saved;
		}
	}
}
