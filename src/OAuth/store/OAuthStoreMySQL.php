<?php

/**
 * Storage container for the oauth credentials, both server and consumer side.
 * Based on MySQL
 * 
 * @version $Id: OAuthStoreMySQL.php 85 2010-02-19 14:56:40Z brunobg@corollarium.com $
 * @author Marc Worrell <marcw@pobox.com>
 * @date  Nov 16, 2007 4:03:30 PM
 * 
 * 
 * The MIT License
 * 
 * Copyright (c) 2007-2008 Mediamatic Lab
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */


require_once dirname(__FILE__) . '/OAuthStoreSQL.php';


abstract class OAuthStoreMySQL extends OAuthStoreSQL
{
	/**
	 * The MySQL connection 
	 */
	protected $conn;

	
	protected function sql_printf ( $args )
	{
		$sql  = array_shift($args);
		if (count($args) == 1 && is_array($args[0]))
		{
			$args = $args[0];
		}
		$args = array_map(array($this, 'sql_escape_string'), $args);
		return vsprintf($sql, $args);
	}
}


/* vi:set ts=4 sts=4 sw=4 binary noeol: */

?>