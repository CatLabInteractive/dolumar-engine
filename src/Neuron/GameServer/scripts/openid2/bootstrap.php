<?php

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

if (isset($_COOKIE['dolumar-auth'])) {
    session_id($_COOKIE['dolumar-auth']);
}

session_name('dolumar-auth');
session_start([
    'use_cookies' => 1,
    'cookie_path' => '/'
]);

function getOpenIDConnectFlow() {
    $returnUrl = Neuron_URLBuilder::getInstance()->getRawURL('oauth2/login/next', []);

    $config = [
        'client_id' => OPENID_CONNECT_CLIENT_ID,
        'redirect_uri' => $returnUrl,
        'authorization_endpoint' => OPENID_CONNECT_AUTHORIZE_URL,
        'token_endpoint' => OPENID_CONNECT_TOKEN_URL,
        'user_info_endpoint' => OPENID_CONNECT_PROFILE_URL,
        'authentication_info' => [
            'method' => 'client_secret_post',
            'params' => array(
                'client_secret' => OPENID_CONNECT_SECRET
            )
        ]
    ];

    return new \InoOicClient\Flow\Basic(['client_info' => $config ]);
}
