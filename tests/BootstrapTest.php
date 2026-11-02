<?php

use PHPUnit\Framework\TestCase;

class BootstrapTest extends TestCase
{
	public function testDatabaseConnectionWorks(): void
	{
		$db = Neuron_DB_Database::getInstance();
		$result = $db->query('SELECT 1 AS one');

		$this->assertSame(1, $result->getNumRows());
		foreach ($result as $row) {
			$this->assertEquals(1, $row['one']);
		}
	}
}
