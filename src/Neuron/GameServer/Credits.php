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

/**
 * Credits of one player, backed by CatLab Accounts.
 * Every method returns null / does nothing while credits are not configured.
 */
class Neuron_GameServer_Credits
{
	private $objUser;

	private $error;

	/** @var Neuron_GameServer_Credits_AccountsClient|false|null test override; false = off */
	private static $clientOverride = null;


	/**
	 * @param Neuron_GameServer_Credits_AccountsClient|false|null $client false forces "off", null restores config
	 */
	public static function setClientOverride ($client)
	{
		self::$clientOverride = $client;
	}

	/**
	 * @return Neuron_GameServer_Credits_AccountsClient|null
	 */
	public static function getPureCreditsObject ()
	{
		if (self::$clientOverride === false)
		{
			return null;
		}
		if (self::$clientOverride !== null)
		{
			return self::$clientOverride;
		}
		return Neuron_GameServer_Credits_AccountsClient::fromConfig ();
	}

	/**
	 * @param Neuron_GameServer_Player $objUser
	 */
	public function __construct (Neuron_GameServer_Player $objUser)
	{
		$this->objUser = $objUser;
		$this->objCredits = self::getPureCreditsObject ();
	}

	private $objCredits;

	/**
	 * Credit balance, or null when credits are off, the player has no
	 * Accounts token yet, or Accounts is unreachable.
	 *
	 * @return int|null
	 */
	public function getCredits ()
	{
		if (!$this->objCredits) {
			return null;
		}

		$auth = Neuron_GameServer_Mappers_CatLabAuthMapper::getInstance ()->getForPlayer ($this->objUser);
		if ($auth === null || $auth['access_token'] === '') {
			return null;
		}

		try
		{
			return $this->objCredits->getBalance ($auth['access_token']);
		}
		catch (Neuron_GameServer_Credits_AccountsUnavailable $e)
		{
			$this->error = 'credits_unavailable';
			return null;
		}
		catch (Neuron_GameServer_Credits_AccountsRejected $e)
		{
			$this->error = 'credits_unavailable';
			return null;
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

		return $this->objCredits->getBuyUrl ();
	}

	/**
	 * Manual (admin) refund of $amount credits to the player's profile.
	 *
	 * @param int $amount
	 * @param string $description
	 * @param string|null $action unused
	 * @return bool|null
	 */
	public function refundCredits ($amount, $description, $action = null)
	{
		if (!$this->objCredits) {
			return null;
		}

		$auth = Neuron_GameServer_Mappers_CatLabAuthMapper::getInstance ()->getForPlayer ($this->objUser);
		if ($auth === null || $auth['profile_id'] === null) {
			return false;
		}

		try
		{
			return $this->objCredits->refund ((int) $auth['profile_id'], (int) $amount, (string) $description, null);
		}
		catch (Neuron_GameServer_Credits_AccountsUnavailable $e)
		{
			return false;
		}
		catch (Neuron_GameServer_Credits_AccountsRejected $e)
		{
			return false;
		}
	}

	/**
	 * Local start link. The price and the action are decided server side when
	 * the link is followed; $amount and $description are not used.
	 *
	 * @param int $amount unused
	 * @param array $data
	 * @param string $description unused
	 * @param string $action unused
	 * @return null|string
	 */
	public function getUseUrl ($amount = 1, $data = array (), $description = 'Premium features', $action = 'premium')
	{
		if (!$this->objCredits) {
			return null;
		}

		return API_FULL_URL . 'creditstart/?' . http_build_query ($data);
	}

	/**
	 * @param int $amount
	 * @return mixed
	 */
	public function convertCredits ($amount = 1)
	{
		return $amount;
	}

	/**
	 * @param $amount
	 * @param bool|false $html
	 * @return mixed
	 */
	public function getCreditDisplay ($amount, $html = false)
	{
		return (string) $amount;
	}

	/**
	 * Tracker links still use the legacy BrowserGamesHub signing, guarded by
	 * its own constants (unset in production).
	 *
	 * @param $sTracker $sTracker ID of the tracker, for example: "registration"
	 * @return null|string
	 * @throws Exception
	 */
	public function getTrackerUrl ($sTracker)
	{
		if (!defined ('CREDITS_GAME_TOKEN') || !defined ('CREDITS_PRIVATE_KEY') || !class_exists ('BBGS_Credits')) {
			return null;
		}

		$legacy = new BBGS_Credits (CREDITS_GAME_TOKEN);
		$legacy->setPrivateKey (CREDITS_PRIVATE_KEY);

		if ($this->objUser->isEmailCertified ()) {
			$legacy->setEmail (trim (strtolower ($this->objUser->getEmail ())));
		}
		$legacy->setReferal ($this->objUser->getReferal ());
		foreach ($this->objUser->getOpenIDs () as $v) {
			$legacy->addOpenID ($v);
		}
		$legacy->setUserId ($this->objUser->getId ());

		if (!$legacy->isValidData ()) {
			return null;
		}

		return $legacy->getTrackerUrl ($sTracker);
	}

	/**
	 * @return string
	 */
	public function getError ()
	{
		return $this->error;
	}
}
