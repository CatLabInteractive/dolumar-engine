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

class Neuron_DB_Result implements Iterator, ArrayAccess, Countable
{
	private $result;
	
	// iterator
	private $rowId;
	private $current;
	
	private $cache;

	public function __construct ($result)
	{
		$this->result = $result;
		
		// Move to the first row
		$this->rowId = 0;
		$this->current = $this->result->fetch_assoc ();
	}
	
	public function getNumRows ()
	{
		return $this->result->num_rows;
	}
	
	/**************************
		ARRAY ACCESS
	***************************/
	#[\ReturnTypeWillChange]
	public function offsetExists($offset)
	{
		return $offset >= 0 && $offset < $this->getNumRows ();
	}
	
	#[\ReturnTypeWillChange]
	public function offsetGet($offset)
	{
		// Only numeric values
		$offset = intval ($offset);
	
		// Cache these calls
		if (!isset ($this->cache[$offset]))
		{
			// Move mysql data stream to offset
			$this->result->data_seek ($offset);
			$this->cache[$offset] = $this->result->fetch_assoc ();
		
			// Move back to current position
			$this->result->data_seek ($this->rowId);
		}
		
		return $this->cache[$offset];
	}
	
	#[\ReturnTypeWillChange]
	public function offsetUnset($offset)
	{
		// Doesn't do anything here.
	}
	
	#[\ReturnTypeWillChange]
	public function offsetSet($offset, $value)
	{
		// Doesn't do anything here.
	}

	
	/**************************
		ITERATOR
	***************************/
	#[\ReturnTypeWillChange]
	public function current()
	{
		return $this->current;
	}
	
	#[\ReturnTypeWillChange]
	public function key()
	{
		return $this->rowId;
	}
	
	#[\ReturnTypeWillChange]
	public function next()
	{
		$this->rowId ++;
		$this->current = $this->result->fetch_assoc ();
		
		return $this->current;
	}
	
	#[\ReturnTypeWillChange]
	public function rewind()
	{
		//
	}
	
	#[\ReturnTypeWillChange]
	public function valid()
	{
		return is_array ($this->current ());
	}
	
	/**************************
		COUNTABLE
	***************************/
	#[\ReturnTypeWillChange]
	public function count ()
	{
		return $this->getNumRows ();
	}
	
	// Destruct
	public function __destruct ()
	{
		$this->result->close ();
	}
}
?>
