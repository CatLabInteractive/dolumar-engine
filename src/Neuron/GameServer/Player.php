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

if (!defined ('ALLOW_VACATION_MODE'))
{
    define ('ALLOW_VACATION_MODE', true);
}

class Neuron_GameServer_Player
    extends Neuron_Core_ModuleFactory
    implements Neuron_GameServer_Interfaces_Logable
{
    /*
        To enable the factory to do it's work,
        overload the loadModule function

        Return an object of the required module
        The factory will make sure that it is only loaded once
    */
    protected function loadModule ($sModule)
    {
        $classname = 'Neuron_GameServer_Player_'.ucfirst ($sModule);
        if (!class_exists ($classname))
        {
            throw new Exception ('Module '.$sModule.' ('.$classname.') does not exist.');
        }
        return new $classname ($this);
    }

    public function init ()
    {
        //
    }

    /*
        Required by Interfaces_Logable:
    */
    public static function getFromId ($id)
    {
        return Neuron_GameServer::getPlayer ($id);
    }

    public static function getFromOpenID ($openid)
    {
        return Neuron_GameServer_Mappers_PlayerMapper::getFromOpenID ($openid);
    }

    /*
        Check if a player name exists.
    */
    public static function playerNameExists ($nickname)
    {
        $player = Neuron_GameServer_Mappers_PlayerMapper::getFromNickname ($nickname);
        return isset ($player);
    }

    /*
        Return a player from nickname
    */
    public static function getFromName ($nickname)
    {
        return Neuron_GameServer_Mappers_PlayerMapper::getFromNickname ($nickname);
    }

    public static function getAdminModes ()
    {
        return array
        (
            9 => 'Developer',
            6 => 'Administrator',
            4 => 'Moderator',
            2 => 'Chatmod',
            0 => 'Player'
        );
    }

    private $id;
    private $data;
    private $gameData;

    private $isFound;
    private $isPlaying;

    private $gameTriggerObj = null;
    public $error = null;

    private $objCredits = null;

    public final function __construct ($playerId)
    {
        $this->id = $playerId;
    }

    public function setData ($data)
    {
        $this->data = $data;
        $this->isFound = true;
    }

    public function getId ()
    {
        return (int)$this->id;
    }

    public function reloadData ()
    {
        $this->data = null;
    }

    private function loadData ()
    {
        if (!isset ($this->data))
        {
            $data = Neuron_GameServer_Mappers_PlayerMapper::getDataFromId ($this->getId ());
            if (isset ($data))
            {
                $this->setData ($data);
            }

            else
            {
                $this->isFound = false;
            }
        }
    }

    /*
        Return all data (used by extended classes)
    */
    protected function getData ()
    {
        $this->loadData ();
        return $this->data;
    }

    public function isFound ()
    {
        $this->loadData ();
        return $this->isFound;
    }

    public function isPlaying ()
    {
        $this->loadData ();
        return
            (
                $this->isFound &&
                !empty ($this->data['nickname'])
                && ($this->data['isPlaying'] == 1)
            );
    }

    public function getPasswordHash ()
    {
        $this->loadData ();
        return isset ($this->data['password1']) ? $this->data['password1'] : null;
    }

    public function isNicknameSet ()
    {
        $this->loadData ();
        return !empty ($this->data['nickname']);
    }

    public function getNickname ()
    {
        $this->loadData ();
        return !empty ($this->data['nickname']) ? $this->data['nickname'] : 'Guest ' . $this->getId ();
    }

    public function getName ()
    {
        $this->loadData ();
        return $this->getNickname ();
    }

    /*
        Return the HTML version of the name.
    */
    public function getDisplayName ()
    {
        $flags = '';

        if ($this->isProperPremium ())
        {
            $flags .= 'premium ';
        }

        if ($this->isModerator ())
        {
            $flags .= 'moderator ';
        }

        $string = '<span class="nickname '.$flags.'">';
        $nickname = Neuron_Core_Tools::output_varchar ($this->getName ());
        $string .= Neuron_URLBuilder::getInstance ()->getOpenUrl ('player', $nickname, array ('plid' => $this->getId ()));
        $string .= '</span>';

        return $string;
    }

    public function getEmail ()
    {
        $this->loadData ();
        return $this->data['email'];
    }

    public function isEmailSet ()
    {
        $this->loadData ();
        return Neuron_Core_Tools::checkInput ($this->data['email'], 'email');
    }

    /**
     *	This method will check if an email address is set
     *	and verified OR if an OpenID account is set (in which case)
     *	there is no email required.
     */
    public function isEmailVerified ()
    {
        $openid = isset ($_SESSION['neuron_openid_identity']) ?
            md5 ($_SESSION['neuron_openid_identity']) : false;

        return $this->isFound () && ($this->isEmailCertified () || $openid);
    }

    public function isEmailCertified ()
    {
        $this->loadData ();
        return $this->data['email_cert'] == '1';
    }

    public function sendCertificationMail ()
    {
        if ($this->isEmailSet ())
        {
            // Yeah yeah, mailing stuff.
            $text = Neuron_Core_Text::__getInstance ();
            customMail
            (
                $this->getEmail (),
                $text->get ('mail_subject', 'choosemail', 'account'),
                $text->getTemplate ('email_cert', array
                (
                    Neuron_Core_Tools::output_varchar ($this->getNickname ()),
                    API_FULL_URL.'emailcert?id='.$this->getId ().'&certkey='.$this->data['email_cert_key']
                ))
            );
        }
    }

    public function certifyEmail ($key)
    {
        $this->loadData ();

        Neuron_GameServer_Mappers_PlayerMapper::certifyEmail ($this, $key);

        $okay = $this->data['email_cert'] == 0;
        $this->data['email_cert'] = 1;

        // Count the credits to register email with master server
        $this->getCredits ();

        // Give the refering user a bonus!
        $referer = intval ($this->data['p_referer']);
        $referer = $referer > 0 ? Neuron_GameServer::getPlayer ($this->data['p_referer']) : false;

        if ($referer && $okay)
        {
            $referer->giveReferralBonus ($this);
        }
    }

    public function doesEmailExist ($email)
    {
        $acc = Neuron_GameServer_Mappers_PlayerMapper::getFromEmail ($email);
        return isset ($acc);
    }

    public function setEmail ($email)
    {
        if (Neuron_Core_Tools::checkInput ($email, 'email'))
        {
            if (!$this->doesEmailExist ($email))
            {
                $key = md5 (time () + rand (0, 99999));

                Neuron_GameServer_Mappers_PlayerMapper::setEmail ($this, $email, $key);

                $this->data['email'] = $email;
                $this->data['email_cert'] = 0;
                $this->data['email_cert_key'] = $key;

                $this->sendCertificationMail ();

                return true;
            }
            else
            {
                $this->error = 'email_exists';
                return false;
            }
        }
        else
        {
            $this->error = 'invalid_syntax';
            return false;
        }
    }

    /*
        Only for admin panel
    */
    public function getAdminStatus ()
    {
        $this->loadData ();
        return $this->data['p_admin'];
    }

    public function setAdminStatus ($status)
    {
        $status = intval ($status);

        Neuron_GameServer_Mappers_PlayerMapper::setAdminStatus ($this, $status);

        $this->data['p_admin'] = $status;
    }

    public function getAdminStatusString ()
    {
        $states = $this->getAdminModes ();
        $k = $this->getAdminStatus ();
        return isset ($states[$k]) ? $states[$k] : null;
    }

    public function isDeveloper ()
    {
        $this->loadData ();
        return $this->data['p_admin'] >= 9;
    }

    public function isAdmin ()
    {
        $this->loadData ();
        return $this->data['p_admin'] >= 6;
    }

    public function isModerator ()
    {
        $this->loadData ();
        return $this->data['p_admin'] >= 4;
    }

    public function isChatModerator ()
    {
        $this->loadData ();
        return $this->data['p_admin'] >= 2;
    }

    public function isPremium ()
    {
        if ($this->isProperPremium ())
        {
            return true;
        }

        if (defined ('FREE_PREMIUM') && FREE_PREMIUM)
        {
            return true;
        }

        // Let's move to the "other servers".
        $this->loadData ();

        return $this->getPremiumEndDate () > time ();
    }

    public function isProperPremium ()
    {
        if ($this->isModerator ())
        {
            return true;
        }

        $this->loadData ();
        return strtotime ($this->data['premiumEndDate']) > time ();
    }

    public function getPremiumEndDate ()
    {
        $this->loadData ();
        return max
        (
            strtotime ($this->data['sponsorEndDate']),
            strtotime ($this->data['premiumEndDate']),
            (strtotime ($this->data['creationDate']) + 24 * 60 * 60 * 7)
        );
    }

    public function showAdvertisement ()
    {
        $this->loadData ();
        if ($this->data['showAdvertisement'] == 1)
        {
            return true;
        }
        else
        {
            return !$this->isPremium ();
        }
    }

    public function extendPremiumAccount ($duration = 86400)
    {
        $this->loadData ();

        if (strtotime ($this->data['premiumEndDate']) > time ())
        {
            $start = strtotime ($this->data['premiumEndDate']);
        }
        else
        {
            $start = time ();
        }

        Neuron_GameServer_Mappers_PlayerMapper::extendPremiumAccount ($this, $start + $duration);

        $this->data['premiumEndDate'] = Neuron_Core_Tools::timeStampToMysqlDatetime ($start + $duration);
    }

    /*
        Set this users language
    */
    public function setLanguage ($sLang)
    {
        if (strlen ($sLang) > 5)
        {
            return false;
        }

        Neuron_GameServer_Mappers_PlayerMapper::setLanguage ($this, $sLang);

        $this->reloadData ();
    }

    public function getRightLanguage ()
    {
        $this->loadData ();

        $lang = isset ($this->data['p_lang']) ? $this->data['p_lang'] : false;
        if ($lang)
        {
            return new Neuron_Core_Text ($lang);
        }
        else
        {
            return Neuron_Core_Text::__getInstance ();
        }
    }

    public function getLoginURL ()
    {
        return ABSOLUTE_URL;
    }

    public function isValidUnsubsribeKey ($key)
    {
        return $this->getUnsubscribeKey () === $key;
    }

    public function getUnsubscribeKey ()
    {
        // Not very secure, but it doesn't have to be very secure.
        return substr (md5 ($this->getNickname () . ':' . $this->getId () . ':' . $this->getEmail () . ':' . 'dolumar rules'), 0, 6);
    }

    public function getUnsubscribeURL ()
    {
        $key = $this->getUnsubscribeKey ();
        return API_FULL_URL.'unsubscribe?id='.$this->getId ().'&key=' . $key;
    }

    public function invitePeople
    (
        $txtMsgKeySender, $txtMsgSectionSender,
        $txtMsgKeyReceiver, $txtMsgSectionReceiver,
        $maxPerInterval = 1, $maxReceiverPerInterval = 0, $interval = 604800,
        $inputData = array ()
    )
    {
        // First: load this users OpenID notification urls
        $openid_rows = Neuron_GameServer_Mappers_PlayerMapper::getOpenIDs ($this);

        $text = $this->getRightLanguage ();

        if (count ($openid_rows) > 0)
        {

            $server = Neuron_GameServer::getInstance ()->getServer ();
            $servername = new Neuron_GameServer_Logable_String ($server->getServerName ());

            $inputData = array_merge (array ('sender' => $this, 'server' => $servername), $inputData);

            $keyvalues = array ();
            foreach ($inputData as $k => $v)
            {
                $keyvalues[$k] = $v->getName ();
            }

            $senderMessage = Neuron_Core_Tools::putIntoText ($text->get ($txtMsgKeySender, $txtMsgSectionSender, 'notifications'), $keyvalues);
            $receiverMessage = Neuron_Core_Tools::putIntoText ($text->get ($txtMsgKeyReceiver, $txtMsgSectionReceiver, 'notifications'), $keyvalues);

            // Load OpenID accounts and send Browser Games Hub notifications
            $objNot = new BrowserGamesHub_Invitation ($senderMessage, $receiverMessage, $maxPerInterval, $maxReceiverPerInterval, $interval, $text->getCurrentLanguage ());

            $objNot->setIcon (STATIC_URL . 'icon.png');
            $objNot->setId ($txtMsgKeySender, $txtMsgSectionSender);
            $objNot->setSenderData ($this->getBrowserBasedGamesData ());

            // Keep in mind that the notification does not like actual names,
            // so we will replace all key names with their numeric value.
            $keys = array_keys ($inputData);
            $replace_keys = array ();
            foreach ($keys as $k => $v)
            {
                if ($v != 'actor')
                {
                    $replace_keys[$v] = '{'.$k.'}';
                }
                else
                {
                    $replace_keys[$v] = '{actor}';
                }
            }

            $objNot->setSkeletonSender
            (
                Neuron_Core_Tools::putIntoText
                (
                    $text->get ($txtMsgKeySender, $txtMsgSectionSender, 'notifications'),
                    $replace_keys
                )
            );

            $objNot->setSkeletonReceiver
            (
                Neuron_Core_Tools::putIntoText
                (
                    $text->get ($txtMsgKeyReceiver, $txtMsgSectionReceiver, 'notifications'),
                    $replace_keys
                )
            );

            $callback = API_FULL_URL.'invitation/?id='.$this->getId();
            $objNot->setCallback ($callback);

            // Take all the value strings and put them in there aswell
            foreach ($inputData as $v)
            {
                if ($v instanceof Dolumar_Players_Player)
                {
                    $objNot->addArgument ($v->getName (), 'user', $v->getBrowserBasedGamesData ());
                }

                elseif ($v instanceof Neuron_GameServer_Interfaces_Logable)
                {
                    $objNot->addArgument ($v->getName (), 'text');
                }
                else
                {
                    $objNot->addArgument ($v, 'text');
                }
            }

            // Send the notification
            foreach ($openid_rows as $v)
            {
                return $objNot->send ($v['notify_url']);
            }
        }

        return array ('success' => false, 'error' => 'No OpenID providers set.');
    }

    /*
        Various notifications are called
    */
    public function sendNotification ($txtMsgKey, $txtMsgSection, $inputData = array (), $objSender = null, $isPublic = false)
    {
        $text = $this->getRightLanguage ();

        // Add "actor" to inputdata
        $inputData['actor'] = $this;

        $newArray = array ();
        $plainArray = array ();
        foreach ($inputData as $k => $v)
        {
            if ($v instanceof Neuron_GameServer_Interfaces_Logable)
            {
                $newArray[$k] = Neuron_Core_Tools::output_varchar ($v->getName ());
                $plainArray[$k] = $v->getName ();
            }
            else
            {
                $newArray[$k] = Neuron_Core_Tools::output_varchar ($v);
                $plainArray[$k] = $v;
            }
        }

        $msg = Neuron_Core_Tools::putIntoText
        (
            $text->get ($txtMsgKey, $txtMsgSection, 'notifications'),
            $newArray
        );

        $msg_plaintext = Neuron_Core_Tools::putIntoText
        (
            $text->get ($txtMsgKey, $txtMsgSection, 'notifications'),
            $plainArray
        );

        // Notify player gametrigger
        //$this->callGameTrigger ('sendNotification', array ($msg, $txtMsgSection, $txtMsgKey, $inputData));

        try {
            $this->sendOpenIDNotifications($msg_plaintext, $txtMsgKey, $txtMsgSection, $inputData, $objSender, $isPublic);
        } catch (Exception $e) {
            // Something went wrong, but we can't care about it too much.
            Neuron_ErrorHandler_Handler::getInstance()->notify($e);
        }

        // Also send email
        try {
            $this->sendNotificationEmail ($msg_plaintext, $txtMsgKey, $txtMsgSection, $inputData, $objSender, $isPublic);
        } catch (Exception $e) {
            Neuron_ErrorHandler_Handler::getInstance()->notify($e);
        }
    }

    private function sendNotificationEmail ($msg_plaintext, $txtMsgKey, $txtMsgSection, $inputData, $objSender, $isPublic)
    {
        $text = $this->getRightLanguage ();

        // If online? Don't do anything
        if ($this->isOnline ())
        {
            return;
        }

        // Also send email
        if ($this->isEmailSet () && $this->getPreference ('emailNotifs', true))
        {
            if (!$isPublic)
            {
                customMail
                (
                    $this->getEmail (),
                    $msg_plaintext,
                    $text->getTemplate ('email_notification', array
                    (
                        'nickname' => Neuron_Core_Tools::output_varchar ($this->getNickname ()),
                        'message' => $msg_plaintext,
                        'loginurl' => $this->getLoginURL (),
                        'unsubsribe' => $this->getUnsubscribeURL ()
                    ))
                );
            }
        }
    }

    protected function onSendNotifications (BrowserGamesHub_Notification $notification)
    {
        // Do nothing.
    }

    private function sendOpenIDNotifications ($msg, $txtMsgKey, $txtMsgSection, $inputData, $objSender, $isPublic)
    {
        // First: load this users OpenID notification urls
        $db = Neuron_DB_Database::__getInstance ();

        // Notifications are signed with the credits key: skip when unconfigured.
        if (!defined ('CREDITS_PRIVATE_KEY')) {
            return;
        }

        $openid_rows = Neuron_GameServer_Mappers_PlayerMapper::getOpenIDs ($this);

        if (count ($openid_rows) > 0)
        {
            $text = $this->getRightLanguage ();

            // Load OpenID accounts and send Browser Games Hub notifications
            $objNot = new BrowserGamesHub_Notification ($msg, time (), $text->getCurrentLanguage ());

            $objNot->setIcon (STATIC_URL . 'icon.png');

            $objNot->setId ($txtMsgSection, $txtMsgKey);

            $objNot->setTargetData ($this->getBrowserBasedGamesData ());

            if ($objSender instanceof Dolumar_Players_Player)
            {
                $objNot->setSenderData ($objSender->getBrowserBasedGamesData ());
            }

            // Keep in mind that the notification does not like actual names,
            // so we will replace all key names with their numeric value.
            $keys = array_keys ($inputData);
            $replace_keys = array ();
            foreach ($keys as $k => $v)
            {
                if ($v != 'actor')
                {
                    $replace_keys[$v] = '{'.$k.'}';
                }
                else
                {
                    $replace_keys[$v] = '{target}';
                }
            }

            $objNot->setSkeleton
            (
                Neuron_Core_Tools::putIntoText
                (
                    $text->get ($txtMsgKey, $txtMsgSection, 'notifications'),
                    $replace_keys
                )
            );

            $desc = $text->get ($txtMsgKey.'_long', $txtMsgSection, 'notifications', '');
            if (!empty ($desc))
            {
                $objNot->setDescription
                (
                    Neuron_Core_Tools::putIntoText
                    (
                        $desc,
                        $replace_keys
                    )
                );
            }

            // Take all the value strings and put them in there aswell
            foreach ($inputData as $v)
            {
                if ($v instanceof Dolumar_Players_Player)
                {
                    $objNot->addArgument ($v->getName (), 'user', $v->getBrowserBasedGamesData ());
                }

                elseif ($v instanceof Neuron_GameServer_Interfaces_Logable)
                {
                    $objNot->addArgument ($v->getName (), 'text');
                }
                else
                {
                    $objNot->addArgument ($v, 'text');
                }
            }

            // Visibliity
            $objNot->setVisibility ($isPublic ? 'public' : 'private');

            $this->onSendNotifications ($objNot);

            // Send the notification
            foreach ($openid_rows as $v)
            {
                $objNot->send ($v['notify_url']);
            }
        }
    }

    /*
        Updates this players profile box (if necesarry)
    */
    public function updateProfilebox ()
    {

    }

    /*
        Send all user data (nickname, score, etc) to the OpenID provider.
    */
    private function sendUserData ()
    {

        // First: load this users OpenID notification urls
        $db = Neuron_DB_Database::__getInstance ();

        // Statistics are signed with the credits key: skip when unconfigured.
        if (!defined ('CREDITS_PRIVATE_KEY')) {
            return;
        }

        $openid_rows = Neuron_GameServer_Mappers_PlayerMapper::getOpenIDs ($this);

        if (count ($openid_rows) > 0)
        {
            $information = $this->getBrowserBasedGamesData ();
            $statistics = $this->getStatistics ();

            // Send the notification
            foreach ($openid_rows as $v)
            {
                $stat = new BrowserGamesHub_Statistics ($statistics, $information);
                $stat->send ($v['notify_url']);
            }
        }
    }

    public function getStatistics ()
    {
        return array
        (
            'score' => $this->getScore ()
        );
    }

    /*
        This function is called whenever the score gets updated.
    */
    public function updateScore ()
    {
        $this->sendUserData ();
    }

    /*
        Game triggers can be used to notify players on different websites.
        A player can have a trigger class attached to him. In that case
        all triggers of this class are used.
    */
    public function callGameTrigger ($function, $arguments = array ())
    {

    }

    public function setNickname ($nickname)
    {
        if (true)
        {
            $this->loadData ();

            if (!$this->isNicknameSet ())
            {
                if (Neuron_Core_Tools::checkInput ($nickname, 'username'))
                {
                    $data = Neuron_GameServer_Mappers_PlayerMapper::getFromNickname ($nickname);

                    if (!isset ($data))
                    {

                        // Everything seems to be okay. Let's go.
                        Neuron_GameServer_Mappers_PlayerMapper::setNickname ($this, $nickname);

                        $this->data['nickname'] = $nickname;
                        return true;
                    }
                    else
                    {
                        $this->error = 'user_found';
                        return false;
                    }
                }
                else
                {
                    $this->error = 'error_username';
                    return false;
                }
            }
            else
            {
                $this->error = 'nickname_already_set';
                return false;
            }
        }
        else
        {
            $this->error = 'game_not_open';
            return false;
        }
    }

    public function changeNickname ($nickname)
    {
        $db = Neuron_Core_Database::__getInstance ();

        $this->loadData ();
        if (!empty ($this->data['nickname']))
        {
            if (Neuron_Core_Tools::checkInput ($nickname, 'username'))
            {
                $data = Neuron_GameServer_Mappers_PlayerMapper::getFromNickname ($nickname);

                if (!isset ($data))
                {

                    $chk = Neuron_GameServer_Mappers_PlayerMapper::setNickname ($this, $nickname);

                    if ($chk)
                    {
                        $this->data['nickname'] = $nickname;
                        return true;
                    }
                    else
                    {
                        $this->error = 'update_failed';
                        return false;
                    }
                }
                else
                {
                    $this->error = 'user_found';
                    return false;
                }
            }
            else
            {
                $this->error = 'error_username';
                return false;
            }
        }
        else
        {
            $this->error = 'nickname_not_set';
        }
    }

    public function getError ()
    {
        return $this->error;
    }

    /*
        This function starts the RESET ACCOUNT procedure.

        This function sends a mail to the player and allows
        the user to reset his account using a link provided
        in the mail.
    */
    public function startResetAccount ()
    {
        if ($this->isFound ())
        {
            $db = Neuron_Core_Database::__getInstance ();

            $key = md5 (mt_rand (0, 1000000));

            Neuron_GameServer_Mappers_PlayerMapper::setTemporaryKey ($this, $key, time () + 60*60*24);

            // Send the mail
            $text = Neuron_Core_Text::__getInstance ();
            customMail
            (
                $this->getEmail (),
                $text->get ('msubject', 'resetaccount', 'account'),
                $text->getTemplate
                (
                    'email_reset',
                    array
                    (
                        Neuron_Core_Tools::output_varchar ($this->getNickname ()),
                        API_FULL_URL.'reset?id='.$this->getId ().'&certkey='.$key
                    )
                )
            );
        }
    }

    /*
        This function resets the player acocunt.

        This includes:
        - Disable all villages this player owns
    */
    public function resetAccount ($key)
    {
        $this->loadData ();

        // Check key
        if (strtotime ($this->data['tmp_key_end']) > time () && $this->data['tmp_key'] == $key)
        {
            return $this->doResetAccount ();
        }

        return false;
    }

    /*
        This function executes and acount removal.
    */
    public function execResetAccount ()
    {
        return $this->doResetAccount ();
    }

    /*
        This function is called when a valid reset call is triggered.
    */
    public function doResetAccount ()
    {
        $this->doEndVacationMode ();

        Neuron_GameServer_Mappers_PlayerMapper::resetAccount ($this);

        return true;
    }

    public function isOnline ()
    {
        $this->loadData ();

        $db = Neuron_DB_Database::getInstance ();

        return $db->toUnixtime ($this->data['lastRefresh']) > (time () - ONLINE_TIMEOUT);
    }

    public function getLogArray ()
    {
        return $this->getAPIData (true);
    }

    public function getAPIData ($showExtendedInfo = true)
    {
        $this->loadData ();

        $data = array
        (
            'id' =>		$this->data['plid'],
            'name' => 	$this->getNickname (),
            'refresh' =>	$this->data['lastRefresh']
        );

        if ($showExtendedInfo)
        {
            $villages = $this->getVillages ();

            $vils = array ();
            foreach ($villages as $v)
            {
                $vils[] = array
                (
                    'name' => 	$v->getName (),
                    'id' => 	$v->getId ()
                );
            }

            $data['villages'] = $vils;
        }

        return $data;
    }

    public function getCreationDate ()
    {
        $this->loadData ();
        return Neuron_Core_Tools::datetimeToTimestamp ($this->data['creationDate']);
    }

    public function getLastRefresh ()
    {
        $this->loadData ();
        return Neuron_Core_Tools::datetimeToTimestamp ($this->data['lastRefresh']);
    }

    public function getRemovalDate ()
    {
        $this->loadData ();
        return Neuron_Core_Tools::datetimeToTimestamp ($this->data['removalDate']);
    }

    /*
        Return the referee count
    */
    public function getReferal ()
    {
        $this->loadData ();
        return isset ($this->data['referee']) ? $this->data['referee'] : null;
    }

    /*
        Credits
    */
    private function loadCredits ()
    {
        if (!isset ($this->objCredits))
        {
            $this->objCredits = new Neuron_GameServer_Credits ($this);
        }
    }

    public function isValidData ()
    {
        if ($this->objCredits->isValidData ())
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    public function getCredits ()
    {
        $this->loadCredits ();

        $credits = $this->objCredits->getCredits ();

        if ($credits === false)
        {
            $this->error = $this->objCredits->getError ();
            return false;
        }
        return $credits;
    }

    public function refundCredits ($amount, $description, $action = null)
    {
        $this->loadCredits ();
        return $this->objCredits->refundCredits ($amount, $description, $action);
    }

    public function useCredit ($amount, $data)
    {
        $this->extendPremiumAccount (60*60*24*15);
    }

    public function getCreditUseUrl ($amount = 100, $data = array (), $description = 'Premium membership')
    {
        $this->loadCredits ();
        return $this->objCredits->getUseUrl ($amount, $data, $description);
    }

    public function getCreditBuyUrl ()
    {
        $this->loadCredits ();
        return $this->objCredits->getBuyUrl ();
    }

    /*
        Return the amount of (foreign) credits
        this user will have to pay.
    */
    public function convertCredits ($amount = 100)
    {
        $this->loadCredits ();
        return $this->objCredits->convertCredits ($amount);
    }

    public function getCreditDisplay ($amount = 100)
    {
        $this->loadCredits ();
        return $this->objCredits->getCreditDisplay ($amount);
    }

    public function getTrackerUrl ($tracker)
    {
        $this->loadCredits ();
        return $this->objCredits->getTrackerUrl ($tracker);
    }

    public function handleUseRequest ($data, $id, $key)
    {
        $this->loadCredits ();
        $result = $this->objCredits->handleUseRequest ($data, $id, $key);
        $this->error = $this->objCredits->getError ();

        return $result;
    }

    /*
        Vacation mode
    */
    public function startVacationMode ()
    {
        if (!ALLOW_VACATION_MODE)
        {
            $this->error = 'vacation_disabled';
            return false;
        }

        Neuron_GameServer_Mappers_PlayerMapper::startVacationMode ($this);

        return true;
    }

    /*
        And vacation mode, if possible...
    */
    public function endVacationMode ()
    {
        $db = Neuron_DB_Database::getInstance ();

        $this->loadData ();

        $unixtime = $db->toUnixtime ($this->data['startVacation']);

        if ($unixtime > 0 && $unixtime + 60*60*24*7 > time ())
        {
            $this->error = 'too_early';
            return false;
        }

        $this->doEndVacationMode ();

        return true;
    }

    protected function doEndVacationMode ()
    {
        Neuron_GameServer_Mappers_PlayerMapper::endVacationMode ($this);
    }

    /*
        Check if this player is a vacation mode
    */
    public function inVacationMode ()
    {
        return ALLOW_VACATION_MODE && $this->getVacationStart () != null;
    }

    public function getVacationStart ()
    {
        $this->loadData ();
        if ($this->data['startVacation'] == null)
        {
            return null;
        }
        else
        {
            $db = Neuron_DB_Database::__getInstance ();
            return $db->toUnixtime ($this->data['startVacation']);
        }
    }

    public function getRank ()
    {

        $rank = Neuron_GameServer_Mappers_PlayerMapper::getRank ($this);
        $total = Neuron_GameServer_Mappers_PlayerMapper::countAll ();

        //echo $total;

        return array ($rank, $total);
    }

    /*
        Calculate the score for this player
    */
    public function getScore ()
    {
        $this->loadData ();
        $score = $this->data['p_score'];

        if ($score == 0 && $this->isPlaying ())
        {
            $this->updateScore ();
        }

        return $score;
    }

    /*
        Set the score
    */
    public function setScore ($score)
    {
        Neuron_GameServer_Mappers_PlayerMapper::setScore ($this, $score);

        $this->loadData ();
        $this->data['p_score'] = $score;
    }

    public function getBrowserBasedGamesData ($data = null)
    {
        $openids = $this->getOpenIDs ();

        $openids_out = array ();
        foreach ($openids as $v)
        {
            $openids_out[] = array
            (
                'attributes' => array ('hash' => 'md5'),
                'element-content' => md5 ($v)
            );
        }

        $lkey = "";

        $this->loadData ();

        $isFound = $this->isFound;

        return array
        (
            'member_id' => $this->getId (),
            'member_url' => ABSOLUTE_URL.$lkey,
            'name' => $this->getName (),
            'score' => $this->getScore (),
            'openids' => $openids_out,
            'join_date' => ($isFound ? date (API_DATE_FORMAT, strtotime ($this->data['creationDate'])) : null)
        );
    }

    public function getOpenIDs ()
    {
        $db = Neuron_DB_Database::__getInstance ();

        $openids = Neuron_GameServer_Mappers_PlayerMapper::getOpenIDs ($this, false);

        $out = array ();

        foreach ($openids as $v)
        {
            $out[] = $v['openid_url'];
        }

        return $out;
    }

    public function setPreference ($sKey, $sValue)
    {
        $this->preferences->setPreference ($sKey, $sValue);
    }

    public function getPreference ($sKey, $default = false)
    {
        return $this->preferences->getPreference ($sKey, $default);
    }

    /*
        Return the game logs for this player...
        to be overloaded.
    */
    public function getLogs ($iStart, $iEnd)
    {
        return array ();
    }

    /*
        Give a bonus for refering a friend.
    */
    public function giveReferralBonus ($objUser) {}

    /*
        Social status changer.
        This function enables you to set a special status
        for a player. This special status must be INT.

        Reserved int's are: 1 (isFriend), -1 (ignoring), 0 (neutral)
    */
    protected function setSocialStatus ($objUser, $status)
    {
        if (!$objUser instanceof Neuron_GameServer_Player)
        {
            $objUser = Neuron_GameServer_Mappers_PlayerMapper::getFromId ($objUser);
        }

        $this->social->setSocialStatus ($objUser, $status);
    }

    protected function getSocialStatus ($objUser)
    {
        if (!$objUser instanceof Neuron_GameServer_Player)
        {
            $objUser = Neuron_GameServer_Mappers_PlayerMapper::getFromId ($objUser);
        }

        return $this->social->getSocialStatus ($objUser);
    }

    public function isIgnoring ($objUser)
    {
        return $this->getSocialStatus ($objUser) == -1;
    }

    public function setIgnoring ($objUser, $ignore = true)
    {
        $this->setSocialStatus ($objUser, $ignore == true ? -1 : 0);
    }

    public function getIgnoredPlayers ()
    {
        return $this->social->getSocialStatuses (-1);
    }

    public function isBanned ($sChannel = 'chat')
    {
        return $this->bans->isBanned ($sChannel);
    }

    public function getBanDuration ($sChannel)
    {
        return $this->bans->getBanDuration ($sChannel);
    }

    public function ban ($sChannel = 'chat', $duration = 3600, $ban = true)
    {
        $this->bans->ban ($sChannel, $duration, $ban);
    }

    public function unban ($sChannel = 'chat')
    {
        $this->bans->ban ($sChannel, null, false);
    }

    public function equals ($objPlayer)
    {
        return $objPlayer->getId () == $this->getId ();
    }

    /*
    * Called when someone sent you a gift.
    */
    public function invitationGiftReceiver ($data, Neuron_GameServer_Player $from)
    {

    }

    /*
    * Called when someone accepts your gift.
    */
    public function invitationGiftSender ($data, Neuron_GameServer_Player $to)
    {

    }

    public function countLogins ()
    {
        return Neuron_GameServer_Mappers_PlayerMapper::countLogins ($this);
    }

    public function __toString ()
    {
        return $this->getDisplayName ();
    }

    /*
        Destruct this object and all villages within it.
        This is a fairly dangerous function, it is possible
        that, using this method, villages will be destroyed
        that still have references.
    */
    public function __destruct ()
    {
        //echo 'player destructed.' . "\n";

        //unset ( $this->data );
        unset ( $this->gameTriggerObj );
        unset ( $this->error );
        unset ( $this->id );
        unset ( $this->gameData );
        unset ( $this->isFound );
        unset ( $this->isPlaying );
        unset ( $this->objCredits );
        unset ( $this->sPreferences );
        unset ( $this->iSocialStatuses );
        unset ( $this->bans );
    }
}