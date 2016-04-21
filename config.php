<?php

// This is the primary configuration for switchports. 
// It holds the list of switches, the passwords and 
// community strings, and so on. 

$switches = array(
	"switch1",
	"switch2",
	"switch3"
); 

$readCom = "public"; 
$writeCom = "private";
$enable = "cisco";

// This is the token configured in the outgoing slack.com slash command
// If you're not using a slack integration, just leave it commented.
$token="11111";

// These are the usernames in slack that can (only) query ports. These
// people will not be able to change ports, only query their status.
$slackQueryUsers=array(
	"readuser1",
	"readuser2",
	"readuser3",
	"readuser4",
);

// These users are able to change ports. Changing ports implies that they
// can also query ports, so there's no need to declare them twice.
$slackChangePortUsers=array(
	"writeuser1",
	"writeuser2",
	"writeuser3",
	"writeuser4",
);




?>
