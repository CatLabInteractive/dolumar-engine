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

class Neuron_GameServer_Windows_Tutorial extends Neuron_GameServer_Windows_Help
{

	public function setSettings ()
	{
	
		$text = Neuron_Core_Text::__getInstance ();
	
		// Window settings
		$this->setSize ('250px', '250px');
		$this->setTitle ($text->get ('tutorial', 'menu', 'main'));
		
		$this->setClassName ('help');
		
		$this->setNoClose ();
		
		$this->setPosition (null, 40, 15);
		
		//$this->setAllowOnlyOnce ();
	}
	
	public function getFooter ()
	{
		$out = '<p class="navigation">Page ';
		
		// Count the pages
		$pagecount = $this->getTutorialPageCount ();
		
		$pages = array ();
		for ($i = 1; $i <= $pagecount; $i ++)
		{
			$pages[$i] = $this->prefix.'/Tutorial'.$i;
		}
		$pages['Close'] = 'window:close';
		
		foreach ($pages as $k => $v)
		{
			$out .= $this->getLink ($v, $k, trim ($this->page) == trim ($v)).' ';
		}
		
		$out .= '</p>';
		
		return $out;
	}
	
	private function getTutorialPageCount ()
	{
		$cache = Neuron_Core_Memcache::getInstance ();
		
		if (!$count = $cache->getCache ('tutorial_count_cache'))
		{
			$i = 1;
		
			while ($this->hasContent ($this->prefix.'/Tutorial'.($i + 1)))
			{
				$i ++;
			}
			
			$count = $i;
			
			$cache->setCache ('tutorial_count_cache', $count);
		}
		
		return $count;
	}
	
	protected function getCloseWindow ()
	{
		$input = $this->getInputData ();
		
		$text = Neuron_Core_Text::__getInstance ();
		
		if (isset ($input['confirm']) && $input['confirm'])
		{
			$player = Neuron_GameServer::getPlayer ();
			if ($player)
			{
				$player->setPreference ('closeTutorial', 1);
			}
		
			$this->closeWindow ();
		}
		else
		{
			$out = '<p>'.$text->get ('closeTutorial', 'help', 'main').'</p>';
		
			$out .= '<p><a href="javascript:void(0);" '.
				'onclick="windowAction (this, {\'page\':\'window:close\',\'confirm\':1})">'.
				$text->get ('closeTutorialBtn', 'help', 'main').'</a></p>';
				
			return $out;
		}
	}
	
	/*
		Due to netlog translation services...
		We are going to put the tutorial in 
		the language files.
		
		Muchos betteros, no?
	*/
	/**
	 * Map a tutorial page ("<prefix>/Tutorial1") to its template name,
	 * or null when the page name is not a plain identifier.
	 */
	public static function getTutorialTemplateName ($prefix, $sFile)
	{
		$sFile = substr ((string)$sFile, strlen ((string)$prefix) + 1);

		if (!preg_match ('/^[a-zA-Z0-9_]+$/D', $sFile))
			return null;

		return 'tutorial/'.$sFile.'.phpt';
	}

	protected function getHelpFile ($sFile)
	{
		$page = new Neuron_Core_Template ();
		
		$filename = self::getTutorialTemplateName ($this->prefix, $sFile);
		
		if ($filename !== null && $page->hasTemplate ($filename))
		{
			return $page->parse ($filename);
		}
		else
		{
			return null;
		}
	}
}
