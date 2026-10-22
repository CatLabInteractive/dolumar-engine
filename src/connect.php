<?php
/*
	CONNECT.PHP
	This file is loaded on every request.
*/

// Report all PHP errors
//error_reporting(E_ALL);
ignore_user_abort (true);
error_reporting(E_ERROR);

session_name ('dolumar_session');

define ('CATLAB_BASEPATH', dirname (dirname (__FILE__)) . '/');
define ('CATLAB_LANGUAGE_PATH', CATLAB_BASEPATH . 'languages/');
define ('PEAR_BASEPATH', (dirname (dirname (__FILE__))) . '/pear/');

define ('GAMESERVER_ASSET_URL', BASE_URL . '/gameserver/assets/');

require_once 'config.php';

if (!defined ('MAX_MEMORY_USAGE')) {
	define ('MAX_MEMORY_USAGE', 25000000);
}

if (!defined ('IS_TESTSERVER'))
	define ('IS_TESTSERVER', false);

// Setting basic constants.
if (!defined ('BASE_PATH'))
	define ('BASE_PATH', dirname(dirname(__FILE__)).'/');

if (!defined ('ABSOLUTE_URL'))
	define ('ABSOLUTE_URL', 'http://'.$_SERVER['SERVER_NAME'] . '/');

if (!defined ('SPEED_FACTOR'))
	define ('SPEED_FACTOR', 1);

if (!defined ('STATIC_ABSOLUTE_URL'))
	define ('STATIC_ABSOLUTE_URL', ABSOLUTE_URL);

if (!defined ('GAME_NAME'))
	define ('GAME_NAME', 'Dolumar');

// API
if (!defined ('API_FULL_URL'))
	define ('API_FULL_URL', ABSOLUTE_URL.'api/');

if (!defined ('API_OPENID_URL'))
	define ('API_OPENID_URL', ABSOLUTE_URL.'openid/');

if (!defined ('DEBUG_LOGS'))
	define ('DEBUG_LOGS', false);

if (!defined ('BASE_URL'))
	define ('BASE_URL', ABSOLUTE_URL);

if (!defined ('ONLINE_TIMEOUT'))
	define ('ONLINE_TIMEOUT', 90);

if (!defined ('DATE'))
	define ('DATE', 'd.m.Y');

if (!defined ('DATETIME'))
	define ('DATETIME', 'd.m.Y H:i');

// URLS
if (!defined ('STATIC_URL'))
	define ('STATIC_URL', BASE_URL . 'static/');

if (!defined ('STATIC_PATH'))
	define ('STATIC_PATH', BASE_PATH.'static/');

if (!defined ('IMAGE_URL'))
	define ('IMAGE_URL', STATIC_URL.'images/');

if (!defined ('IMAGE_PATH'))
	define ('IMAGE_PATH', BASE_PATH.'public/static/images/');

if (!defined ('SMILEY_DIR'))
	define ('SMILEY_DIR', IMAGE_URL.'smileys/blue/');

if (!defined ('SMILEY_PATH'))
	define ('SMILEY_PATH', IMAGE_PATH.'smileys/blue/');

if (!defined ('PUBLIC_PATH'))
	define ('PUBLIC_PATH', BASE_PATH.'uploads/');

if (!defined ('PUBLIC_URL'))
	define ('PUBLIC_URL', BASE_URL);

// Paths
if (!defined ('TEMPLATE_DIR'))
	define ('TEMPLATE_DIR', BASE_PATH.'dolumar/templates/default/');

if (!defined ('LANGUAGE_DIR'))
	define ('LANGUAGE_DIR', BASE_PATH.'static/language/');

if (!defined ('STATS_DIR'))
	define ('STATS_DIR', BASE_PATH.'static/stats/');

if (!defined ('RANDMAPFACTOR'))
	define ('RANDMAPFACTOR', 'Funky Dolumar Game 2008');

if (!defined ('COOKIE_PREFIX'))
	define ('COOKIE_PREFIX', 'PJX');

if (!defined ('COOKIE_LIFE'))
	define ('COOKIE_LIFE', 60 * 60 * 24 * 31 * 12);

// Outside  URLS
if (!defined ('WIKI_GUIDE_URL'))
	define ('WIKI_GUIDE_URL', false);

if (!defined ('WIKI_EDIT_URL'))
	define ('WIKI_EDIT_URL', false);

if (!defined ('WIKI_PREFIX'))
	define ('WIKI_PREFIX', false);

if (!defined ('FORUM_URL'))
	define ('FORUM_URL', false);

if (!defined ('CONTACT_URL'))
	define ('CONTACT_URL', false);

if (!defined ('PREMIUM_URL'))
	define ('PREMIUM_URL', false);

if (!defined ('TRACKER_URL'))
	define ('TRACKER_URL', false);

