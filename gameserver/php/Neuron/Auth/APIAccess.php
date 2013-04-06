<?php
require_once 'OAuth/OAuthServer.php';

class Neuron_Auth_APIAccess
{
	public static function isValid ()
	{
		return self::getInstance ()->_isValidRequest ();
	}

	public static function getUser ()
	{
		if ($this->isValid ())
		{
			return self::getInstance ()->_getUser ();
		}
		return null;
	}

	/**
	* Get request body
	*/
	public static function getBody ()
	{
		return self::getInstance ()->_getBody ();
	}

	/**
	* Internals
	*/
	private static function getInstance ()
	{
		static $in;
		if (!isset ($in))
		{
			$in = new self ();
		}
		return $in;
	}

	private $request;
	private $isvalid;
	private $userid;

	private function __construct ()
	{

	}

	private function checkConnection ()
	{
		if (!isset ($this->isvalid))
		{
			Neuron_Auth_OAuthStore::getStore (); 
			if (OAuthRequestVerifier::requestIsSigned())
			{
				try
				{
					$this->request = new OAuthRequestVerifier();
					$req = $this->request;

					$user_id = $req->verify();

					// If we have an user_id, then login as that user (for this request)
					if ($user_id)
					{
						$this->userid = $user_id;
						$this->isvalid = true;

						return true;
					}
				}
				catch (OAuthException $e)
				{
					// The request was signed, but failed verification
					header('HTTP/1.1 401 Unauthorized');
					header('WWW-Authenticate: OAuth realm=""');
					header('Content-Type: text/plain; charset=utf8');

					echo $e->getMessage();
					exit();
				}
			}
		}
		return false;
	}

	private function _isValidRequest ()
	{
		$this->checkConnection ();
		return $this->isvalid;
	}

	private function _getUserId ()
	{
		$this->checkConnection ();
		return $this->userid;
	}

	private function _getBody ()
	{
		$this->checkConnection ();
		return $this->request->getBody ();
	}
}