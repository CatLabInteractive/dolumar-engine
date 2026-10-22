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

class Neuron_Core_Tools
{

	/*
		Resources to text
	*/
	public static function getRandomRuneOptions ()
	{
		return array
		(
			'earth',
			'fire',
			'water',
			'wind'
		);
	}
	
	public static function output_distance ($distance, $abbrevation = false, $round = false)
	{
		if ($distance === false)
		{
			return null;
		}
		else
		{
			$distance = Dolumar_Map_Map::tile2league ($distance);
		
			if ($round)
			{
				$distance = round ($distance);
			}
			else
			{
				$distance = round ($distance, 1);
			
				if ($distance == floor ($distance))
				{
					$distance .= '.0';
				}
			}
			
			return $distance . ($abbrevation ? 'L' : ' league');
		}
	}
	
	/*
		Modern version of the function.
	*/
	public static function getResourceToText ($resources, $income = array (), $capacity = array (), $premiumlinks = false)
	{
		return self::resourceToText
		(
			$resources,
			true,
			true,
			false,
			'rune',
			true,
			$income,
			$capacity,
			$premiumlinks
		);
	}
	
	public static function resourceToText 
	(
		$res, 
		$showRunes = true, 
		$dot = true, 
		$village = false, 
		$runeId = 'rune', 
		$html = true, 
		$income = array (),
		$capacity = array (),
		$premiumlinks = false
	)
	{
		$text = Neuron_Core_Text::__getInstance ();
		
		$txt = '';
		$o = array ();
		
		if (isset ($res['gold']) && $res['gold'] > 0) 	{ $o[] = 'gold'; }
		if (isset ($res['grain']) && $res['grain'] > 0) { $o[] = 'grain'; }
		if (isset ($res['wood']) && $res['wood'] > 0) 	{ $o[] = 'wood'; }
		if (isset ($res['stone']) && $res['stone'] > 0)	{ $o[] = 'stone'; }
		if (isset ($res['iron']) && $res['iron'] > 0)	{ $o[] = 'iron'; }
		if (isset ($res['gems']) && $res['gems'] > 0)	{ $o[] = 'gems'; }

		$end = count ($o) - 1;
		
		if ($html)
		{
			$txt = '<span class="cost-resources">'; 
		}
		
		foreach ($o as $k => $v)
		{
			if ($html)
			{
				$sText = '<span class="resource '.$v.'" title="'.ucfirst ($text->get ($v, 'resources', 'main')).'"> ';
				if (isset ($income[$v]))
				{
					$sInc = $income[$v] >= 0 ? '+'.$income[$v] : $income[$v];
					
					if (isset ($capacity[$v]))
					{
						$sInc .= '/'.$capacity[$v];
					}
					
					$sText .= '<span class="increasing amount" title="'.$sInc.'">';

					if ($premiumlinks)
					{
						$sText .= '<a href="javascript:void(0);" onclick="' .
							"openWindow('premiumshop', {}, {'action':'buyresources'});" . '">';
							
						$sText .= $res[$v];
						$sText .= '</a>';
					}
					else
					{
						$sText .= $res[$v];
					}
					
					$sText .= '</span>';
				}
				else
				{
					$sText .= $res[$v];
				}
				$sText .= '<span> '.ucfirst ($text->get ($v, 'resources', 'main')) . '</span>';
				$sText .= '</span>';
				
			}
			else
			{
				$sText = $res[$v] . ' ' . $text->get ($v, 'resources', 'main');
			}

			if ($k < $end)
			{
				$txt .= $html ? $sText . '<span class="sign">,</span> ' : $sText . ', ';
			}

			elseif ($end == 0 && $dot)
			{
				$txt .= $html ? $sText . '<span class="sign">.</span> ' : $sText . '. ';
			}
			
			elseif ($end == 0)
			{
				$txt .= $sText;
			}
			
			else 
			{
				if ($html)
				{
					$txt = substr ($txt, 0, -28) . '<span class="and"> ' . $text->get ('and', 'main', 'main') . '</span> ';
				}
				else
				{
					$txt = substr ($txt, 0, -2) . ' ' . $text->get ('and', 'main', 'main') . ' ';
				}
					
				$txt .= $sText;
				if ($dot) 
				{
					$txt .= $html ? '<span class="sign">.</span> ' : '. ';
				}
			}
		}
		
		if ($html)
		{
			$txt .= '</span>';
		}
		
		//if (!$dot) { $txt = substr ($txt, 0, -2); }
		if (!empty ($res['runeId']) && $res['runeAmount'] > 0 && $showRunes)
		{
			// Get village runes
			if ($village)
			{
				$myRunes = $village->resources->getRunes ();
			}
			if ($res['runeId'] == 'random' && $html)
			{
				$txt .= '<br /><select class="selectRune building_'.$runeId.'" name="runeSelection">';
				
				$runes = self::getRandomRuneOptions ();
				
				$txt .= '<option value="random">'.$text->get ('random', 'runeSingle', 'main').'</option>';
				
				foreach ($runes as $v)
				{
					$sRune = $text->get ($v, $res['runeAmount'] > 1 ? 'runeDouble' : 'runeSingle', 'main');
				
					$t = Neuron_Core_Tools::putIntoText 
					(
						$text->get ('requires', 'build', 'building'), 
						array 
						(
							$res['runeAmount'].' '.$sRune
						)
					);

					if ($village && isset ($myRunes[$v]) && $myRunes[$v] >= $res['runeAmount'])
					{
						$txt .= '<option class="hasRunes" value="' .$v. '">'.$t.'</option>';
					}

					elseif ($village)
					{
						$txt .= '<option class="hasntRunes" value="' .$v. '">'.$t.'</option>';
					}

					else
					{
						$txt .= '<option value="' .$v. '">'.$t.'</option>';
					}
				}
				
				$txt .= '</select>';
			}
			
			elseif ($html)
			{
				$sRune = $text->get ($res['runeId'], $res['runeAmount'] > 1 ? 'runeDouble' : 'runeSingle', 'main');
			
				$t = Neuron_Core_Tools::putIntoText 
				(
					$text->get ('requires', 'build', 'building'), 
					array 
					(
						'<span title="'.$res['runeAmount'] . ' ' . $sRune.'" class="rune '.$res['runeId'].'">'.
						$res['runeAmount'].'<span> '.$sRune.'</span></span>'
					)
				);

				if ($village && isset ($myRunes[$res['runeId']]) && $myRunes[$res['runeId']] >= $res['runeAmount'])
				{
					$txt .= '<br /><span class="hasRunes">' . $t . '</span>';
				}
				else
				{
					$txt .= '<br /><span class="hasntRunes">' . $t . '</span>';
				}
			}
			
			else
			{
				$sRune = $text->get ($res['runeId'], $res['runeAmount'] > 1 ? 'runeDouble' : 'runeSingle', 'main');
			
				$t = Neuron_Core_Tools::putIntoText 
				(
					$text->get ('requires', 'build', 'building'), 
					array 
					(
						$res['runeAmount'].' '.$sRune
					)
				);

				if ($village && isset ($myRunes[$res['runeId']]) && $myRunes[$res['runeId']] >= $res['runeAmount'])
				{
					$txt .= "\n" . $t;
				}
				else
				{
					$txt .= "\n" . $t;
				}
			}
		}
		
		return $txt;
	}

