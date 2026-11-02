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

class BBGS_Credits
{
	private $email;
	private $openid;
	private $openids = [];
	private $openid_hashed = [];
	private $gametoken;
	private $url;

	private $data = array ();

	private $privatekey;

	//const PAYMENT_GATEWAY_URL = 'http://daedeloth.dyndns.org/bbgs/credits/default/spend/';
	const PAYMENT_GATEWAY_URL = 'https://credits.catlab.eu/default/spend/';
	const TRACKER_GATEWAY_URL = 'https://credits.catlab.eu/default/tracker/';
	const NEWSLETTER_GATEWAY_URL = 'https://credits.catlab.eu/default/mail/';

	public function __construct ($gametoken, $site = null)
	{
		$this->gametoken = $gametoken;

		if (!isset ($site))
		{
			$site = $this->curPageURL ();
		}

		$this->addData ('site', $site);
	}

	/***********************************************************
	Setters
	 ************************************************************/
	public function setEmail ($email)
	{
		$this->email = $email;
	}

	public function setOpenID ($openid)
	{
		//$this->openid = md5 ($openid);
		$this->addOpenID ($openid);
	}

	public function addOpenID ($openid)
	{
		if (!isset ($this->openid_hashed))
		{
			$this->openids[] = urlencode ($openid);
			$this->openid_hashed[] = md5 ($openid);
		}

		else
		{
			$this->openids[] .= ',' . urlencode ($openid);
			$this->openid_hashed[] .= '|' . md5 ($openid);
		}
	}

	public function setReferal ($referal)
	{
		$this->addData ('ref', $referal);
	}

	/*
		Some payment gateways won't work when the game
		is in "fullscreen mode". For these gateways it's
		important to set fullscreen when the game is not 
		loaded in an iframe.
	*/
	public function setFullscreen ($fullscreen = true)
	{
		$this->addData ('fullscreen', $fullscreen ? 1 : 0);
	}

	/*
		Sets the container the user is currently in.
		This is important to select the payment gateway
		for third party networks.
	*/
	public function setContainer ($container)
	{
		$this->addData ('container', $container);
	}

	/*
		Set the language the payment gateway will be
		loaded in.
	*/
	public function setLanguage ($language = 'en')
	{
		$this->addData ('lang', $language);
	}

	/*
		Set the private key the data will be signed with.
	*/
	public function setPrivateKey ($key)
	{
		$this->privatekey = $key;
	}

	/*
		A players UserID is optional and is mainly used
		for debugging your payment gateway.
		@param $userid: The userid for this user
	*/
	public function setUserId ($userid)
	{
		$this->addData ('userid', $userid);
	}

	/*
		Set the birthday of this user (to determine age)
		@param $birthday: Birthday of the user in UNIX timestamp
	*/
	public function setBirthday ($birthday)
	{
		$birthday = intval ($birthday);
	}

	/*
		@param $gender: Can be "male" or "female"
	*/
	public function setGender ($gender)
	{
		$gender = strtolower ($gender);
		$this->addData ('gender', $gender == 'male' ? 'male' : 'female');
	}

	/*
		Add custom parameters that can be checked in
		the reporting tool.
	*/
	public function setCustomData ($key, $value)
	{
		$this->addData ('custom_'.$key, $value);
	}

	/***********************************************************
	Logic
	 ************************************************************/

	/*
		Return the amount of credits this user has.
	*/
	public function getCredits ()
	{
		$this->isValidData ();

		$data = array ();

		if (isset ($this->email))
		{
			$data['email'] = $this->email;
		}

		if (count($this->openid_hashed) > 0)
		{
			$data['openidhash'] = $this->openid_hashed[0];
			$data['openid'] = $this->openids[0];
		}

		$data = array_merge
		(
			$this->data,
			$data
		);

		$url = $this->getSignedURL (self::PAYMENT_GATEWAY_URL.'getcredits/', $data);

		$data = $this->file_get_contents ($url);
		if ($data !== false)
		{
			return $data;
		}
		else
		{
			return -1;
		}

		return false;
	}

