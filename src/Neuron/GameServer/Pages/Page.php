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

class Neuron_GameServer_Pages_Page
{
	/*
		Return the HTML
	*/
	public function getOutput ()
	{
		return $this->getHTML ();
	}
	
	public function getHTML ()
	{
		header("Content-Type: text/html; charset=UTF-8");
	
		$page = new Neuron_Core_Template ();
		$page->set ('body', $this->getOuterBody ());
		$page->set ('stylesheet', 'page');
		$page->set ('static_client_url', '');
		
		foreach ($this->getJavascript () as $v)
		{
			$page->addListValue ('javascripts', $v);
		}
		
		return $page->parse ('pages/index.phpt');
	}
	
	/*
		Return the whole body of the template.
	*/
	public function getOuterBody ()
	{
		$page = new Neuron_Core_Template ();
		
		$page->set ('body', $this->getBody ());
		
		return $page->parse ('pages/body.phpt');
	}
	
	public static function getUrl ($sUrl, $sArrs = array (), $sBase = 'page/')
	{
		// Admin_Page::getUrl passes null when there are no parameters.
		$sArrs = $sArrs ?? array ();

		if (!isset ($_COOKIE['session_id']))
		{
			$sArrs['session_id'] = session_id ();
		}
	
		$out = ABSOLUTE_URL.$sBase.$sUrl;
		if (count ($sArrs) > 0)
		{
			$out .= '?';
			foreach ($sArrs as $k => $v)
			{
				$out .= $k .'='.urlencode ($v).'&';
			}
			$out = substr ($out, 0, -1);
		}
		return $out;
	}
	
	protected function getParameter ($id)
	{
		$id = intval ($id);

		$data = explode ('/', isset ($_GET['module']) ? $_GET['module'] : null);

		if (isset ($data[$id]))
		{
			return $data[$id];
		}
		
		return null;
	}
	
	protected function getJavascript ()
	{
		return array ('admin');
	}
	
	/*
		Return the body of the page
	*/
	public function getBody ()
	{
		return null;
	}
}
?>
