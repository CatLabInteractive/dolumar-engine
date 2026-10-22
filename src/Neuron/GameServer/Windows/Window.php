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

class Neuron_GameServer_Windows_Window 
	implements Neuron_GameServer_Interfaces_Window
{
	private 
		$width = 300, 
		$height = 150, 
		$posLeft = null, 
		$posTop = null, 
		$posRight = null,
		$posBottom = null,
		$fixed = false, 
		$className = 'windowContent', 
		$isFixed = 'false',
		$noBorder = 'false',
		$zLevel = 10,
		$bottom = 'auto',
		$title = 'Untitled Window',
		$divId,
		$onlyOnce = 'false',
		$onLoad = false,
		$onFirstload = false,
		$onResize = false,
		$ajaxPollSeconds = null,
		$canClose = true,
		$sPool = 'general',
		
		$minWidth = 100,
		$minHeight = 20,
		
		$modal = false,
		$centered = false,

		$container = 'center',

		$requires = null
	;

	public $channel;
	
	// There we go with the new functions!
	private $sType = 'window';
	private $updates = array (), $dom;
	private $requestData = Array (), $inputData = array ();
	
	private $fGetUrlCallback;
	
	// Windows should know about their server
	private $server;
	
	/*
		Do nothing really...
	*/
	public final function __construct ()
	{
		$this->channel = new Neuron_GameServer_Models_Channel ();

		$this->setUrlCallback (array ($this, '_getUrl'));
		libxml_use_internal_errors(true);
	}

	protected function getXMLNamespace ()
	{
		return 'http://www.w3.org/1999/xhtml';
	}
	
	public function setServer (Neuron_GameServer $server)
	{
		$this->server = $server;
	}
	
	public function getServer ()
	{
		return $this->server;
	}

	public function setContainer ($container)
	{
		$this->container = $container;
	}

	public function getContainer ()
	{
		return $this->container;
	}

	public static function getWindow ($v)
	{
		$classname = 'Neuron_GameServer_Windows_'.ucfirst ($v);
		return new $classname ();
	}
	
	public function setUrlCallback ($callback)
	{
		$this->fGetUrlCallback = $callback;
	}
	
	public function setSettings ()
	{
		
	}
	
	public function setModal ($modal = true)
	{
		$this->modal = $modal == true;
	}
	
	public function setDom ($dom)
	{
		$this->dom = $dom;
	}
	
	public function setDivId ($id)
	{
		$this->divId = $id;
	}
	
	public function getDivId ()
	{
		return $this->divId;
	}
	
	public function getOnload ()
	{
		return $this->onLoad;
	}
	
	public function setOnload ($j)
	{
		$this->onLoad = $j;
	}

	public function getFirstOnload ()
	{
		return $this->onFirstload;
	}
	
	public function setOnFirstload ($j)
	{
		$this->onFirstload = $j;
	}

	public function getOnResize ()
	{
		return $this->onResize;
	}

	public function setOnResize ($t)
	{
		$this->onResize = $t;
	}

	public function setAjaxPollSeconds ($sec)
	{
		$this->ajaxPollSeconds = $sec;
	}
	
	public function setType ($sType)
	{
		$this->sType = $sType;
	}
	
	public function getType ()
	{
		return $this->sType;
	}
	
	public function setPool ($pool)
	{
		$this->sPool = $pool;
	}
	
	/*
		This function makes sure certain trackers are available in the window
		content.
	*/
	public final function getWindowContent ()
	{
		$out = $this->getContent ();
		
		if (!$out || $this->getType () == 'invisible')
		{
			return false;
		}
		
		// Tracker (yes, we need opensocial as well)
		if (isset ($_SESSION['just_logged_in']) && $_SESSION['just_logged_in'])
		{
			$me = Neuron_GameServer::getPlayer ();
			if ($me)
			{
				$out .= '<iframe src="'.htmlentities ($me->getTrackerUrl ('login')).'" width="1" '.
					'height="1" border="0" class="hidden-iframe"></iframe>';
			}
			
			$_SESSION['just_logged_in'] = false;
		}
		
		return $out;
	}

	public function getNewWindow ()
	{
		$contentInput = $this->getWindowContent ();
		
		if ($contentInput || $this->getType () == 'invisible') 
		{
			$dom = $this->dom;
			
			$element = $dom->createElement('openwindow');
			
			// Type
			switch ($this->sType)
			{
				case 'window':
				case 'panel':
				case 'invisible':
					$element->setAttribute ('type', $this->sType);
				break;
				
				default:
					$element->setAttribute ('type', 'window');
				break;
			}
			
			// Size
			$element->setAttribute ('width', $this->width);
			$element->setAttribute ('height', $this->height);
			
			$element->setAttribute ('minWidth', $this->minWidth);
			$element->setAttribute ('minHeight', $this->minHeight);

			if (isset ($this->container))
			{
				$element->setAttribute ('container', $this->container);
			}
			
			// Position
			/*
			$this->posLeft = $left;
			$this->posTop = $top;
			$this->posRight = $right;
			$this->posBottom = $bottom;
			*/
			
			$element->setAttribute ('left', $this->posLeft);
			$element->setAttribute ('top', $this->posTop);
			$element->setAttribute ('right', $this->posRight);
			$element->setAttribute ('bottom', $this->posBottom);
			
			// Title (in CDATA element)
			$element->setAttribute ('title', $this->title);
			
			// Dialog ID (from session) must be unique, but doesn't have to be linear.
			if (!isset ($_SESSION['iDialogCounter']))
			{
				$_SESSION['iDialogCounter'] = time ();
			}
			
			$element->setAttribute ('dialogId', $_SESSION['iDialogCounter']);
			$_SESSION['iDialogCounter'] ++;
			
			$element->setAttribute ('windowId', $this->getWindowId ());
			$element->setAttribute ('className', $this->className);
			
			$element->setAttribute ('onlyOnce', $this->onlyOnce);
			
			$element->setAttribute ('center', $this->centered ? '1' : '0');
			$element->setAttribute ('modal', $this->modal ? '1' : '0');

			$element->setAttribute ('requires', $this->requires);
			
			
			//$element->appendChild ($dom->createElement ('ajaxPollSec', $this->ajaxPollSeconds));
			$element->setAttribute ('pollInterval', $this->ajaxPollSeconds);
			
			// Content (XML)
			if ($this->getType () == 'invisible')
			{
			
			}
			else
			{
				$content = $dom->createElement ('content');
				$content->setAttribute ('xmlns', $this->getXMLNamespace ());
			
				$contentInput = $this->xmlEntities ($contentInput);
			
				$fragment = $dom->createDocumentFragment ();
				if ($fragment->appendXML ($contentInput))
				{
					$content->appendChild ($fragment);
				}
				else
				{
					$this->displayXMLErrors ($contentInput, $content);
				}
			
				$element->appendChild ($content);
			}
			
			
			$element->setAttribute ('onLoad', $this->getOnload () . $this->getFirstOnload ());
			$element->setAttribute ('pool', $this->sPool);
			$element->setAttribute ('closable', $this->canClose);
			
			// Request data
			$content = $dom->createCDATASection (json_encode ($this->getRequestData ()));
			$attribute = $dom->createElement ('requestData');
			$attribute->appendChild ($content);
			$element->appendChild ($attribute);

			$subsribed = $dom->createElement ('subscriptions');
			foreach ($this->channel->getSubscriptions () as $v)
			{
					$el = $dom->createElement ('channel');
					$el->setAttribute ('channel', $v);
					$subsribed->appendChild ($el);
			}
			$element->appendChild ($subsribed);
		
			return $element;
		}
		else
		{
			return false;
		}
	}
	
	/*
		Return the name of this window
	*/
	public function getWindowId ()
	{
		$sn = explode ('_', get_class ($this));
		return $sn[count ($sn) - 1];
	}

	/*
		Equals: checks if this window is a duplicate of another window	
	*/
	public function equals (Neuron_GameServer_Windows_Window $window)
	{
		if ($this->getWindowId () == $window->getWindowId ())
		{
			// Check the request data
			if (json_encode ($this->getRequestData ()) == json_encode ($window->getRequestData ()))
			{
				return true;
			}
		}
		return false;
	}
	
	public function getContent ()
	{
		return '<p>This is a default test window.</p>';
	}
	
	public function setSize ($sizeX, $sizeY = 'auto')
	{
		$this->width = $sizeX;
		$this->height = $sizeY;
	}
	
	public function setMinSize ($sizeX, $sizeY)
	{
		$this->minWidth = $sizeX;
		$this->minHeight = $sizeY;
	}

	public function setNoClose ()
	{
		$this->canClose = false;
	}
	
	public function setPosition ($left, $top, $right = null, $bottom = null)
	{
		$this->posLeft = $left;
		$this->posTop = $top;
		$this->posRight = $right;
		$this->posBottom = $bottom;
	}
	
	public function setFixed ()
	{
		$this->isFixed = 'fixed';
	}
	
	public function setZ ($l)
	{
		$this->zLevel = $l;
	}
	
	public function setClassName ($class)
	{
		$this->setClass ($class);
	}
	
	public function setClass ($class)
	{
		$this->className = $class;
	}
	
	public function setNoBorder ()
	{
		$this->noBorder = 'noborder';
	}
	
	public function setCentered ()
	{
		$this->centered = 'centered';
	}
	
	public function setTitle ($title)
	{
		$title = html_entity_decode ($title, ENT_QUOTES, 'UTF-8');
	
		if (mb_strlen ($title, 'UTF-8') > 30)
		{
			$title = mb_substr ($title, 0, 30, 'UTF-8') . '...';
			if (strpos ($title, '('))
			{
				$title .= ')';
			}
		}
	
		$this->title = $title;
	}
	
	public function setAllowOnlyOnce ()
	{
		$this->onlyOnce = 'true';
	}
	
	public function getRefresh () {}

	private function appendChannelUpdates ($element)
	{
		$dom = $this->dom;
		foreach ($this->channel->getUpdates () as $v)
		{
			$up = $dom->createElement ('update');
			$up->setAttribute ('action', 'channel');
			$up->setAttribute ('chaction', $v['action']);
			$up->setAttribute ('channel', $v['channel']);
			$element->appendChild ($up);
		}
	}

	public function getDOMRefresh ()
	{
		// Process input
		if (count ($this->getInputData ()) > 0)
		{
			$this->processInput ();
		}
	
		else
		{
			// Regular update
			$this->getRefresh ();
		}
		
		$dom = $this->dom;

		if (count ($this->updates) > 0)
		{
			$element = $dom->createElement('updatewindow');
			$element->setAttribute ('windowId', $this->getWindowId ());
			$element->setAttribute ('dialogId', $this->divId);
			
			if ($this->ajaxPollSeconds !== null)
			{
				$element->setAttribute ('pollInterval', $this->ajaxPollSeconds);
			}
			
			// $this->updates (array) has DOM objects which we will append now.
			foreach ($this->updates as $v)
			{
				$element->appendChild ($v);
			}
			
			$content = $dom->createCDATASection (urlencode (json_encode ($this->getRequestData ())));
			$attribute = $dom->createElement ('requestData');
			$attribute->appendChild ($content);
			$element->appendChild ($attribute);
			
			$element->setAttribute ('onLoad', $this->getOnload ());

			$this->appendChannelUpdates ($element);
			
			return $element;
		}
		
		else 
		{
			return false;
		}
	}
	
	public function updateContent ($data = false)
	{
		if ($this->getType () == 'invisible')
		{
			return;
		}
	
		$dom = $this->dom;
	
		if (!$data)
		{
			$data = $this->getWindowContent ();
		}
		
		$update = $dom->createElement ('update');
		//$update->appendChild ($dom->createElement ('action', 'updateContent'));
		$update->setAttribute ('action', 'updateContent');
		
		$content = $dom->createElement ('content');
		$content->setAttribute ('xmlns', $this->getXMLNamespace ());
		
		$data = $this->xmlEntities ($data);
		
		$fragment = $dom->createDocumentFragment ();
		if ($fragment->appendXML ($data))
		{
			$content->appendChild ($fragment);
		}
		else
		{	
			$this->displayXMLErrors ($data, $content);
		}
		
		$update->appendChild ($content);

		$this->updates[] = $update;
	}
	
	private function displayXmlErrors ($data, $content)
	{	
		$dom = $this->dom;
		
		$content->appendChild ($dom->createElement ('h2', 'O-ow! Failed parsing XML'));
		$content->appendChild ($dom->createElement ('p', 'The content if this window is not valid XML. Please contact the game administrator.'));
	
		$a = simplexml_load_string ($data);
		foreach (libxml_get_errors () as $v)
		{
			$content->appendChild ($dom->createElement ('p', $v->line . ':' . $v->column . ': ' . $v->message));
		}
		
		$dommy = $dom->createElement ('textarea', htmlentities ($data));
		$dommy->setAttribute ('style', 'width: 90%; height: 100px;');
		
		$content->appendChild ($dommy);
		
		//customMail ('thijs@catlab.be', 'Invalid xml content debug', $data);
	}
	
	public function javascriptCommand ($command)
	{

	}

	public function mapJump ($x, $y)
	{
		$dom = $this->dom;
		
		$update = $dom->createElement ('update');
		$update->setAttribute ('action', 'scrollmap');
		$update->setAttribute ('x', $x);
		$update->setAttribute ('y', $y);

		$this->updates[] = $update;
	}

	public function updateRequestData ($data)
	{
		if (!is_array ($data))
		{
			$data = array ($data);
		}
		
		$this->setRequestData ($data);
		
		$update = $this->dom->createElement ('update');
		$update->setAttribute ('action', 'updaterequestdata');

		$this->updates[] = $update;
	}

	public function alert ($alert)
	{
		$dom = $this->dom;
		
		$update = $dom->createElement ('update');
		$update->setAttribute ('action', 'alert');
		$update->appendChild ($dom->createElement ('message', $alert));

		$this->updates[] = $update;
	}
	
	public function dialog ($alert, $label1, $action1, $label2 = 'Cancel', $action2 = 'void(0);')
	{
		$dom = $this->dom;
		
		$update = $dom->createElement ('update');
		$update->setAttribute ('action', 'dialog');
		
		$update->appendChild ($dom->createElement ('message', $alert));
		$update->appendChild ($dom->createElement ('label1', $label1));
		$update->appendChild ($dom->createElement ('action1', $action1));
		$update->appendChild ($dom->createElement ('label2', $label2));
		$update->appendChild ($dom->createElement ('action2', $action2));

		$this->updates[] = $update;
	}
	
	public function highlight ($id)
	{
		$dom = $this->dom;
		
		$update = $dom->createElement ('update');
		$update->setAttribute ('action', 'highlight');
		
		$update->appendChild ($dom->createElement ('id', $id));

		$this->updates[] = $update;
	}

	public function addHtmlToElement ($div, $html, $pos = 'top')
	{	
		$dom = $this->dom;
		
		$update = $dom->createElement ('update');
		
		$update->setAttribute ('action', 'addContentToElement');
		$update->setAttribute ('className', $div);
		$update->setAttribute ('position', $pos);

		/*
		$html = $dom->createCDATASection ($html);

		$attribute = $dom->createElement ('content');
		$attribute->appendChild ($html);
		$update->appendChild ($attribute);
		*/
		
		$content = $dom->createElement ('content');
		$content->setAttribute ('xmlns', $this->getXMLNamespace ());
		
		$html = $this->xmlEntities ($html);
		
		$fragment = $dom->createDocumentFragment ();
		if ($fragment->appendXML ($html))
		{
			$content->appendChild ($fragment);
		}
		else
		{	
			$this->displayXMLErrors ($html, $content);
		}
		
		$update->appendChild ($content);

		$this->updates[] = $update;
	}
	
	public function showNewsflash ($html)
	{
		$dom = $this->dom;
		
		$update = $dom->createElement ('update');
		
		$update->setAttribute ('action', 'showNewsflash');

		$content = $dom->createElement ('content');
		$content->setAttribute ('xmlns', $this->getXMLNamespace ());
		
		$html = $this->xmlEntities ($html);
		
		$fragment = $dom->createDocumentFragment ();
		if ($fragment->appendXML ($html))
		{
			$content->appendChild ($fragment);
		}
		else
		{	
			$this->displayXMLErrors ($html, $content);
		}
		
		$update->appendChild ($content);

		$this->updates[] = $update;
	}

	public function updateMap ()
	{
		// Trigger a "reload" map event.
		$dom = $this->dom;
		
		$update = $dom->createElement ('update');
		$update->setAttribute ('action', 'updateMap');
		$this->updates[] = $update;
	}

	public function watchObject (Neuron_GameServer_Map_MapObject $object)
	{
		$dom = $this->dom;
		
		$update = $dom->createElement ('update');
		$update->setAttribute ('action', 'watch');
		$update->setAttribute ('object', $object->getUOID ());

		$this->updates[] = $update;
	}
	
	public function reloadLocation ($x, $y, $z = 0)
	{
		if ($x instanceof Neuron_GameServer_Map_Location)
		{
			$z = $x->z ();
			$y = $x->y ();
			$x = $x->x ();
		}

		$dom = $this->dom;
		
		$update = $dom->createElement ('update');
		$update->setAttribute ('action', 'reloadLocation');
		$update->setAttribute ('x', $x);
		$update->setAttribute ('y', $y);

		$this->updates[] = $update;
	}
	
	public function reloadMap ()
	{
		$dom = $this->dom;
		
		$update = $dom->createElement ('update');
		$update->setAttribute ('action', 'reloadMap');
		
		$this->updates[] = $update;
	}
	
	public function setRequestData ($data)
	{
		//$this->requestData = explode ('&', urldecode ($data));
		if (is_array ($data))
		{
			$this->requestData = $data;
		}
		else
		{
			$this->requestData = json_decode (urldecode ($data), true);
		}
	}
	
	public function getRequestData ()
	{
		return $this->requestData;
	}
	
	public function setJsonInputData ($json)
	{
		$this->setInputData (json_decode ($json, true));
	}

	public function setInputData ($data)
	{
		$this->inputData = $data;
	}
	
	public function getInputData ()
	{
		return $this->inputData;
	}
	
	/*
		Return the value of the request
		or the input data.
	*/
	public function getInput ($key)
	{
		$data = $this->getInputData ();
		$data2 = $this->getRequestData ();
		return isset ($data[$key]) ? $data[$key] : (isset ($data2[$key]) ? $data2[$key] : null);
	}
	
	public function reloadContent ()
	{
		$this->updateContent ();
	}
	
	public function updateTitle ($title = null)
	{
		if (!empty ($title))
		{
			$this->title = $title;
		}
		
		$dom = $this->dom;
		
		$update = $dom->createElement ('update');
		$update->setAttribute ('action', 'changeTitle');
		
		// Put in CData
		$content = $dom->createCDATASection ($this->title);
		
		$attribute = $dom->createElement ('title');
		$attribute->appendChild ($content);
		$update->appendChild ($attribute);
		
		$this->updates[] = $update;
	}

	public function closeWindow ()
	{
		$dom = $this->dom;
		$update = $dom->createElement ('update');
		$update->setAttribute ('action', 'closeWindow');
		$this->updates[] = $update;
	}
	
	public function reloadWindow ()
	{
		$dom = $this->dom;
		$update = $dom->createElement ('update');
		$update->setAttribute ('action', 'reloadWindow');
		$this->updates[] = $update;
	}
	
	public function popupWindow ($url, $width = 600, $height = 400)
	{
		$dom = $this->dom;
		$update = $dom->createElement ('update');
		
		$update->setAttribute ('action', 'popup');
		
		$data = $dom->createElement ('url');
		$data->appendChild ($dom->createCDATASection ($url));
		$update->appendChild ($data);
		
		$update->appendChild ($dom->createElement ('width', $width));
		$update->appendChild ($dom->createElement ('height', $height));
		$this->updates[] = $update;
	}

	public function processInput ()
	{
		$this->updateContent ();
	}
	
	public function throwOkay ($text)
	{
		return '<p class="true">'.$text.'</p>';
	}
	
	public function throwError ($text)
	{
		return '<p class="false">'.$text.'</p>';
	}
	
	/*
		This function generates the links.
	*/
	public function getUrl ($data, $display, $title = null)
	{	
		return call_user_func ($this->fGetUrlCallback, $display, $title);
	}
	
	private function _getUrl ($data, $title)
	{
		$data = str_replace ('"', "'", json_encode ($data));
		
		if (is_array ($title))
		{
			return $title[0].'<a href="javascript:void(0);" onclick="windowAction(this,'.$data.');">'.$title[1].'</a>'.$title[2];
		}
		elseif (isset ($confirm))
		{
			$confirm = str_replace ("'", "\'", $confirm);
			return '<a href="javascript:void(0);" onclick="confirmAction(this,'.$data.',\''.$confirm.'\');">'.$title.'</a>';
		}
		else
		{
			return '<a href="javascript:void(0);" onclick="windowAction(this,'.$data.');">'.$title.'</a>';
		}
	}
	
	private function xmlEntities ($str) 
	{ 
		$xml = array('&#34;','&#38;','&#38;','&#60;','&#62;','&#160;','&#161;','&#162;','&#163;','&#164;','&#165;','&#166;','&#167;','&#168;','&#169;','&#170;','&#171;','&#172;','&#173;','&#174;','&#175;','&#176;','&#177;','&#178;','&#179;','&#180;','&#181;','&#182;','&#183;','&#184;','&#185;','&#186;','&#187;','&#188;','&#189;','&#190;','&#191;','&#192;','&#193;','&#194;','&#195;','&#196;','&#197;','&#198;','&#199;','&#200;','&#201;','&#202;','&#203;','&#204;','&#205;','&#206;','&#207;','&#208;','&#209;','&#210;','&#211;','&#212;','&#213;','&#214;','&#215;','&#216;','&#217;','&#218;','&#219;','&#220;','&#221;','&#222;','&#223;','&#224;','&#225;','&#226;','&#227;','&#228;','&#229;','&#230;','&#231;','&#232;','&#233;','&#234;','&#235;','&#236;','&#237;','&#238;','&#239;','&#240;','&#241;','&#242;','&#243;','&#244;','&#245;','&#246;','&#247;','&#248;','&#249;','&#250;','&#251;','&#252;','&#253;','&#254;','&#255;'); 
		$html = array('&quot;','&amp;','&amp;','&lt;','&gt;','&nbsp;','&iexcl;','&cent;','&pound;','&curren;','&yen;','&brvbar;','&sect;','&uml;','&copy;','&ordf;','&laquo;','&not;','&shy;','&reg;','&macr;','&deg;','&plusmn;','&sup2;','&sup3;','&acute;','&micro;','&para;','&middot;','&cedil;','&sup1;','&ordm;','&raquo;','&frac14;','&frac12;','&frac34;','&iquest;','&Agrave;','&Aacute;','&Acirc;','&Atilde;','&Auml;','&Aring;','&AElig;','&Ccedil;','&Egrave;','&Eacute;','&Ecirc;','&Euml;','&Igrave;','&Iacute;','&Icirc;','&Iuml;','&ETH;','&Ntilde;','&Ograve;','&Oacute;','&Ocirc;','&Otilde;','&Ouml;','&times;','&Oslash;','&Ugrave;','&Uacute;','&Ucirc;','&Uuml;','&Yacute;','&THORN;','&szlig;','&agrave;','&aacute;','&acirc;','&atilde;','&auml;','&aring;','&aelig;','&ccedil;','&egrave;','&eacute;','&ecirc;','&euml;','&igrave;','&iacute;','&icirc;','&iuml;','&eth;','&ntilde;','&ograve;','&oacute;','&ocirc;','&otilde;','&ouml;','&divide;','&oslash;','&ugrave;','&uacute;','&ucirc;','&uuml;','&yacute;','&thorn;','&yuml;'); 
		$str = str_replace($html,$xml,$str); 
		$str = str_ireplace($html,$xml,$str); 
		return $str; 
	} 

	public function requires ($script)
	{
		$this->requires = $script;
	}
}