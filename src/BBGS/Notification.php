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

/*
	This class constructs a notification and sends it to the server.
*/
class BBGS_Notification
{
	private $sMessage;
	private $iTimestamp;
	private $sId;
	private $sGroup;
	
	private $sSkeletons;
	private $sSkeleton;
	private $sValues;
	
	private $sXML;
	
	private $sVisibility;
	private $sLanguage;
	
	private $privatekey;
	
	private $attachments = array ();

	/*
		Construct a notification with it's 2 required
		parameters. More information can (and should) be
		set with the setters below.
	*/
	public function __construct ($sMessage, $iTimestamp = null, $language = 'en')
	{
		if (!isset ($iTimestamp))
		{
			$iTimestamp = time ();
		}
		
		$this->sMessage = $sMessage;
		$this->iTimestamp = $iTimestamp;
		
		$this->sVisibility = 'private';
		$this->sLanguage = $language;
		
		$this->sSkeletons = array ();
		$this->sValues = array ();
		
		$this->sXML = array ();
		$this->sXML['text'] = $sMessage;
		$this->sXML['id'] = null;
		$this->sXML['group'] = null;
		$this->sXML['skeleton'] = null;
		$this->sXML['arguments'] = array ();
		$this->sXML['attachments'] = array ();
	}
	
	public function addImage ($imgurl, $name, $link)
	{
		$this->attachments[] = array
		(
			'type' => 'image',
			'src' => $imgurl,
			'name' => $name,
			'link' => $link
		);
		
		$this->sXML['attachments'] = array ();
		
		$out = array ();
		$out['attributes'] = array ('type' => 'image');
		$out['src'] = $imgurl;
		$out['name'] = $name;
		$out['link'] = $link;
		
		$this->sXML['attachments'][] = $out;
	}
	
	public function setDescription ($description)
	{
		$this->sXML['description'] = $description;
	}
	
	public function setPrivateKey ($key)
	{
		$this->privatekey = $key;
	}
	
	/*
		Set the visiblity.
	*/
	public function setVisibility ($visibility)
	{
		switch ($visibility)
		{
			case 'private':
			case 'public':
				$this->sVisibility = $visibility;
			break;
		}
	}
	
	public function setIcon ($sUrl)
	{
		$this->sXML['favicon'] = $sUrl;
	}
	
	/*
		Set a notifications group and ID.
		For example: group "messages" contains "sent" and "received"
	*/
	public function setId ($group, $id)
	{
		$this->sGroup = $group;
		$this->sId = $id;
		
		$this->sXML['id'] = $id;
		$this->sXML['group'] = $group;
	}
	
	/*
		Set the user data
	*/
	public function setTargetData ($data)
	{
		$this->sXML['target'] = $data;
	}
	
	/*
		Set the user data
	*/
	public function setSenderData ($data)
	{
		$this->sXML['sender'] = $data;
	}
	
	/*
		Add a skeleton in a given language
		@param $sLanguage: a 2-letter representation of the language.
	*/
	public function setSkeleton ($sSkeleton)
	{		
		$this->sSkeleton[] = array ($sSkeleton);
		$this->sXML['skeleton'] = $sSkeleton;
	}
	
	/*
		Take an array $aValues and put it in the skeletons
	*/
	public function addArgument ($value, $type = 'text', $additionalData = array ())
	{
		$additionalData['value'] = $value;
	
		$this->sValues[] = array ((string)$value, $type, $additionalData);
		
		$this->sXML['arguments'][] = array
		(
			'items' => $additionalData,
			'attributes' => array ('type' => $type)
		);
	}
	
	private function getPrivateKey ()
	{
		if (!isset ($this->privatekey))
		{
			throw new Exception ('Private key not set.');
		}
	
		return $this->privatekey;
	}
	
	private function getSignature ($data)
	{
		$privatekey = $this->getPrivateKey ();
		
		openssl_sign 
		(
			$data, 
			$binary_signature,
			$privatekey,
			OPENSSL_ALGO_SHA1
		);
		
		return $binary_signature;
	}
	