if (!defined ('PREMIUM_API_KEY'))
	define ('PREMIUM_API_KEY', false);

if (!defined ('SERVERLIST_URL'))
	define ('SERVERLIST_URL', false);

if (!defined ('OPENID_SKIP_LOGIN'))
	define ('OPENID_SKIP_LOGIN', true);

if (!defined ('OPENID_SKIP_NICKNAME'))
	define ('OPENID_SKIP_NICKNAME', true);

if (!defined ('EMAIL_DEBUG_LEVEL'))
	define ('EMAIL_DEBUG_LEVEL', 0);

// Define cookie path
$base_path = ABSOLUTE_URL;
$base_path = explode ('/', $base_path);

if (isset ($_SERVER['SERVER_NAME']))
{
	while (array_shift ($base_path) != $_SERVER['SERVER_NAME'] && count ($base_path) > 0) {}
}
$base_path = '/'.implode ('/', $base_path);
define ('COOKIE_BASE_PATH', $base_path);

define ('API_DATE_FORMAT', 'Y-m-d\TH:i:s');

// Set session ID if provided
if (isset($_GET['sessionId']) && !empty($_GET['sessionId'])) {
    session_id ($_GET['sessionId']);
} elseif (isset ($_GET['phpSessionId']) && !empty ($_GET['phpSessionId'])) {
	session_id ($_GET['phpSessionId']);
} elseif (isset ($_GET['session_id']) && !empty ($_GET['session_id'])) {
	session_id ($_GET['session_id']);
} elseif (isset ($_COOKIE['session_id']) && !empty ($_COOKIE['session_id'])) {
	session_id ($_COOKIE['session_id']);
}

session_start([
    'use_cookies' => 0
]);

// Get right language tag
if (isset ($_GET['language']))
{
	define ('LANGUAGE_TAG', $_GET['language']);
	
	$_SESSION['language'] = LANGUAGE_TAG;
	setcookie ('user_language', LANGUAGE_TAG, time () + COOKIE_LIFE, '/');
}

elseif (isset ($_COOKIE['user_language']))
{
	define ('LANGUAGE_TAG', $_COOKIE['user_language']);
	$_SESSION['language'] = LANGUAGE_TAG;
}

elseif (isset ($_SESSION['language']))
{
	define ('LANGUAGE_TAG', $_SESSION['language']);
}

else
{
	if (!empty ($_SESSION['loginAuthLanguage']) && strlen ($_SESSION['loginAuthLanguage']) == 2)
	{
		define ('LANGUAGE_TAG', $_SESSION['loginAuthLanguage']);
		$_SESSION['language'] = LANGUAGE_TAG;
	}
	else
	{
		define ('LANGUAGE_TAG', 'en');
	}
}

// Check for client time zone
if (isset ($_COOKIE['time_zone_offset']) && isset ($_COOKIE['time_zone_dst']))
{
	$tz = timezone_name_from_abbr('', -$_COOKIE['time_zone_offset']*60, $_COOKIE['time_zone_dst']);
	if ($tz)
	{
		define ('TIME_ZONE', $tz);
	}
	else
	{
		define ('TIME_ZONE', 'Europe/Brussels');
	}
}
else
{
	define ('TIME_ZONE', 'Europe/Brussels');
}

// Game version
if (!defined('APP_VERSION')) 
{
	if (file_exists (BASE_PATH.'.svn/entries')
		&& is_writable (BASE_PATH.'version.txt'))
	{
		$svn = file(BASE_PATH.'.svn/entries');
		if (is_numeric(trim($svn[10]))) 
		{
			$version = $svn[10];
		} 
		else 
		{ // pre 1.4 svn used xml for this file
			$version = explode('"', $svn[4]);
			$version = $version[1];    
		}
		
		$version = trim ($version);
	
		define ('APP_VERSION', $version);
		
		// Check if the version file is still up to date
		$fversion = file_get_contents (BASE_PATH.'version.txt');
		if ($fversion != $version)
		{
			file_put_contents (BASE_PATH.'version.txt', $version);
		}
	}
	else
	{
		// get the version from the "version file"
		define ('APP_VERSION', trim (@file_get_contents (BASE_PATH.'version.txt')));
	}
}

ini_set ('max_execution_time', 90);

// Define "now"
define ('NOW', time ());

// Config file has already defined TIME_ZONE using above function (or config.)
date_default_timezone_set (TIME_ZONE);

