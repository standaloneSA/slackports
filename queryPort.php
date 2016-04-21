#!/usr/bin/php
<?php

require("config.php"); 
require_once("lib.php"); 

readMibs(); 

if ( $argc < 2 ) { 
	print "Usage: $argv[0] <walljack>\n"; 
	exit;
}

try { 
	$desc = $argv[1]; 
} catch (exception $e) { 
	print "Error: Unable to determine port and vlan.\n";
	exit;
}

$port = findWalljack($desc); 

print_r($port); 




?>
