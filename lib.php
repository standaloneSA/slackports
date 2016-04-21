<?php
// Useful library of functions for what we're doing

// The default SNMP library behavior is to throw a warning. This changes it to 
// an exception, which we can actually handle. yay.
set_error_handler(function($errno, $errstr, $errfile, $errline, array $errcontext) {
	//error was suppressed with the @-operator
	if (0 === error_reporting()) {
		return false;
	}
	
	throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});
//////////////

////////////////////////////////////////////////
function readMibs( $mib_path=0) { 
	if ( ! $mib_path ) { 
		$distro = getLinuxDistro(); 
		switch ($distro) { 
		case "debian":
		case "ubuntu":
			$mib_path = "/var/lib/mibs/"; 
			break; 
		case "fedora":
		case "centos":
		case "redhat":
			$mib_path = "/usr/share/snmp/mibs"; 
			break;
		default:
			$mib_path = "/usr/share/snmp/mibs"; 
		}
	}
	
	try { 
		if ($handle = opendir($mib_path)) {
   		 while (false !== ($file = readdir($handle))) { 
		 		if($file!='.') { 
					if($file!='..') { 
						if(!extension_loaded('snmp')) { 
							echo "Error: php-snmp extension not loaded. Aborting.\n"; 
							exit; 
						}

						if(snmp_read_mib($mib_path.'/'.$file)) { 
						} else { 
							echo "Read : ";        
			        		echo "$mib_path.$file";
							echo " Failed\n";
						}
					}
				}
			 }
		closedir($handle);
		}
	}

	catch(Exception $e){ 
		echo $e->getMessage() . "\n"; 
		exit; 
	}
} // end readMibs()

////////////////////////////////////////////////
function searchIntDesc($desc, $array) { 
	// from http://php.net/manual/en/function.preg-grep.php
	// $desc should be the description of the wall port, i.e. 310G-WVH-1D
	// $array is an array of values as returned from snmp2_walk()
	// Sample usage: serchIntDesc("310G-WVH-1F", snmp2_walk("switch.mydomain", "public", "IF-MIB::ifAlias"))
	//
	
	$keys = preg_grep("/^" . $desc . "/i", $array); 
	return $keys;
} // end searchIntDesc()

////////////////////////////////////////////////
function getPortInfo($portIndex, $switch) { 
	// Makes various SNMP queries about a port and creates an assoc array with information about it. 
	//
	// $portIndex is the interface number according to the SNMPwalk. For instance: 
	// getPortInfo("0", "myswitch.mydomain"); 

	$arrPortInfo = array(
		"switch"				=> $switch,
		"portID"				=>	$portIndex,
		"portVLAN"			=> "",
		"walljack"			=> "",
		"portNameShort"	=> "", 
		"portNameLong"		=> "",
	);

	$arrPortInfo["portVLAN"] = getPortVLAN($arrPortInfo["switch"], $arrPortInfo["portID"]); 
	$arrPortInfo["walljack"] = getPortWallJack($arrPortInfo["switch"], $arrPortInfo["portID"]);
	$arrPortInfo["portNameShort"] = getPortNameShort($arrPortInfo["switch"], $arrPortInfo["portID"]); 
	$arrPortInfo["portNameLong"] = getPortNameLong($arrPortInfo["switch"], $arrPortInfo["portID"]); 

	return $arrPortInfo;

} // end getPortInfo()

////////////////////////////////////////////////
function getPortVLAN($switch, $portID) { 
	// Takes the $portID (the interface number according to SNMP walk) 
	// and determines the vlan based on an snmp2_get(). 
	//
	
	global $readCom;
	try { 
		$vlanID = snmp2_get($switch, $readCom, "1.3.6.1.4.1.9.9.68.1.2.2.1.2." . $portID); 
	} catch (Exception $e) { 
		// The port is probably in trunked status
		$trunkStatus = snmp2_get($switch, $readCom, "1.3.6.1.4.1.9.9.46.1.6.1.1.13." . $portID); 
		if ( $trunkStatus == "on" ) { 
			return "TRUNK";
		} else {
			return "UNKNOWN"; 
		}
	}
	return $vlanID; 
} // end getPortVLAN()