	/*
		Translate a mysql date - field to a unix timestamp.
		Returns false if date is not set.
	*/
	public static function dateToTimestamp ($date)
	{
		$db = Neuron_DB_Database::__getInstance ();
		return $db->toUnixtime ($date);
	
		/*
		return $date != '0000-00-00' ? 
			gmmktime (
			
				0,
				0,
				1,
				substr ($date, 5, 2),
				substr ($date, 8, 2),
				substr ($date, 0, 4)
			 
		): false;
		*/
	}

	public static function datetimeToTimestamp ($date)
	{
		$db = Neuron_DB_Database::__getInstance ();
		return $db->toUnixtime ($date);
		//return strtotime ($date);
	}
	
	/*
		This one is total bullshit.
	*/
	/*
	public static function dateToMysql ($day, $month, $year)
	{
		return self::addZeros ($year, 4).'-'.self::addZeros ($month, 2).'-'.self::addZeros ($day, 2);
	}
	*/
	
	/*
		This one even more
	*/
	/*
	public static function datetimeToMysql ($day, $month, $year, $hour, $minute, $seconds)
	{
		return self::addZeros ($year, 4).
			'-'.self::addZeros ($month, 2).
			'-'.self::addZeros ($day, 2)
			.' '.self::addZeros ($hour, 2)
			.':'.self::addZeros ($minute, 2).
			':'.self::addZeros ($seconds, 2);
	
	}
	*/
	
