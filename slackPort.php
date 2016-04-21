<?php
// This is the actual slack integration file. 
// 
// This file gets called by Slack.com as a POST operation. Various things need to match
// our assumptions, such as the token in the config file being the one presented by 
// slack.com. 
//

require("config.php"); 
require("lib.php");

if ( ! $_POST ) { 
	header("HTTP/1.1 405 Method Not Allowed"); 
	header("Allow: POST"); 
	print "Error: Page only permits POST"; 
	exit -1; 
}

if ( $_POST['token'] != $token ) { 
	error_log("Unauthorized slack token: " . $_POST['token'] . "but I needed $token"); 
	print "Error: Unauthorized token. Please check configuration."; 
	exit -1; 
} 

error_log("Finding stuff"); 
$arrLine = preg_split('/\s+/', $_POST['text']); 
$command = $arrLine[0];
$desc = $arrLine[1]; 
if ( count($arrLine) > 2 ) { 
	$vlan = $arrLine[2]; 
} 


$channel = $_POST['channel_name']; 
switch ( $command ) { 
	case "/pq":
	case "pq":
	case "qp":
	case "/qp":
		if ( checkPerms(array_merge($slackQueryUsers, $slackChangePortUsers)) ) { 
			if (preg_match("/^[0-9]{3}[A-Z]?[0-9]?-WVH-[0-9]{1,2}[A-F]$/i", $desc)) {
                $port = findWalljack($desc); 
			    if ( $port ) { 
				    $Response['text'] = "Port " . $port['walljack'] . " (" . $port['portNameShort'] . ") is VLAN " . $port['portVLAN'] . " on switch " . $port['switch']; 
			    } else { 
				    $Response['text'] = "Port $desc  wasn't found. Sorry.";
			    }
            } else {
                $Response['text'] = "Port $desc does not match the expected format.";
            }
		} else { 
			$Response['text'] = "Sorry, you don't seem to have permission to query switchports.";
		}
		print json_encode($Response); 
		break; 
	case "/pc":
	case "pc":
		if ( checkPerms($slackChangePortUsers) ) { 
			error_log("Changing port $desc to vlan $vlan"); 
			if (preg_match("/^[0-9]{3}[A-Z]?[0-9]?-WVH-[0-9]{1,2}[A-F]$/i", $desc)) {
                $port = findWalljack($desc); 
			    if ( $port ) { 
				    if ( setPortVLAN($port['switch'], $port['portID'], $arrLine[2]) ) { 
					    error_log("Running save switch config"); 
					
					    // second argument is "quiet"
					    if ( saveSwitchConfig($port['switch'], 1) ) { 
						    $Response['text'] = "Port " . $port['walljack'] . " (" . $port['portNameShort'] . ") on " 
							    . $port['switch'] . " changed to VLAN " 
							    . $arrLine[2] . " - The switch config was saved successfully."; 
						    error_log("Returned from save switch config"); 
					    } else { 
						    $Response['text'] = "Port " . $port['walljack'] . " (" . $port['portNameShort'] . ") on " 
							    . $port['switch'] . " changed to VLAN " 
							    . $arrLine[2] . " but there was an error saving switch config. Please rectify manually."; 
						    error_log("Returned from save switch config"); 
					    }
				    } else { 
					    $Response['text'] = "Error setting port " . $port['walljack'] . " to VLAN " . $arrLine[2];
				    }
			    } else { 
				        $Response['text'] = "Port $desc wasn't found. Sorry.";
			        }
            } else {
                $Response['text'] = "Port $desc does not match the expected format.";
            }
		} else { 
			$Response['text'] = "Sorry, you don't seem to have permission to change switchports."; 
		}
		print json_encode($Response); 
		break; 
	default:
		break;
}


function checkPerms($arrUsers) { 
	return in_array($_POST['user_name'], $arrUsers); 
} // end checkPerms()

function slackResponse($message) { 

} // end slackResponse()


?>
