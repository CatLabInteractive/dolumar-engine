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

class Neuron_Core_Text
{

	protected $cache;
	protected $root_dir;
	protected $backup = false;
	protected $inFile, $inSection;
	protected $tag;
	private $pathname;

	/*
		Return an instance
	*/
	public static function __getInstance ()
	{
		static $in;
		if (empty ($in)) 
		{
			if (isset ($_SESSION['opensocial_messagebundle']))
			{
				$in = new Neuron_Core_MessageBundleText ($_SESSION['opensocial_messagebundle']);
			}
			else
			{
				$in = new Neuron_Core_Text ();
			}
		}
		return $in;
	}
	
	public static function getInstance ()
	{
		return self::__getInstance ();
	}

	public function __construct ($language = null, $baseText = 'en', $pathname = null)
	{
		if (!isset ($language) && defined ('LANGUAGE_TAG'))
		{
			$language = LANGUAGE_TAG;
		}

		if (!isset ($pathname))
		{
			$pathname = LANGUAGE_DIR;
			$this->backup = new Neuron_Core_Text ($language, $baseText, CATLAB_LANGUAGE_PATH);
		}

		else if ($baseText && $baseText != $language)
		{
			$this->backup = new Neuron_Core_Text ($baseText, false);
		}
		
		// Take text
		if (isset ($language))
		{
			$this->root_dir = $pathname.$language;
			$this->tag = $language;
		}

		else 
		{
			echo 'Language directory not defined.';
			exit ();
		}

	}
	
	public function setLanguage ($language)
	{
		$this->root_dir = LANGUAGE_DIR.$language;
		$this->tag = $language;
		
		// Remove cache
		$this->cache = array ();
	}
	
	public function setFile ($file)
	{
	
		$this->inFile = $file;
	
	}
	
	public function setSection ($section)
	{
		$this->inSection = $section;
	}

	public function get ($id, $section = null, $file = null, $alternative = null)
	{
		// Section & fill
		if (empty ($section))
		{
			$section = $this->inSection;
		}
		
		if (empty ($file))
		{
			$file = $this->inFile;
		}

		// Check if the file is loaded already
		if (empty ($this->cache[$file])) 
		{
			$this->load_file ($file);
		}

		// Check if the id exist
		if (empty ($this->cache[$file][$section][$id])) 
		{
			if ($this->backup && $this->backup->get ($id, $section, $file, false))
			{
				return $this->backup->get ($id, $section, $file, false);
			}
			elseif ($alternative === null) 
			{
				return 'Text Not Found: '.$id.' ('.$file.', '.$section.', '.$this->root_dir.')';
			}
			else 
			{
				return $alternative;
			}
		}
		else {
			return $this->cache[$file][$section][$id];
		}
	}
	
	public function getSection ($section, $file)
	{
	
		// Check if the file is loaded already
		if (empty ($this->cache[$file]))
		{
			$this->load_file ($file);
		}
		
		$output = array ();
		
		if (!empty ($this->cache[$file][$section])) 
		{
			foreach ($this->cache[$file][$section] as $k => $v) 
			{
				if (!empty ($v)) {
					$output[$k] = $v;
				}
			}
		}
		
		if (count ($output) == 0)
		{
			$output = $this->backup->getSection ($section, $file);
		}
		
		return $output;
	}
	
	public function getRandomLine ($section, $file, $iNumber = false)
	{
		$section = array_values ($this->getSection ($section, $file));
		
		$total = count ($section);
		
		if ($total < 1)
		{
			return null;
		}
		
		if (!$iNumber)
		{
			$iNumber = mt_rand (0, $total);
		}
		
		return $section[$iNumber % $total];
	}
	
	public function getFile ($inFile, $return = 'error')
	{
	
		$file = $this->root_dir.'/templates/'.$inFile.'.txt';
		
		if (is_readable ($file)) 
		{
			return file_get_contents ($file);
		}
		
		elseif ($this->backup && $this->backup->getFile ($inFile, false))
		{
			return $this->backup->getFile ($inFile);
		}
		
		else {
			if ($return === 'error')
			{
				return ('404: Unable to load file '.$file.'.');
			}
			
			else return $return;
		}
	}
	
	public function getTemplate ($file, $fields = array ())
	{
		return Neuron_Core_Tools::putIntoText
		(
			$this->getFile ($file),
			$fields
		);
	}

	protected function load_file ($file)
	{
		$f = $this->root_dir.'/'.$file.'.lng';
		
		if (is_readable ($f)) 
		{
			$this->cache[$file] = parse_ini_file ($f, true);
		}
	}
	
	/*
		Return the abasolute path to the
		text file.
	*/
	public function getPath ()
	{
		return $this->root_dir . '/';
	}
	
	public function getClickto ($txt)
	{
		return Array
		(
			$this->get ('clickto1', 'main', 'main').' ',
			$this->get ('clickto2', 'main', 'main'),
			' '.$txt
		);
	}

	public static function getLanguages ()
	{
		$o = array ();
		$dir = scandir (LANGUAGE_DIR);
		foreach ($dir as $file)
		{
			if ($file != '.' && $file != '..' && strlen ($file) == 2)
			{
				$o[] = $file;
			}
		}
		return $o;
	}
	
	public function getCurrentLanguage ()
	{
		return $this->tag;
	}
}
