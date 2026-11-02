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
	This class manages the connection to the premium
	credits server.
*/
class Neuron_GameServer_Credits
{
	private $objUser;
	private $sToken;

	private $error;

	private $objCredits;

	private $convertcache = array ();

	/**
	 * @return BBGS_Credits|null
	 */
	public static function getPureCreditsObject ()
	{
		if (
			!defined('CREDITS_GAME_TOKEN') ||
			!defined('CREDITS_PRIVATE_KEY')
		) {
			return null;
		}

		$out = new BBGS_Credits(CREDITS_GAME_TOKEN);
		$out->setPrivateKey (CREDITS_PRIVATE_KEY);

		return $out;
	}

	/**
	 * @param Neuron_GameServer_Player $objUser
	 */
	public function __construct (Neuron_GameServer_Player $objUser)
	{
		$this->objUser = $objUser;

		$this->objCredits = self::getPureCreditsObject ();
		if (!$this->objCredits) {
			return;
		}

		if ($this->objUser->isEmailCertified ()) {
			$this->objCredits->setEmail ($this->getEmail ());
		}

		$this->objCredits->setReferal ($objUser->getReferal ());

		foreach ($objUser->getOpenIDs () as $v) {
			$this->objCredits->addOpenID ($v);
		}

		$container = isset ($_SESSION['opensocial_container']) ?
			$_SESSION['opensocial_container'] : null;

		if (isset ($container)) {
			$this->objCredits->setContainer ($container);
		}

		$fullscreen = isset ($_SESSION['fullscreen']) && $_SESSION['fullscreen'] ? 1 : 0;
		$this->objCredits->setFullscreen ($fullscreen);

		$this->objCredits->setLanguage (Neuron_Core_Text::getInstance ()->getCurrentLanguage ());

		$this->objCredits->setUserId ($objUser->getId ());

		if (isset ($_SESSION['birthday']))
		{
			$this->objCredits->setBirthday ($_SESSION['birthday']);
		}

		if (isset ($_SESSION['gender']))
		{
			$this->objCredits->setGender (strtolower ($_SESSION['gender']) == 'm' ? 'male' : 'female');
		}
	}

	/**
	 * @return string
	 */
	private function getEmail ()
	{
		return trim (strtolower ($this->objUser->getEmail ()));
	}

	/**
	 * @return array|bool|int|mixed|null
	 * @throws Exception
	 */
	public function getCredits ()
	{
		if (!$this->objCredits) {
			return null;
		}

		if (!$this->objCredits->isValidData (false)) {
			return 0;
		}

		try
		{
			return $this->objCredits->getCredits ();
		}
		catch (BadMethodCallException $e)
		{
			$this->error = 'email_not_set';
			return false;
		}
	}

	/**
	 * @return bool
	 */
	public function useCredit ()
	{
		return true;
	}

	/**
	 * @return null|string
	 */
	public function getBuyUrl ()
	{
		if (!$this->objCredits) {
			return null;
		}

		//return PREMIUM_URL . '?email='.$this->getEmail().'&token='.$this->sToken;
		return $this->objCredits->buyCredits ();
	}

	/**
	 * @param $amount
	 * @param $description
	 * @param $action
	 * @return bool|null
	 */
	public function refundCredits ($amount, $description, $action)
	{
		if (!$this->objCredits) {
			return null;
		}

		return $this->objCredits->refundCredits ($amount, $description, $action);
	}

	/**
	 * This function connects to the credit gateway
	 * and checks if a certain transaction exists.
	 *
	 * If the transaction exists, it handles it
	 * and returns TRUE.
	 *
	 * @param $data
	 * @param $transactionId
	 * @param $transactionKey
	 * @return bool|null
	 */
	public function handleUseRequest ($data, $transactionId, $transactionKey)
	{
		if (!$this->objCredits) {
			return null;
		}

		if (isset ($_POST['transaction_id']) && isset ($_POST['transaction_secret']))
		{
			$valid = $this->objCredits->isRequestValid ($_POST['transaction_id'], $_POST['transaction_secret']);

			if ($valid)
			{
				$amount = $_POST['transaction_amount'];
				$this->objUser->useCredit ($amount, $data);

				return true;
			}
			else
			{
				$this->error = 'This request was not valid or already executed. Ignore.';
			}
		}
		else
		{
			$this->error = 'No post data received.';
		}

		return false;
	}

	/**
	 * @param int $amount
	 * @param array $data
	 * @param string $description
	 * @param string $action
	 * @return bool|null|string
	 */
	public function getUseUrl ($amount = 1, $data = array (), $description = 'Premium features', $action = 'premium')
	{
		if (!$this->objCredits) {
			return null;
		}

		$callback = API_FULL_URL.'spendCredit/'.
			'?key='.md5($this->getEmail()).
			'&id='.$this->objUser->getId();

		foreach ($data as $k => $v)
		{
			$callback .= '&' . urlencode ($k) . '=' . urlencode ($v);
		}

		try
		{
			return $this->objCredits->requestCredits ($amount, $description, $callback, $action);
		}
		catch (BadMethodCallException $e)
		{
			$this->error = 'email_not_set';
			return false;
		}
	}

	/**
	 * @param int $amount
	 * @return mixed
	 */
	private function getConvertData ($amount = 1)
	{
		if (!$this->objCredits) {
			// Credits service not configured: no conversion possible.
			return array ('amount' => $amount, 'text' => (string) $amount, 'html' => (string) $amount);
		}

		if (!isset ($this->convertcache[$amount])) {
			$this->convertcache[$amount] = $this->objCredits->convert ($amount);
		}
		return $this->convertcache[$amount];
	}

	/**
	 * @param int $amount
	 * @return mixed
	 */
	public function convertCredits ($amount = 1)
	{
		$data = $this->getConvertData ($amount);
		return $data['amount'];
	}

	/**
	 * @param $amount
	 * @param bool|false $html
	 * @return mixed
	 */
	public function getCreditDisplay ($amount, $html = false)
	{
		$data = $this->getConvertData ($amount);
		return $html ? $data['html'] : $data['text'];
	}

	/**
	 * @param $sTracker $sTracker ID of the tracker, for example: "registration"
	 * @return null|string
	 * @throws Exception
	 */
	public function getTrackerUrl ($sTracker)
	{
		if (!$this->objCredits) {
			return null;
		}

		if (!$this->objCredits->isValidData()) {
			return null;
		}

		return $this->objCredits->getTrackerUrl ($sTracker);
	}

	/**
	 * @return string
	 */
	public function getError ()
	{
		return $this->error;
	}
}