	/*
		And the most important function of them all:
		send this notification to the users notifaction URL.
	*/
	public function send ($sUrl)
	{
		$key = $this->getPrivateKey ();
	
		$xml = self::output_xml 
		(
			$this->sXML, 
			1, 
			'notification', 
			array 
			(
				'visibility' => $this->sVisibility, 
				'lang' => $this->sLanguage
			)
		);
		
		// And now: send the notification!
		$postfields = array
		(
			'text' => $this->sMessage,
			'date' => date ('Y-m-d\TH:i:s', $this->iTimestamp),
			'xml' => $xml,
			'signature' => base64_encode ($this->getSignature ($xml)),
			'type' => 'notification'
		);
		
		// Make sure curl doesn't think that we're trying to upload files
		foreach ($postfields as $k => $v)
		{
			if (substr ($v, 0, 1) == '@')
			{
				// Let's hope the receive will trim the response.
				// It's only used for the bare message anyway.
				$postfields[$k] = ' '.$v;
			}
		}
		
		if (defined ('NOTIFICATION_DEBUG'))
		{
			$postfields['debug'] = true;
			
			echo '<h2>Preparing data to send:</h2><pre>';
			echo htmlentities (print_r ($postfields, true));
			echo '</pre>';
			
			echo '<h2>Sending data</h2>';
			echo '<p>Contacting <span style="color: red;">'.$sUrl.'</span>... ';
			
			echo '<form method="post" action="' . $sUrl . '">';
			foreach ($postfields as $k => $v)
			{
				echo '<textarea col="0" row="0" type="hidden" name="'.$k.'" style="visibility: hidden; width: 0px; height: 0px;">'.htmlentities ($v).'</textarea>';
			}
			echo '<button type="submit">Test call</button>';
			echo '</form>';
		}
		
		try
		{
			if (function_exists ('curl_init'))
			{
				$ch = curl_init ();
		
				curl_setopt($ch, CURLOPT_URL, $sUrl);
				//curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);// allow redirects
				curl_setopt($ch, CURLOPT_RETURNTRANSFER,1); // return into a variable
				//curl_setopt($ch, CURLOPT_TIMEOUT, 3); // times out after 4s
				curl_setopt($ch, CURLOPT_POST, true); // set POST method
				curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields); // add POST fields
		
				// execute curl command
				$result = curl_exec($ch); // run the whole process
			}
		}
		catch (Exception $e)
		{
			// Do nothing. The notification was not sent.
		}
		
		// Show if debug is on.
		if (defined ('NOTIFICATION_DEBUG'))
		{
			if (!$result)
			{
				echo '<p>cURL error: '.curl_error ($ch).'</p>';
			}

			echo 'Done!</p>';
			echo '<h2>Beginning notification output:</h2>';
			echo '<pre>'.htmlentities($result).'</pre>';
			echo '<h2>End of notification output.</h2>';
		}

		$result = json_decode ($result, true);

		return array
		(
			'success' => $result['success'] ? true : false,
			'iframe' => isset ($result['iframe']) ? $result['iframe'] : null
		);
	}
	
	/*
		XML BUILDER
	*/
	private static function output_xml ($data, $version = '0.1', $root = 'root', $parameters = array (), $sItemName = 'item')
	{	
		$xml = new XmlWriter();
		$xml->openMemory();
		$xml->startDocument('1.0', 'UTF-8');
		$xml->startElement($root);
		
		if (!empty ($version))
		{
			$xml->writeAttribute ('version', $version);
		}
		
		foreach ($parameters as $paramk => $paramv)
		{
			$xml->writeAttribute ($paramk, $paramv);
		}

		self::writexml ($xml, $data, $sItemName);

		$xml->endElement();
		return $xml->outputMemory(true);
	}
	
	private static function output_partly_xml ($data, $key =  null)
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
			$output .= $data;
		}
		
		elseif (count ($data) == 1 && isset ($data['element-content']))
		{
			$output .= $data['element-content'];
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
	
	private static function writexml (XMLWriter $xml, $data, $item_name = 'item')
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
				
				self::writexml ($xml, $value, substr ($key, 0, -1));
				
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
}
?>