	public static function timestampToMysql ($time = null)
	{
		if ($time == null)
		{	
			$time = time ();
		}
		
		$db = Neuron_DB_Database::__getInstance ();
		return $db->fromUnixtime ($time);
		//return self::dateToMysql (date ('d', $time), date ('m', $time), date ('Y', $time));
	
	}
	
	public static function timestampToMysqlDatetime ($time = null)
	{
		if ($time == null)
		{
			$time = time ();
		}
		
		$db = Neuron_DB_Database::__getInstance ();
		return $db->fromUnixtime ($time);
		
		//return self::datetimeToMysql (date ('d', $time), date ('m', $time), date ('Y', $time), 
		//	date ('H', $time), date ('i', $time), date ('s', $time));
	
	}

	public static function getArrayFirstValue ($a)
	{
		foreach ($a as $k => $v)
			return array ($k, $v);
	}
	
	public static function addZeros ($int, $totaal)
	{
		while (strlen ($int) < $totaal)
		{	
			$int = "0".$int;
		}
		return $int;
	}

	public static function getInput ($dat, $key, $type, $default = false)
	{

		$dat = $GLOBALS[$dat] ?? null;

		if (!isset ($dat[$key])) {

			return $default;
		}

		else {
			// Check if the value has the right type
			if (Neuron_Core_Tools::checkInput ($dat[$key], $type)) 
			{
				return $dat[$key];
			}

			else 
			{
				return $default;
			}
		}
	}

	public static function checkInput ($value, $type)
	{
		if ($type == 'bool' || $type == 'text')
		{

			return true;
		
		}
		
		elseif ($type == 'varchar')
		{
			return true;
		}
		
		elseif ($type == 'password')
		{
			return strlen ($value) > 2;
		}
		
		elseif ($type == 'email')
		{
			return strlen ($value) > 2;
		}
		
		elseif ($type == 'username')
		{
			return preg_match ('/^[a-zA-Z0-9_]{3,20}$/', $value);
		}
		
		elseif ($type == 'village')
		{
			$chk = preg_match ('/^[a-zA-Z0-9\' ]{3,40}$/', $value);
			$value = trim ($value);
			$notempty = !empty ($value);
			$chk = $chk && $notempty;
			return $chk;
		}

		elseif ($type == 'unitname')
		{
			$chk = preg_match ('/^[a-zA-Z0-9 ]{3,20}$/', $value);
			$value = trim ($value);
			$notempty = !empty ($value);
			$chk = $chk && $notempty;
			return $chk;
		}
		
		elseif ($type == 'md5')
		{
		
			return strlen ($value) == 32;
		
		}
		
		elseif ($type == 'int')
		{
		
			return is_numeric ($value);
		
		}
		
		else {
		
			return false;
			echo 'fout: '.$type;
		
		}

	}
	
	public static function convert_price ($basic_price)
	{
	
		$basic_price = str_replace (",", ".", $basic_price);
		$basic_price = number_format ($basic_price, 2, ",", "");
		
		return $basic_price;
	
	}

	public static function putIntoText ($text, $ar = array(), $delimiter = '@@') 
	{
		foreach ($ar as $k => $v) 
		{
			if (is_string ($v) || is_float ($v) || is_int ($v))
			{
				$text = str_replace ($delimiter.$k, $v, $text);
			}
			else if (is_object ($v))
			{
				$text = str_replace ($delimiter.$k, (string)$v, $text);	
			}
			else
			{
				throw new Exception ("putIntoText excepts an array.");
			}
		}
		
		// Remove all remaining "putIntoTexts"
		$text = preg_replace ('/'.$delimiter.'([^ ]+)/s', '', $text);
		
		return $text;
	}

