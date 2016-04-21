#!/usr/bin/php
<?php 
// index.php
// 
// Displays the initial form to gather information

require("config.php"); 
require_once("lib.php"); 


readMibs(); 

if ( $argc < 2 ) { 
	print "Usage: $argv[0] <walljack> <new-vlan-id>\n"; 
	exit;
}

try { 
	$desc = $argv[1]; 
	$newVLAN = $argv[2]; 
} catch (exception $e) { 
		print "Error: Unable to determine port and vlan.\n"; 
		exit; 
}


// Turn off printing STRING: or INTEGER: before values
snmp_set_quick_print(1);

$intSwitch = ""; 

foreach ( $switches as $switch) { 
	try { 
		$arrIDs = searchIntDesc($desc, snmp2_walk($switch, $readCom, "IF-MIB::ifAlias")); 
		if (count($arrIDs) != 0) { 
			$intSwitch=$switch; 
			break; 
		}
	} 
	catch (Exception $e) { 
		print $e->getMessage() . "\n"; 
		print "Does this machine have permission to query the switches?\n"; 
		exit;
	}
}

if ( ! $intSwitch ) { 
	print "Error: Unable to find switchport matching $desc\n"; 
	exit -1;
}; 

// The +2 is because there is an offset. I haven't tracked it down
// entirely, but I believe it's because of a zero-index array and 
// a non-zero index SNMP array, plus a leading blank entry. Not sure.
// This works, though.
$port = getPortInfo(key($arrIDs)+2, $intSwitch); 

print "Prior to reassignment:\n"; 
print_r($port); 


print "\n\nReassigning VLAN:\n"; 

if ( $retVal = setPortVLAN($port["switch"], $port["portID"], $newVLAN) ) { 
	print "Changed VLAN:\n"; 
	$port = getPortInfo($port["portID"], $port["switch"]); 
	print_r($port); 
} else { 
	print "Error changing VLAN ID.\n"; 
}

if ( ! saveSwitchConfig($switch) ) { 
	echo "Please save the switch configuration manually.\n"; 
}