////////////////////////////////////////////////
function getPortWallJack($switch, $portID) { 
	// Takes $portID which is the interface number according to SNMP walk
	// Note that this returns a string in the description field. Ideally, this
	// would be something like 310G-WVH-1F, but it could concievably be anything, 
	// so don't trust it too much. 
	//
	
	global $readCom; 
	$portDesc = snmp2_get($switch, $readCom, "IF-MIB::ifAlias." . $portID); 
	if ( ! preg_match("/\w+-WVH-\w+/", $portDesc, $wallJack) ) { 
		return("UNKNOWN"); 
	} else { 
		return($wallJack[0]); 
	}
} // end getPortWallJack()

////////////////////////////////////////////////
function getPortNameShort($switch, $portID) { 
	// should return something like Gi5/10
	global $readCom;
	return snmp2_get($switch, $readCom, "ifName.".$portID); 
}

////////////////////////////////////////////////
function getPortNameLong($switch, $portID) {
	// should return something like GigabitEthernet5/10
	global $readCom;
	return snmp2_get($switch, $readCom, "ifDescr.".$portID); 
}

////////////////////////////////////////////////
function setPortVLAN($switch, $portID, $newVLANID) { 
	// important parts of this function are that the current VLAN can't 
	// match the new VLAN, otherwise it will error out. 
	//

	global $writeCom, $readCom; 
	$newVLANID = intval($newVLANID); 


	if ( ! is_int($newVLANID) ) { 
		print "Error: VLAN ID should be an integer. Got $newVLANID.\n"; 
		return 0; 
	}
	
	if ( ! verifyVLANOperation($switch, $newVLANID) ) {
		print "Error: VLAN $newVLANID is non-operational on $switch\n"; 
		return 0; 
	}

	$currentVLAN = getPortVLAN($switch, $portID); 

	if ( $currentVLAN == "TRUNK" ) { 
		print "Error: Interface is in TRUNK mode.\n"; 
		return 0; 
	}

	if ( $newVLANID != $currentVLAN ) { 
		return snmp2_set($switch, $writeCom, "1.3.6.1.4.1.9.9.68.1.2.2.1.2." . $portID, "i", $newVLANID); 
	} else { 
		print "Notice: Existing VLAN is the same as requested VLAN\n";
		return 0; 
	}

} // end setPortVLAN()

////////////////////////////////////////////////
function verifyVLANOperation($switch, $VLANID) { 
	// typically called by setPortVLAN() to make sure that the given VLAN 
	// is actually working on the switch in question. You can't set a port 
	// to a VLAN that doesn't exist. 
	//
	
	global $readCom; 
	try {
		$status = snmp2_get($switch, $readCom, "1.3.6.1.4.1.9.9.46.1.3.1.1.2.1." . $VLANID); 
	} catch (exception $e) { 
		print "Error retrieving status on vlan $VLANID\n"; 
		return 0;
	}
	return $status; 
} // end verifyVLANOperation()	

////////////////////////////////////////////////