	public static function output_title ($title)
	{

		return htmlspecialchars ($title, ENT_QUOTES, 'UTF-8');

	}
	
	public function date_long ($stamp)
	{
	
		$text = Neuron_Core_Text::__getInstance ();
		
		$dag = $text->get ('day'.(date ('w', $stamp) + 1), 'days', 'main');
		$maand = $text->get ('mon'.date ('m', $stamp), 'months', 'main');
	
		return Neuron_Core_Tools::putIntoText (
			$text->get ('longDateFormat', 'dateFormat', 'main'),
			array
			(
				$dag,
				date ('d', $stamp),
				$maand,
				date ('Y', $stamp)
			)
		);
	
	}
	
	public static function splitLongWords ($input)
	{
	
		$array = explode (' ', $input);
		
		foreach ($array as $k => $v)
		{
		
			$array[$k] = wordwrap ($v, 20, ' ', 1);
		
		}
		
		return implode (' ', $array);
	
	}
	
	public static function output_text ($convert, $tags = true, $div = true, $list = true, $layout = true)
	{
		return Neuron_NBBC_Parser::parse ($convert);
	
		//$input = Neuron_Core_Tools::splitLongWords ($input);
		/*

		$convert = htmlspecialchars ($convert, ENT_QUOTES, 'UTF-8');

		// Config: breaks:
		$p_open = '<p>';
		$p_close = '</p>';
		$p_break = '<br />';

		if ($tags)
		{
			$codeDoorgeven = array ();
		
			$codes = array ();
		
			// codes eruit halen
			preg_match_all ("/\[code\](.*?)\[\/code\]/is", $convert, $codes);
			
			$count = 0;
			
			foreach($codes[0] as $nor => $var1) 
			{
				$replace = '[tmp-code-block]'.$count.'[/tmp-code-block]';
				$pom = $codes[0][$nor];
				$convert = str_replace ($pom, $replace, $convert);
				
				$codeDoorgeven[$count] = $codes[1][$nor];
				$count ++;
			}
		
			$bbcode_regex = array
			(
				 0 => '/\[b\](.+?)\[\/b\]/s',
				 1 => '/\[i\](.+?)\[\/i\]/s',
				 2 => '/\[u\](.+?)\[\/u\]/s',
				 5 => '/\[url\](.+?)\[\/url\]/s',
				 6 => '/\[url\=(.+?)\](.+?)\[\/url\]/s',
				 7 => '/\[img\](.+?)\[\/img\]/s',
				 8 => '/\[img\=noresize\](.+?)\[\/img\]/s',
				 9 => '/\[help\](.+?)\[\/help\]/s',
				10 => '/\[open\=(.+?)\](.+?)\[\/open\]/s',
				11 => "/\[quote\](.*?)\[\/quote\]/s"
			);
			
			if ($layout)
			{
				$bbcode_regex = array_merge
				(
					$bbcode_regex,
					array
					(
						100 => '/\[col\=(.+?)\](.+?)\[\/col\]/s',
						101 => '/\[color\=(.+?)\](.+?)\[\/color\]/s',
						102 => '/\[colour\=(.+?)\](.+?)\[\/colour\]/s',
						103 => '/\[size\=(.+?)\](.+?)\[\/size\]/s'
					)
				);
			}

			$bbcode_replace = array
			(
				 0 => '<span style="font-weight:bold;">$1</span>',
				 1 => '<span style="font-style:italic;">$1</span>',
				 2 => '<span style="text-decoration:underline;">$1</span>',
				 5 => '<a href="$1" target="_BLANK">$1</a>',
				 6 => '<a href="$1" target="_BLANK">$2</a>',
				 7 => '<a href="$1" target="_BLANK" rel="lightbox"><img src="$1" alt="User submitted image" /></a>',
				 8 => '<img class="noresize" src="$1" alt="User submitted image" />',
				 9 => '<a href="javascript:void(0);" onclick="openWindow(\'help\',{\'page\':\'$1\'});">$1</a>',
				10 => '<a href="javascript:void(0);" onclick="openWindow(\'$1\',{});">$2</a>',
				11 => '<blockquote><p>$1</p></blockquote>',
				
				100 => '<span style="color:$1;">$2</span>',
				101 => '<span style="color:$1;">$2</span>',
				102 => '<span style="color:$1;">$2</span>',
				103 => '<span style="font-size:$1;">$2</span>'
			);

			$convert = preg_replace ($bbcode_regex, $bbcode_replace, $convert);		

			//$convert = str_replace("[/quote]", "</blockquote>" . $p_open, $convert);
		
			// Headers
			if ($layout)
			{
				$convert = preg_replace (
					"/\[h(.*?)](.*?)\[\/h(.*?)]/si",
					'<h\\1>\\2</h\\1>',
					$convert
				);
			}

			// Hyperlinks
			$convert = eregi_replace(
				"\[url]([^\[]*)\[/url]",
				"<a target=\"_BLANK\" href=\"\\1\">\\1</a>", $convert);
		
			$convert = eregi_replace(
				"\[url=([^\[]*)\]([^\[]*)\[/url]",
				"<a target=\"_BLANK\" href=\"\\1\">\\2</a>", $convert);

			// Images align=left
			$convert = eregi_replace(
				"\[img]([-_./a-zA-Z0-9!&%#?,'=:~]+)\[/img]",
				"<a href=\"\\1\" target=\"_BLANK\" rel=\"lightbox\"><img src=\"\\1\" /></a>", $convert);
		
			// Images align=left
			$convert = eregi_replace(
				"\[img:([-_./a-zA-Z0-9!&%#?,'=:~]+)\]([-_./a-zA-Z0-9!&%#?,'=:~]+)\[/img]",
				"<a href=\"\\2\" target=\"_BLANK\" rel=\"lightbox\"><img align=\"\\1\" src=\"\\2\" /></a>", $convert);
				
			// codes erin steken
			foreach($codeDoorgeven as $a => $b) 
			{
				$sk = '[tmp-code-block]'.$a.'[/tmp-code-block]';
				$replace = $b;
				$convert = str_replace ($sk, $replace, $convert);
			}
		}
		
		// Lists (*)
		$convert = self::textToParagraph ($convert, $list);
		
		if ($tags)
		{
			// Let's go for the smileys
			$smileys = array 
			(
				':)' 	=> 'smile',
				':-)'	=> 'smile',
			
				':('	=> 'sad',
				':-('	=> 'sad',
			
				':D'	=> 'grin',
				':-D'	=> 'grin',
			
				':P' 	=> 'tease',
				':-P' => 'tease',
				':p' 	=> 'tease',
				':-p'	=> 'tease',
			
				';)'	=> 'wink',
				';-)'	=> 'wink',
			
				':|'	=> 'frown',
				':-|'	=> 'frown',
			
				'[:/]'	=> 'unsure',
				'[:-/]'	=> 'unsure',
			
				':@'	=> 'angry',
				':-@'	=> 'angry'
			);
		
			foreach ($smileys as $k => $v)
			{
				$convert = str_replace ($k, '<img src="'.SMILEY_DIR . $v . '.png" alt="'.$k.'" class="smiley" />', $convert);
				$convert = str_replace ('['.$k.']', '<img src="'.SMILEY_DIR . $v . '.png" alt="'.$k.'" class="smiley" />', $convert);
			}
		}
		
		if ($div)
		{
			$convert = '<div class="text">'.$convert.'</div>';
		}
		
		return $convert;
		*/
	}
	
