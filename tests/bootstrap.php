<?php

require_once __DIR__ . '/../vendor/autoload.php';

$url = getenv('DATABASE_URL') ?: 'mysql://dolumar:dolumar@mysql/dolumar';
$parts = parse_url($url);

if ($parts === false || !isset($parts['host'])) {
	throw new RuntimeException('Invalid DATABASE_URL');
}

$constants = [
	'DB_SERVER' => $parts['host'],
	'DB_PORT' => isset($parts['port']) ? (int) $parts['port'] : 3306,
	'DB_USERNAME' => isset($parts['user']) ? urldecode($parts['user']) : '',
	'DB_PASSWORD' => isset($parts['pass']) ? urldecode($parts['pass']) : '',
	'DB_DATABASE' => ltrim($parts['path'] ?? '', '/'),
];

foreach ($constants as $name => $value) {
	if (!defined($name)) {
		define($name, $value);
	}
}