/*
	Replace the "mail" function by something more flexible.
*/
function customMail ($target, $subject, $msg)
{
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer ();

        if (defined ('EMAIL_SMTP_SERVER')) {
            $mail->IsSMTP ();                                   // send via SMTP
            $mail->Host = EMAIL_SMTP_SERVER; // SMTP servers
            $mail->Port = EMAIL_SMTP_PORT;

            if (defined ('EMAIL_SMTP_USERNAME') && EMAIL_SMTP_USERNAME !== '') {
                $mail->SMTPAuth = true;     // turn on SMTP authentication

                if (defined ('EMAIL_SMTP_SECURE') && EMAIL_SMTP_SECURE !== '')
                    $mail->SMTPSecure = EMAIL_SMTP_SECURE;

                $mail->Username = EMAIL_SMTP_USERNAME;  // SMTP username
                $mail->Password = EMAIL_SMTP_PASSWORD; // SMTP password
            }
        }

        $mail->SMTPDebug = false;

        $from = EMAIL_FROM;
        if (empty($from)) {
            $from = 'support@catlab.be';
        }
        $mail->From = $from;

        $mail->FromName = EMAIL_FROM_NAME;
        $mail->CharSet  = 'utf-8';

        $mail->isHtml (false);
        $mail->AddAddress($target);

        $mail->Subject  =  $subject;
        $mail->Body = $msg;

        if (!$mail->send()) {
            throw new \Exception("Mail could not be send: " . $mail->ErrorInfo);
        }
    } catch (\Exception $e) {
	    error_log($e->getMessage());
    }
}

/*
	This function commands the game to reload the counters
*/
function reloadStatusCounters ()
{
	unset ($_SESSION['status_counters']);
}

// Reload function
function reloadEverything ()
{
	if (!defined ('RELOAD'))
	{
		define ('RELOAD', true);
	}
}

function utf8_urldecode($str) 
{
	$str = preg_replace("/%u([0-9a-f]{3,4})/i","&#x\\1;",urldecode($str));
	return html_entity_decode($str,null,'UTF-8');;
}

function return_bytes($val) 
{
	$val = trim($val);
	$last = strtolower($val[strlen($val)-1]);
	$val = intval(substr($val, 0, strlen($val)-1));
	switch($last) 
	{
		// The 'G' modifier is available since PHP 5.1.0
		case 'g':
		$val *= 1024;
		case 'm':
		$val *= 1024;
		case 'k':
		$val *= 1024;
	}

	return $val;
}

/******************************** 
 * Retro-support of get_called_class() 
 * Tested and works in PHP 5.2.4 
 * http://www.sol1.com.au/ 
 ********************************/ 
if(!function_exists('get_called_class')) { 
function get_called_class($bt = false,$l = 1) { 
    if (!$bt) $bt = debug_backtrace(); 
    if (!isset($bt[$l])) throw new Exception("Cannot find called class -> stack level too deep."); 
    if (!isset($bt[$l]['type'])) { 
        throw new Exception ('type not set'); 
    } 
    else switch ($bt[$l]['type']) { 
        case '::': 
            $lines = file($bt[$l]['file']); 
            $i = 0; 
            $callerLine = ''; 
            do { 
                $i++; 
                $callerLine = $lines[$bt[$l]['line']-$i] . $callerLine; 
            } while (stripos($callerLine,$bt[$l]['function']) === false); 
            preg_match('/([a-zA-Z0-9\_]+)::'.$bt[$l]['function'].'/', 
                        $callerLine, 
                        $matches); 
            if (!isset($matches[1])) { 
                // must be an edge case. 
                throw new Exception ("Could not find caller class: originating method call is obscured."); 
            } 
            switch ($matches[1]) { 
                case 'self': 
                case 'parent': 
                    return get_called_class($bt,$l+1); 
                default: 
                    return $matches[1]; 
            } 
            // won't get here. 
        case '->': switch ($bt[$l]['function']) { 
                case '__get': 
                    // edge case -> get class of calling object 
                    if (!is_object($bt[$l]['object'])) throw new Exception ("Edge case fail. __get called on non object."); 
                    return get_class($bt[$l]['object']); 
                default: return $bt[$l]['class']; 
            } 

        default: throw new Exception ("Unknown backtrace method type"); 
    } 
} 
} 

// Set include path
set_include_path (get_include_path () . PATH_SEPARATOR . CATLAB_BASEPATH . 'php' . PATH_SEPARATOR . PEAR_BASEPATH);

// Check for ref cookie
if (isset ($_GET['ref']))
{
	setcookie ('referee', $_GET['ref'], time() + 60*60, '/');
}

// Check for player referee cookie
if (isset ($_GET['pref']))
{
	setcookie ('preferrer', $_GET['pref'], time () + 60*60, '/');
}

if (isset ($_GET['bonus']))
{
	setcookie ('player_bonus', $_GET['bonus'], time () + 60*60, '/');
}

if (!defined('SHOW_ADS')) {
    define('SHOW_ADS', false);
}

// Crazy hacks because of my outdated code
$_REQUEST = $_REQUEST;