	public static function textToParagraph ($convert, $showLists = true)
	{
		$lines = explode ("\n", $convert);
		
		$convert = "";
		
		$inList = false;
		$inPa = false;
		
		foreach ($lines as $v)
		{
			$v = trim ($v);
			
			if (substr ($v, 0, 1) == '*' && $showLists)
			{
				if (!$inList)
				{
					if ($inPa)
					{
						$convert .= '</p>';
						$inPa = false;
					}
				
					$convert = $convert . '<ul>';
					$inList = true;
				}
				
				$convert .= '<li>'.substr ($v, 1).'</li>';
			}
			else
			{
				if ($inList)
				{
					$convert .= '</ul>';
					$inList = false;
				}
				
				// Block elements don't need paragraphcs
				if (substr ($v, 0, 2) == '<h')
				{
					if ($inPa)
					{
						$convert .= '</p>';
					}
					
					$convert .= $v;
				}
				
				// Regular text
				else
				{
					if (empty ($v))
					{
						if ($inPa)
						{
							$convert .= '</p>';
							$inPa = false;
						}
					}
					
					else
					{
						if (!$inPa)
						{
							$convert .= '<p>';
							$inPa = true;
						}
						
						$convert .= $v . '<br />';
					}
				}
			}
		}
		
		if ($inList)
		{
			$convert .= '</ul>';
		}
		
		elseif ($inPa)
		{
			$convert .= '</p>';
		}
		
		$convert = str_replace ('<p><br />', '<p>', $convert);
		$convert = str_replace ('<br /></p>', '</p>', $convert);
		$convert = str_replace ('<p></p>', '', $convert);
		
		return $convert;
	}
	
