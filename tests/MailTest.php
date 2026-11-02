<?php

use PHPUnit\Framework\TestCase;

class MailTest extends TestCase
{
	private const MAILPIT_API = 'http://mailpit:8025/api/v1/messages';

	public static function setUpBeforeClass(): void
	{
		// connect.php has global side effects, so load only the mail helper from it.
		if (!function_exists('customMail')) {
			$source = file_get_contents(__DIR__ . '/../src/connect.php');
			if (!preg_match('/^function customMail \(.*?^}/ms', $source, $matches)) {
				throw new RuntimeException('customMail() not found in connect.php');
			}
			eval($matches[0]);
		}

		$constants = [
			'EMAIL_SMTP_SERVER' => 'mailpit',
			'EMAIL_SMTP_PORT' => 1025,
			'EMAIL_FROM' => 'dolumar@example.com',
			'EMAIL_FROM_NAME' => 'Dolumar Test',
		];
		foreach ($constants as $name => $value) {
			if (!defined($name)) {
				define($name, $value);
			}
		}
	}

	private function fetchMessages(): ?array
	{
		$context = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
		$body = @file_get_contents(self::MAILPIT_API, false, $context);
		if ($body === false) {
			return null;
		}
		$data = json_decode($body, true);
		return is_array($data) ? ($data['messages'] ?? []) : null;
	}

	public function test_send_mail_reaches_smtp(): void
	{
		if ($this->fetchMessages() === null) {
			$this->markTestSkipped('Mailpit is not reachable.');
		}

		$subject = 'Dolumar mail test ' . uniqid();
		customMail('player@example.com', $subject, 'Hello from the test suite.');

		$found = false;
		for ($i = 0; $i < 20 && !$found; $i++) {
			foreach ($this->fetchMessages() ?? [] as $message) {
				if (($message['Subject'] ?? null) === $subject) {
					$found = true;
					break;
				}
			}
			if (!$found) {
				usleep(250000);
			}
		}

		$this->assertTrue($found, 'Message with subject "' . $subject . '" not found in Mailpit.');
	}
}
