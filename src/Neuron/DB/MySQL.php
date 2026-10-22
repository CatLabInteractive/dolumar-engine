<?php
/**
 *  Dolumar engine, php/html MMORTS engine
 *  Copyright (C) 2009 Thijs Van der Schaeghe
 *  CatLab Interactive bvba, Gent, Belgium
 *  http://www.catlab.eu/
 *  http://www.dolumar.com/
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License along
 *  with this program; if not, write to the Free Software Foundation, Inc.,
 *  51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

class Neuron_DB_MySQL extends Neuron_DB_Database
{
	/**
	 * @var mysqli
	 */
	private $connection;

	/*
		define ('DB_USERNAME', 'myuser');
		define ('DB_PASSWORD', 'myuser');
		define ('DB_SERVER', 'localhost');
		define ('DB_DATABASE', 'dolumar');
		define ('DB_PORT', 3306); // optional
		define ('DB_CHARSET', 'utf8mb4'); // optional
	*/
	private function connect ()
	{
		if (!isset ($this->connection))
		{
			// Keep PHP 7 semantics: mysqli reports errors through return values, not exceptions
			mysqli_report (MYSQLI_REPORT_OFF);

			$port = defined ('DB_PORT') ? (int) DB_PORT : 3306;
			$charset = defined ('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

			$connection = @ new MySQLi (DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_DATABASE, $port);

			// Never output host or credentials
			if ($connection->connect_errno || !$connection->set_charset ($charset))
			{
				throw new RuntimeException ('Database connection failed');
			}

			$this->connection = $connection;
		}
	}

	public function getConnection ()
	{
		return $this->connection;
	}

	public function multiQuery($sSQL)
	{
		$this->connect();
		$this->connection->multi_query($sSQL);
	}

	/*
		Execute a query and return a result
	*/
	public function query ($sSQL)
	{
		$this->addQueryLog ($sSQL);

		$this->connect ();

		// Increase the counter
		$this->query_counter ++;

		$result = $this->connection->query ($sSQL);

		if (!$result)
		{
			throw new Exception ('MySQL Error: '.$this->connection->error);
		}

		elseif ($result instanceof MySQLi_Result)
		{
			return new Neuron_DB_Result ($result);
		}

		// Insert ID will return zero if this query was not insert or update.
		$this->insert_id = intval ($this->connection->insert_id);

		// Affected rows
		$this->affected_rows = intval ($this->connection->affected_rows);

		if ($this->insert_id > 0)
			return $this->insert_id;

		if ($this->affected_rows > 0)
			return $this->affected_rows;

		return $result;
	}

	public function escape ($txt)
	{
		if (is_array ($txt))
		{
			throw new Neuron_Core_Error ('Invalid parameter: escape cannot handle arrays.');
		}
		return $this->connection->real_escape_string ($txt);
	}

	public function fromUnixtime ($timestamp)
	{
		$query = $this->query ("SELECT FROM_UNIXTIME('{$timestamp}') AS datum");
		return $query[0]['datum'];
	}

	public function toUnixtime ($date)
	{
		$query = $this->query ("SELECT UNIX_TIMESTAMP('{$date}') AS datum");
		return $query[0]['datum'];
	}
}
?>
