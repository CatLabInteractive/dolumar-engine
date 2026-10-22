<?php

function getValue ($name)
{
	$var = $GLOBALS[$name];
	if (isset ($var['test']))
	{
		echo "Key found\n\n";
	}
	else
	{
		echo "Key not found\n\n";
	}
}

echo '<pre>';

getValue ('_REQUEST');

// Comment this out to make it work.
// print_r ($_REQUEST);

echo '</pre>';