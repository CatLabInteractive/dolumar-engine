<?php

require 'bootstrap.php';

$flow = getOpenIDConnectFlow();
$url = $flow->getAuthorizationUrl();
header('Location: ' . $url);

