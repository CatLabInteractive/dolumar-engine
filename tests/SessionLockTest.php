<?php

use PHPUnit\Framework\TestCase;

/**
 * A page refresh sometimes showed the login prompt to a logged in player:
 * the memcached session lock was still held by other requests of the same
 * session, session_start() gave up after 0.75 s and the request ran without
 * its session (dolumar #30, #31, #79, #80).
 */
class SessionLockTest extends TestCase
{
	/**
	 * Replays the php-memcached lock loop (php_memcached_session.c) and returns
	 * how many milliseconds a request sleeps before it gives up.
	 */
	private static function totalLockWait(array $settings): int
	{
		$wait = (int) $settings['memcached.sess_lock_wait_min'];
		$max = (int) $settings['memcached.sess_lock_wait_max'];
		$retries = (int) $settings['memcached.sess_lock_retries'];

		$total = 0;
		while ($retries-- > 0) {
			$total += $wait;
			$wait = min($max, $wait * 2);
		}

		return $total;
	}

	public function testExtensionDefaultsGiveUpTooSoon(): void
	{
		$defaults = [
			'memcached.sess_lock_wait_min' => '150',
			'memcached.sess_lock_wait_max' => '150',
			'memcached.sess_lock_retries' => '5',
		];

		$this->assertSame(750, self::totalLockWait($defaults));
	}

	public function testLockWaitCoversSlowRequestsButNotTheAjaxTimeout(): void
	{
		$total = self::totalLockWait(Neuron_Core_Tools::getSessionLockSettings());

		$this->assertGreaterThanOrEqual(10000, $total);
		// game.js aborts an AJAX request after 45 s; leave room for the request itself.
		$this->assertLessThanOrEqual(20000, $total);
	}

	public function testFirstRetryIsQuick(): void
	{
		$settings = Neuron_Core_Tools::getSessionLockSettings();
		$this->assertLessThanOrEqual(50, (int) $settings['memcached.sess_lock_wait_min']);
	}

	public function testUnavailablePageRetriesAndAsksNobodyToLogIn(): void
	{
		$page = Neuron_Core_Tools::getSessionUnavailablePage();

		$this->assertStringContainsString('http-equiv="refresh"', $page);
		$this->assertStringNotContainsStringIgnoringCase('login', $page);
	}

	public function testConnectStopsWhenTheSessionCannotStart(): void
	{
		$source = file_get_contents(__DIR__ . '/../src/connect.php');

		$this->assertMatchesRegularExpression('/getSessionLockSettings \(\)[\s\S]*if \(!session_start/', $source);
		$this->assertStringContainsString('http_response_code (503)', $source);
	}
}