	public static function output_form ($text)
	{
	
		return htmlspecialchars (($text) , ENT_QUOTES, 'UTF-8');
	
	}
	
	public static function output_varchar ($text)
	{
	
		$input = Neuron_Core_Tools::splitLongWords ($text);
		return htmlspecialchars (($text), ENT_QUOTES, 'UTF-8');
	
	}

	public static function color_mkwebsafe ( $in )
	{
		// put values into an easy-to-use array
		$vals['r'] = hexdec( substr($in, 0, 2) );
		$vals['g'] = hexdec( substr($in, 2, 2) );
		$vals['b'] = hexdec( substr($in, 4, 2) );
		
		// loop through
		foreach( $vals as $val )
		{
		// convert value
		$val = ( round($val/51) * 51 );
		// convert to HEX
		$out .= str_pad(dechex($val), 2, '0', STR_PAD_LEFT);
		}
		
		return $out;
	}
	
	public static function getConfirmLink ()
	{
	
		return 'confirmed';
	
	}
	
	public static function checkConfirmLink ($link)
	{
		return ($link == self::getConfirmLink ());
	}
	
	public static function getCountdown ($future, $class = 'counter')
	{
		$timeLeft = $future - time () + 1;
		
		$hours = floor ($timeLeft / 3600);
		$minutes = floor (($timeLeft - $hours * 3600) / 60);
		$seconds = $timeLeft - $hours * 3600 - $minutes * 60;
		
		if ($hours < 10 && $hours >= 0) $hours = '0'.$hours;
		if ($minutes < 10 && $minutes >= 0) $minutes = '0'.$minutes;
		if ($seconds < 10 && $seconds >= 0) $seconds = '0'.$seconds;
	
		return '<span class="'.$class.'">'.$hours.':'.$minutes.':'.$seconds.'</span>';
	}

	public static function getDuration ($duration)
	{
		$hours = floor ($duration / 3600);
		$minutes = floor ( ($duration - $hours * 3600) / 60 );
		$seconds = floor ( $duration - $hours * 3600 - $minutes * 60 );

		if ($hours < 10) { $hours = '0' . $hours; }
		if ($minutes < 10) { $minutes = '0' . $minutes; }
		if ($seconds < 10) { $seconds = '0' . $seconds; }

		if ($hours > 0)
		{
			$dur = $hours . ':' . $minutes . ':' . $seconds;
		}

		else
		{
			$dur = $minutes . ':' . $seconds;
		}

		return $dur;
	}