function saveSwitchConfig($switch, $quiet=0) { 
	// You are not expected to understand this function and the various
	// esoteric OIDs. More information is here:
	// http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15217-copy-configs-snmp.html
	// The trick is actually in the SNMP put timings so that the switch treats
	// all of the commands as relevant to the same session. 
	global $writeCom, $readCom;

	$rand = rand(1,256); 

	$ccCopySourceFileType="1.3.6.1.4.1.9.9.96.1.1.1.1.3"; 
	$ccCopyDestFileType="1.3.6.1.4.1.9.9.96.1.1.1.1.4"; 
		$networkFile="1";
		$iosFile="2";
		$startupConfig="3";
		$runningConfig="4";

	$ccCopyProtocol="1.3.6.1.4.1.9.9.96.1.1.1.1.2";
		$tftp="1"; 
		$rcp="2";

	$ccCopyEntryRowStatus="1.3.6.1.4.1.9.9.96.1.1.1.1.14";
		$active="1";
		$createAndGo="4";
		$createAndWait="5";
		$destroy="6";

	$ccCopyServerAddress="1.3.6.1.4.1.9.9.96.1.1.1.1.5"; 
	$ccCopyFileName="1.3.6.1.4.1.9.9.96.1.1.1.1.6";

	$ccCopyState="1.3.6.1.4.1.9.9.96.1.1.1.1.10";
		$waiting="1"; 
		$running="2";
		$successful="3";
		$failed="4";

	if (! $quiet) { echo "Saving config...\n"; } 
//	try { 
//		echo "Setting the source file type\n"; 
//		$status = snmp2_set($switch, $writeCom, $ccCopySourceFileType . "." . $rand, "i", $runningConfig); 
//		echo "Status: $status\nSetting the destination file type\n"; 
//		$status = snmp2_set($switch, $writeCom, $ccCopyDestFileType . "." . $rand, "i", $startupConfig); 
//		echo "Status: $status\nSetting the create-and-go\n"; 
//		$status = snmp2_set($switch, $writeCom, $ccCopyEntryRowStatus . "." . $rand, "i", $createAndGo); 
//		echo "Status: $status\n"; 
//
//	} catch (exception $E) { 
//		echo "Failed: ", $E->getMessage(), "\n"; 
//		return 0; 
//	}
	// This is horrible, but snmp2_set() only pushes one OID at a time. 
	// #BadDesign -MS

	$result = exec("/usr/bin/snmpset -v 2c -c $writeCom $switch \
		$ccCopySourceFileType.$rand i $runningConfig \
		$ccCopyDestFileType.$rand i $startupConfig \
		$ccCopyEntryRowStatus.$rand i $createAndGo \
	2>&1"); 




	$count = 0;
	while ( $count < 10 ) { 
		$status = snmp2_get($switch, $readCom, $ccCopyState . "." . $rand); 
		switch($status) { 
			case $running:
				if ( ! $quiet ) { echo ".";} 
				sleep($count); 
				$count++; 
				break;
			case $successful:
				if ( ! $quiet ) { echo "Configuration saved.\n"; } 
				return 1; 
				break; 
			case $failed:
				if ( ! $quiet ) { echo "Error saving configuration.\n"; } 
				return 0; 
				break;
			default:
				if ( ! $quiet ) { echo "Warning: unknown state: $status\nContinuing...\n"; } 
				$count++; 
				break;
		}
	}

	// if we get here, things are bad, so we complain and punt. 
	if ( ! $quiet ) { echo "Error saving configuration.\n"; } 
	return 0; 

} // end saveSwitchConfig()

////////////////////////////////////////////////

function getLinuxDistro() { 
	// from http://stackoverflow.com/questions/16334577/if-php-is-running-on-linux-how-to-get-the-specific-distributionubuntu-fedora
	$distros = array(
		'arch' 	=> 'arch-release',
		'ubuntu'	=> 'lsb-release',
		'debian'	=> 'debian_version',
		'fedora'	=> 'fedora-release',
		'redhat'	=> 'redhat-release',
		'centos'	=> 'centos-release'
	); 

	$etcList = scandir('/etc/'); 
	if ( ! $etcList ) { 
		echo "Warning: Error determining Linux distribution\n"; 
		echo "Continuing"; 
	}
	foreach ( $etcList as $entry ) { 
		foreach ( $distros as $distroReleaseFile ) { 
			if ( $distroReleaseFile === $entry ) { 
				$OSDistro = array_search($distroReleaseFile, $distros); 
				break 2; 
			} 
		}
	}

	return $OSDistro; 
} // end getLinuxDistro()

////////////////////////////////////////////////

function findWalljack($desc) { 
	// This is a function that basically does all the hard work for us. 
	// Takes a description, returns a port object. 

	global $readCom, $switches; 

	readMibs(); 
	snmp_set_quick_print(1); 
	$intSwitch = ""; 
	foreach ( $switches as $switch ) { 
		try { 
			$arrIDs = searchIntDesc($desc, snmp2_walk($switch, $readCom, "IF-MIB::ifAlias")); 
			if ( count($arrIDs) != 0) { 
				$intSwitch = $switch; 
				break;
			}
		} catch ( Exception $E ) { 
			print $E->getMessage() . "\n"; 
			print "Does this machine have permission to query the switches?\n"; 
		} 
	}
	if ( ! $intSwitch ) { 
		return 0; 
	} 

	$port = getPortInfo(key($arrIDs)+2, $intSwitch); 

	return $port; 
} // end findWalljack()


?>
