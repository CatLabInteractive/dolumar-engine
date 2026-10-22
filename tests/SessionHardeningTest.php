<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SessionHardeningTest extends TestCase
{
	#[DataProvider('validIds')]
	public function testValidSessionIds(string $id): void
	{
		$this->assertSame($id, Neuron_Core_Tools::sanitizeSessionId($id));
	}

	public static function validIds(): array
	{
		return [
			['abcdefghijklmnopqrstuv'],
			['0123456789abcdef0123456789abcdef'],
			['abc-def,ghi-jkl,mno-pqr-stu'],
			[str_repeat('a', 256)],
		];
	}

	#[DataProvider('invalidIds')]
	public function testInvalidSessionIdsAreIgnored(mixed $id): void
	{
		$this->assertNull(Neuron_Core_Tools::sanitizeSessionId($id));
	}

	public static function invalidIds(): array
	{
		return [
			[null], [''], ['short'], [str_repeat('a', 21)], [str_repeat('a', 257)],
			['abcdefghijklmnopqrstu/'], ["abcdefghijklmnopqrstuv\n"], ['../../etc/passwdxxxxxxxxxx'],
			[['abcdefghijklmnopqrstuv']], [12345678901234567890123],
		];
	}

	public function testHttpsDetection(): void
	{
		$this->assertFalse(Neuron_Core_Tools::isHttpsRequest([]));
		$this->assertFalse(Neuron_Core_Tools::isHttpsRequest(['HTTPS' => '']));
		$this->assertFalse(Neuron_Core_Tools::isHttpsRequest(['HTTPS' => 'off']));
		$this->assertFalse(Neuron_Core_Tools::isHttpsRequest(['HTTP_X_FORWARDED_PROTO' => 'http']));
		$this->assertTrue(Neuron_Core_Tools::isHttpsRequest(['HTTPS' => 'on']));
		$this->assertTrue(Neuron_Core_Tools::isHttpsRequest(['HTTPS' => '1']));
		$this->assertTrue(Neuron_Core_Tools::isHttpsRequest(['HTTP_X_FORWARDED_PROTO' => 'https']));
	}

	public function testSessionOptions(): void
	{
		$http = Neuron_Core_Tools::getSessionOptions(['use_cookies' => 0], []);
		$this->assertSame(0, $http['use_cookies']);
		$this->assertSame(1, $http['use_strict_mode']);
		$this->assertSame(1, $http['cookie_httponly']);
		$this->assertSame('Lax', $http['cookie_samesite']);
		$this->assertArrayNotHasKey('cookie_secure', $http);

		$https = Neuron_Core_Tools::getSessionOptions([], ['HTTPS' => 'on']);
		$this->assertSame(1, $https['cookie_secure']);
	}

	public function testCookieOptions(): void
	{
		$o = Neuron_Core_Tools::getCookieOptions('/admin/', ['HTTP_X_FORWARDED_PROTO' => 'https']);
		$this->assertSame(['expires' => 0, 'path' => '/admin/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => true], $o);
		$this->assertFalse(Neuron_Core_Tools::getCookieOptions('/', [])['secure']);
	}
}
