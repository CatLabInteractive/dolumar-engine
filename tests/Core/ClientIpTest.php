<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ClientIpTest extends TestCase
{
	private const TRUSTED = ['127.0.0.0/8', '::1/128', '172.16.0.0/12'];

	#[DataProvider('requests')]
	public function testClientIp(array $server, string $expected): void
	{
		$this->assertSame($expected, Neuron_Core_Tools::getClientIp($server, self::TRUSTED));
	}

	public static function requests(): array
	{
		return [
			'direct visitor' => [['REMOTE_ADDR' => '203.0.113.7'], '203.0.113.7'],
			'direct visitor cannot spoof forwarded-for' => [
				['REMOTE_ADDR' => '203.0.113.7', 'HTTP_X_FORWARDED_FOR' => '198.51.100.1'], '203.0.113.7'],
			'direct visitor cannot spoof real-ip' => [
				['REMOTE_ADDR' => '203.0.113.7', 'HTTP_X_REAL_IP' => '198.51.100.1'], '203.0.113.7'],
			'dokku nginx on docker bridge' => [
				['REMOTE_ADDR' => '172.17.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7'], '203.0.113.7'],
			'real-ip only' => [
				['REMOTE_ADDR' => '172.17.0.1', 'HTTP_X_REAL_IP' => '203.0.113.7'], '203.0.113.7'],
			'forwarded-for wins over real-ip' => [
				['REMOTE_ADDR' => '172.17.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7', 'HTTP_X_REAL_IP' => '198.51.100.1'],
				'203.0.113.7'],
			'spoofed left-most entry is ignored' => [
				['REMOTE_ADDR' => '172.17.0.1', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4, 203.0.113.7'], '203.0.113.7'],
			'trusted hops are skipped' => [
				['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7, 172.18.0.5'], '203.0.113.7'],
			'garbage stops the walk' => [
				['REMOTE_ADDR' => '172.17.0.1', 'HTTP_X_FORWARDED_FOR' => 'nonsense, 203.0.113.7'], '203.0.113.7'],
			'only garbage falls back to proxy' => [
				['REMOTE_ADDR' => '172.17.0.1', 'HTTP_X_FORWARDED_FOR' => 'nonsense'], '172.17.0.1'],
			'garbage real-ip falls back to proxy' => [
				['REMOTE_ADDR' => '172.17.0.1', 'HTTP_X_REAL_IP' => "1.2.3.4'; DROP"], '172.17.0.1'],
			'no headers behind proxy' => [['REMOTE_ADDR' => '172.17.0.1'], '172.17.0.1'],
			'ipv6 client' => [
				['REMOTE_ADDR' => '172.17.0.1', 'HTTP_X_FORWARDED_FOR' => '2001:db8::1'], '2001:db8::1'],
			'ipv6 loopback proxy' => [
				['REMOTE_ADDR' => '::1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7'], '203.0.113.7'],
			'outside 172.16/12 is not trusted' => [
				['REMOTE_ADDR' => '172.32.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7'], '172.32.0.1'],
			'no remote addr (cli)' => [[], ''],
		];
	}

	public function testRangeMatching(): void
	{
		$this->assertTrue(Neuron_Core_Tools::isTrustedProxy('172.31.255.255', ['172.16.0.0/12']));
		$this->assertFalse(Neuron_Core_Tools::isTrustedProxy('172.15.255.255', ['172.16.0.0/12']));
		$this->assertTrue(Neuron_Core_Tools::isTrustedProxy('10.1.2.3', ['10.1.2.3']));
		$this->assertFalse(Neuron_Core_Tools::isTrustedProxy('10.1.2.4', ['10.1.2.3']));
		$this->assertTrue(Neuron_Core_Tools::isTrustedProxy('1.2.3.4', ['0.0.0.0/0']));
		$this->assertFalse(Neuron_Core_Tools::isTrustedProxy('::ffff:1.2.3.4', ['1.2.3.4']));
		$this->assertFalse(Neuron_Core_Tools::isTrustedProxy('1.2.3.4', ['1.2.3.0/33', 'bogus', '']));
		$this->assertTrue(Neuron_Core_Tools::isTrustedProxy('fd00::5', ['fd00::/8']));
	}

	public function testDefaultTrustedProxies(): void
	{
		if (defined('TRUSTED_PROXIES')) {
			$this->markTestSkipped('TRUSTED_PROXIES is defined in this environment');
		}

		$this->assertSame(Neuron_Core_Tools::DEFAULT_TRUSTED_PROXIES, Neuron_Core_Tools::getTrustedProxies());
		$this->assertSame('203.0.113.7', Neuron_Core_Tools::getClientIp(
			['REMOTE_ADDR' => '172.17.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7']));
	}
}