	/*
		Convert credits into "proper credits"
		(use this to display)
	*/
	public function convert ($amount)
	{
		$this->isValidData ();

		$data = array ();

		if (isset ($this->email))
		{
			$data['email'] = $this->email;
		}

		if (count($this->openid_hashed) > 0)
		{
			$data['openidhash'] = $this->openid_hashed[0];
			$data['openid'] = $this->openids[0];
		}

		$data['amount'] = $amount;

		$data = array_merge
		(
			$this->data,
			$data
		);

		$url = $this->getSignedURL (self::PAYMENT_GATEWAY_URL.'convert/', $data);

		$data = $this->file_get_contents ($url);
		if ($data !== false)
		{
			return json_decode ($data, true);
		}

		return $amount;
	}

	/*
		Refund credits to the player.
		(Warning, you are giving free credits to this by calling this method player)
	*/
	public function refundCredits ($credits, $description, $action = null)
	{
		$this->isValidData ();

		$data = array ();

		if (isset ($this->email))
		{
			$data['email'] = $this->email;
		}

		if (count($this->openid_hashed) > 0)
		{
			$data['openidhash'] = $this->openid_hashed[0];
			$data['openid'] = $this->openids[0];
		}

		$data['amount'] = intval ($credits);
		$data['description'] = $description;

		$data = array_merge
		(
			$this->data,
			$data
		);

		if (isset ($action))
		{
			$data['tag'] = $action;
		}

		$data = array_merge
		(
			$this->data,
			$data
		);

		$url = $this->getSignedURL (self::PAYMENT_GATEWAY_URL.'refundcredits/', $data);

		$content = $this->file_get_contents ($url);
		return $content == "1";
	}

	public function buyCredits ()
	{
		$this->isValidData ();

		$data = array ();

		if (isset ($this->email))
		{
			$data['email'] = $this->email;
		}

		if (count($this->openid_hashed) > 0)
		{
			$data['openidhash'] = $this->openid_hashed[0];
			$data['openid'] = $this->openids[0];
		}

		$data = array_merge
		(
			$this->data,
			$data
		);

		return $this->getSignedURL (self::PAYMENT_GATEWAY_URL.'buy/', $data);
	}

	public function sendNewsletter ($subject, $content, $text, $language)
	{
		$data = array ();

		$data['newsletter_subject'] = $subject;
		$data['newsletter_content'] = $content;
		$data['newsletter_text'] = $text;
		$data['newsletter_language'] = $language;

		return $this->file_get_contents ($this->getSignedURL (self::NEWSLETTER_GATEWAY_URL.'send/', $data));
	}

	/*
		Request the URL to approve a credit transfer
	
		@param $credits: Amount of credits that will be requested
		@param $description: Short description of the use of these credits
		@param $callback: URL that will be contacted once the transfer is approved
		@param $action: A short tag to group your transfers
		@param $return_url: URL where the user will be redirected
	*/
	public function requestCredits ($credits, $description, $callback, $action = null, $return_url = null)
	{
		$this->isValidData ();

		$data = array ();

		if (isset ($this->email))
		{
			$data['email'] = $this->email;
		}

		if (count($this->openid_hashed) > 0)
		{
			$data['openidhash'] = $this->openid_hashed[0];
			$data['openid'] = $this->openids[0];
		}

		$data['amount'] = intval ($credits);
		$data['callback'] = $callback;
		$data['description'] = $description;

		if (isset ($action))
		{
			$data['tag'] = $action;
		}

		if (isset ($return_url))
		{
			$data['return'] = $return_url;
		}

		$data = array_merge
		(
			$this->data,
			$data
		);

		return $this->getSignedURL (self::PAYMENT_GATEWAY_URL.'approve/', $data);
	}

