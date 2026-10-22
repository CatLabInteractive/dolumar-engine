<?php

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$authSessionId = Neuron_Core_Tools::sanitizeSessionId($_COOKIE['dolumar-auth'] ?? null);
if ($authSessionId !== null) {
    session_id($authSessionId);
}

session_name('dolumar-auth');
session_start(Neuron_Core_Tools::getSessionOptions([
    'use_cookies' => 1,
    'cookie_path' => '/'
]));

function getOpenIDConnectFlow() {
    $returnUrl = Neuron_URLBuilder::getInstance()->getRawURL('oauth2/login/next', []);

    return Neuron_Auth_OpenIDConnect::fromConfig($returnUrl);
}
