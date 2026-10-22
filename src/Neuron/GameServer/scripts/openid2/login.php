<?php

require 'bootstrap.php';

function handleOpenIDConnectAuthentication() {
    $flow = getOpenIDConnectFlow();

    try {
        $userInfo = $flow->getProfile($_GET);
    } catch (RuntimeException | \League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
        error_log('OIDC login failed: ' . get_class($e) . ': ' . $e->getMessage());

        $loginUrl = Neuron_URLBuilder::getInstance()->getRawURL('oauth2/login', []);

        http_response_code(400);
        echo 'Login failed. Please click <a href="'.$loginUrl.'">here</a> to try again.';
        return;
    }

    $id = $userInfo['id'];
    $email = $userInfo['email'];
    $username = $userInfo['username'];
    $emailVerified = $userInfo['verified_email'];

    if (!$emailVerified) {
        $loginUrl = Neuron_URLBuilder::getInstance()->getRawURL('oauth2/login', []);

        echo 'Please verify your email address before logging in and click <a href="'.$loginUrl.'">here</a> to try again.';
        return;
    }

    $openIdProfileUrl = Neuron_Auth_OpenIDConnect::openIdUrlFor($id);

    // We're going to follow the openid flow now.
    $_SESSION['openid_nickname'] = $username;
    $_SESSION['dolumar_openid_identity'] = $openIdProfileUrl;
    $_SESSION['dolumar_openid_email'] = $email;

    //$loginUrl = Neuron_URLBuilder::getInstance()->getRawURL('oauth2/login', []);

    $db = Neuron_Core_Database::__getInstance ();

    // Check if we need to convert an old account to a new account (based on email address)
    if (Neuron_Auth_OpenIDConnect::findUserId($openIdProfileUrl) === null) {
        // Look for player with the same email address
        $player = Neuron_GameServer_Mappers_PlayerMapper::getFromEmail($email);
        if ($player) {
            $db->insert('n_auth_openid', [
                'openid_url' => $openIdProfileUrl,
                'user_id' => $player->getId()
            ]);
        }
    }

    // New session id after login, so an id planted before login is worthless.
    session_regenerate_id(true);

    $openid = new Neuron_Auth_OpenID ();
    $openid->handleAuthentication($openIdProfileUrl, $email);
}

handleOpenIDConnectAuthentication();