	/*
		Use this method to check if a callback is valid
	*/
	public function isRequestValid ($id, $secret)
	{
		$data = array
		(
			'id' => $id,
			'secret' => $secret
		);

		$url = $this->getSignedURL (self::PAYMENT_GATEWAY_URL.'check/', $data);

		$data = $this->file_get_contents ($url);
		if ($data)
		{
			if ($data == '1')
			{
				return true;
			}
		}

		return false;
	}

	/*
		@param $action: login / register
		
		Load this URL in an iframe in order to get the right trackers
	*/
	public function getTrackerUrl ($action)
	{
		$data = array
		(
			'tracker' => $action
		);

		if (isset ($this->email))
		{
			$data['email'] = $this->email;
		}

		if (count($this->openid_hashed) > 0)
		{
			$data['openidhash'] = $this->openid_hashed[0];
			$data['openid'] = $this->openids[0];
		}

		$url = $this->getSignedURL (self::TRACKER_GATEWAY_URL.'track/', $data);

		return $url;
	}

	/***********************************************************
	Helper functions
	 ************************************************************/
	private function curPageURL ()
	{
		$pageURL = 'http';
		if (isset ($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") { $pageURL .= "s"; }
		$pageURL .= "://";
		if ($_SERVER["SERVER_PORT"] != "80")
		{
			$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
		}
		else
		{
			$pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
		}

		return $pageURL;
	}

	/*
		Checks to see if all required data is available
	*/
	private function getPrivateKey ()
	{
		if (!isset ($this->privatekey))
		{
			throw new Exception ('Private key not set.');
		}

		return $this->privatekey;
	}

	public function isValidData ($exception = true)
	{
		if (!isset ($this->privatekey)) {
			return false;
		}

		if (isset ($this->email) && !empty ($this->email))
		{
			return true;
		}

		if (isset ($this->openids) && count ($this->openids) > 0)
		{
			return true;
		}

		if (isset ($this->openid_hashed) && count ($this->openid_hashed) > 0)
		{
			return true;
		}

		if ($exception)
		{
			throw new Exception ('You need to supply an email or OpenID url.');
		}

		return false;
	}

	private function addData ($key, $value)
	{
		$this->data[$key] = $value;
	}

	private function getSignedUrl ($url, $parameters = array ())
	{
		$parameters['client'] = $this->gametoken;
		$parameters['time'] = time ();

		$parameters['signature'] = base64_encode ($this->getSignature ($parameters));

		$out = $url.'?';
		foreach ($parameters as $k => $v)
		{
			$out .= $k . "=" . urlencode ($v) . "&";
		}

		return $out;
	}

	private function getSignature ($data)
	{
		$privatekey = $this->getPrivateKey ();

		$data = $this->prepareDataString ($data);

		openssl_sign
		(
			$data,
			$binary_signature,
			$privatekey,
			OPENSSL_ALGO_SHA1
		);

		return $binary_signature;
	}

	private function rawurlencode ($input)
	{
		return str_replace('+', ' ', str_replace('%7E', '~', $this->rawurlencode($input)));
	}

	private function prepareDataString ($data)
	{
		unset ($data['query']);
		unset ($data['signature']);

		uksort ($data, 'strcmp');

		$out = "";
		foreach ($data as $k => $v)
		{
			$out .= $k . "=" . urlencode ($v) . "&";
		}
		$out = substr ($out, 0, -1);

		return $out;
	}

	private function file_get_contents ($url)
	{
		$profiler = Neuron_Profiler_Profiler::getInstance ();

		$profiler->start ('Fetching ' . $url);

		//Initialize the Curl session 
		$ch = curl_init();

		//Set curl to return the data instead of printing it to the browser. 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		//Set the URL 
		curl_setopt($ch, CURLOPT_URL, $url);

		// Set a reasonable timeout
		curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 5);

		//Execute the fetch 
		$data = curl_exec($ch);

		if ($error = curl_error ($ch))
		{
			$profiler->message ('Error: ' . $error);
		}

		//Close the connection 
		curl_close($ch);

		$profiler->stop ();

		return $data;
	}
}