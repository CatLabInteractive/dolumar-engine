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

    return Neuron_Auth_OpenIDConnect::fromConfig($returnUrl);
}