	public static function getDurationText ($duration, $short = false)
	{
		$text = Neuron_Core_Text::__getInstance ();
		
		$space = " ";
		$gName = 'datetime';
		if ($short)
		{
			$gName .= '_short';
			$space = "";
		}
	
		$day = 24 * 60 * 60;
		$hour = 60 * 60;
		$minute = 60;

		$days = floor ($duration / $day);
		$duration -= $days * $day;

		$hours = floor ($duration / $hour);
		$duration -= $hours * $hour;

		$minutes = floor ($duration / $minute);
		$duration -= $minutes * $minute;

		$seconds = $duration;

		$txt = null;

		if ($days > 1)
			$txt .= $days .$space.$text->get ('days', $gName, 'main').", ";
		elseif ($days > 0)
			$txt .= $days .$space.$text->get ('day', $gName, 'main').", ";
			
		if ($hours > 1)
			$txt .= $hours .$space.$text->get ('hours', $gName, 'main').", ";
		elseif ($hours > 0)
			$txt .= $hours .$space.$text->get ('hour', $gName, 'main').", ";


		if ($minutes > 1)
			$txt .= $minutes .$space.$text->get ('minutes', $gName, 'main').", ";
		elseif ($minutes > 0)
			$txt .= $minutes .$space.$text->get ('minute', $gName, 'main').", ";

		if ($seconds > 1)
			$txt .= $seconds .$space.$text->get ('seconds', $gName, 'main').", ";
		elseif ($seconds > 0)
			$txt .= $seconds .$space.$text->get ('second', $gName, 'main').", ";

		return substr ($txt, 0, -2);
	}
	
	public static function checkIE6 ()
	{
		if (isset ($_SERVER['HTTP_USER_AGENT']))
		{
			$ua = $_SERVER['HTTP_USER_AGENT'];
			if (strpos($ua,'MSIE') != false && strpos($ua,'Opera') === false)
			{
				$false = true;
				if (strpos($ua,'Windows NT 5.2') != false) {
					if(strpos($ua,'.NET CLR') === false) $false = false;
				}
	
				if (substr($ua,strpos($ua,'MSIE')+5,1) < 7 && $false)
				{
					return true;
				}
			}
		}
		return false;
	}

	public static function floor_array ($array)
	{
		foreach ($array as $k => $v)
		{
			$array[$k] = floor ($v);
		}
		return $array;
	}

	public static function splitInPages 
	(
		$page, 
		$total, 
		$current = 0, 
		$perpage = 10, 
		$maxAantalSnelclicks = 10, 
		$action = '', 
		$module = 'module',
		$pagetoken = 'page'
	)
	{
		$pages = ceil ($total / $perpage);

		$maxAantalSnelclicks --;
	
		if ($current < 1)
		{
			$current = 1;
		}

		$deHelft = floor ($maxAantalSnelclicks / 2);
		if ($current < $deHelft)
		{
			$snelcount = 1;
			$morevar = $deHelft - $current + 1;
		} 

		else
		{
			$snelcount = $current - $deHelft;
			$morevar = $deHelft;
		}

		if ($current > ($pages - $deHelft) && $current > $deHelft)
		{
			$snelvar = $pages - $current;
			$snelcount = $snelcount - ($morevar - $snelvar);
		}
		
		if ($snelcount < 1)
		{ 
			$snelcount = 1; 
		}

		$snelmax = $snelcount + $maxAantalSnelclicks;

		// replace the stuff
		$pS = $snelcount;
		$pE = min ($snelmax, $pages);
	
		$page->set ('pagelist_curpage', $current);
		$page->set ('pagelist_total', $pages);
		$page->set ('pagelist_start', $pS);
		$page->set ('pagelist_end', $pE);
		
		$page->set ('pagelist_firstpage', 1);
		$page->set ('pagelist_lastpage', $pages);
		
		// Action data
		$l_action = $action;
		
		if (!is_array ($action))
		{
			$l_action = array ();
			$tmp = explode ('&', $action);
			foreach ($tmp as $v)
			{
				$tmp2 = explode ('=', $action);
				if (count ($tmp2) == 2)
					$l_action[$tmp2[0]] = $tmp2[1];
			}
		}			
		
		$page->set ('pagelist_firstpage_url', self::splitInPages_getPageUrl ($pagetoken, $module, 1, $l_action, '&lt;'));
		$page->set ('pagelist_previous_url', self::splitInPages_getPageUrl ($pagetoken, $module, $current - 1, $l_action, '&laquo;'));
		
		$page->set ('pagelist_lastpage_url', self::splitInPages_getPageUrl ($pagetoken, $module, $pages, $l_action, '&gt;'));
		$page->set ('pagelist_nextpage_url', self::splitInPages_getPageUrl ($pagetoken, $module, $current + 1, $l_action, '&raquo;'));
		
		$shortcuts = array ();
		for ($i = $pS; $i <= $pE; $i ++)
		{
			$shortcuts[] = array
			(
				'page' => $i,
				'url' => self::splitInPages_getPageUrl ($pagetoken, $module, $i, $l_action, $i)
			);
		}
		
		$page->set ('pagelist_shortcuts', $shortcuts);

		return array
		(
			'limit' => ( ($current - 1) * $perpage) . ', ' . $perpage,
			'start' => ($current-1) * $perpage,
			'perpage' => $perpage
		);
	}
	
