<?php

use PHPUnit\Framework\TestCase;

class MySQLTest extends TestCase
{
	private function db (): Neuron_DB_MySQL
	{
		$db = Neuron_DB_Database::getInstance ();
		$this->assertInstanceOf (Neuron_DB_MySQL::class, $db);
		return $db;
	}

	public function test_failed_query_throws_legacy_exception (): void
	{
		try
		{
			$this->db ()->query ('SELECT * FROM table_that_does_not_exist');
			$this->fail ('Expected an exception');
		}
		catch (Throwable $e)
		{
			$this->assertSame (Exception::class, get_class ($e));
			$this->assertStringStartsWith ('MySQL Error:', $e->getMessage ());
		}
	}

	public function test_escape_quotes (): void
	{
		$db = $this->db ();
		$db->query ('SELECT 1');
		$this->assertSame ("O\\'Brien", $db->escape ("O'Brien"));
	}

	public function test_utf8_roundtrip (): void
	{
		$db = $this->db ();
		$value = 'Ünderwörld ⚔';

		$db->query ('CREATE TEMPORARY TABLE utf8_roundtrip (val VARCHAR(50) CHARACTER SET utf8mb4)');
		$db->query ("INSERT INTO utf8_roundtrip (val) VALUES ('" . $db->escape ($value) . "')");
		$result = $db->query ('SELECT val FROM utf8_roundtrip');

		$this->assertSame (1, $result->getNumRows ());
		$this->assertSame ($value, $result[0]['val']);
	}

	public function test_unixtime_roundtrip (): void
	{
		$db = $this->db ();
		$this->assertSame (1700000000, (int) $db->toUnixtime ($db->fromUnixtime (1700000000)));
	}

	public function test_connects_with_explicit_port (): void
	{
		$this->assertTrue (defined ('DB_PORT'));
		$this->assertIsInt (DB_PORT);

		// Fresh instance, so it performs its own connect() using DB_PORT
		$db = new Neuron_DB_MySQL ();
		$result = $db->query ('SELECT 1 AS one');
		$this->assertSame (1, $result->getNumRows ());
	}
}