	private static function splitInPages_getPageUrl ($pagetoken, $module, $page, $data, $name)
	{
		$data[$pagetoken] = $page;
	
		return Neuron_URLBuilder::getInstance ()->getUpdateUrl 
		(
			$module, 
			$name,
			$data
		);
	}
	
	public static function writexml (XMLWriter $xml, $data, $item_name = 'item')
	{
		foreach($data as $key => $value)
		{
			if (is_int ($key))
			{
				$key = $item_name;
			}

			if (is_array($value))
			{
				if ($key != 'items')
				{
					$xml->startElement($key);
				}
				
				if (isset ($value['attributes']) && is_array ($value['attributes']))
				{
					foreach ($value['attributes'] as $k => $v)
					{
						$xml->writeAttribute ($k, $v);
					}
					
					unset ($value['attributes']);
				}
				
				Neuron_Core_Tools::writexml ($xml, $value, substr ($key, 0, -1));
				
				if ($key != 'items')
				{
					$xml->endElement();
				}
			}
			
			elseif ($key == 'element-content')
			{
				$xml->text ($value);
			}
	
			else
			{
				$xml->writeElement($key, $value);
			}
		}
	}
	
	public static function output_xml ($data, $version = '0.1', $root = 'root', $parameters = array (), $sItemName = 'item')
	{	
		$xml = new XmlWriter();
		$xml->openMemory();
		$xml->startDocument('1.0', 'UTF-8');
		$xml->startElement($root);
		$xml->setIndent (true);
		
		if (!empty ($version))
		{
			$xml->writeAttribute ('version', $version);
		}
		
		foreach ($parameters as $paramk => $paramv)
		{
			$xml->writeAttribute ($paramk, $paramv);
		}

		Neuron_Core_Tools::writexml ($xml, $data, $sItemName);

		$xml->endElement();
		return $xml->outputMemory(true);
	}
	
	private static function xml_escape ($input)
	{
		//$input = str_replace ('"', '&quot;', $input);
		//$input = str_replace ("'", '&apos;', $input);
		
		
		$input = str_replace ('<', '&lt;', $input);
		$input = str_replace ('>', '&gt;', $input);
		$input = str_replace ('&', '&amp;', $input);
		
	
		return $input;
	}
	
	public static function output_partly_xml ($data, $key =  null)
	{
		$output = '<'.$key;
		
		if (isset ($data['attributes']) && is_array ($data['attributes']))
		{
			foreach ($data['attributes'] as $k => $v)
			{
				$output .= ' '.$k.'="'.$v.'"';
			}
		
			unset ($data['attributes']);
		}
		
		$output .= '>';
		if (!is_array ($data))
		{
			$output .= self::xml_escape ($data);
		}
		
		elseif (count ($data) == 1 && isset ($data['element-content']))
		{
			$output .= self::xml_escape ($data['element-content']);
		}
		
		else
		{
			foreach ($data as $k => $v)
			{
				if (is_numeric ($k))
				{
					$k = substr ($key, 0, -1);
				}
				
				$output .= self::output_partly_xml ($v, $k);
			}
		}
		$output .= '</'.$key.'>'."\n";
		
		return $output;
	}
}

?>
