<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sn_dns.php";
include_once "/lib/sn_smtp.php";

echo "PHPoC example : send email\r\n";

//smtp_setup(udp_id, tcp_id, "x.x.x.x");
//smtp_hostname("from_domain.com");
//smtp_account("from_id@from_domain.com", "from_name");

$subject = "email test from PHPoC";
$message = "This is PHPoC test email\r\nGood bye\r\n";

$msg = smtp_send("to_id@to_domain.com", "to_name", $subject, $message);

if($msg == "221")
	echo "send email successful\r\n";
else
	echo "send email failed\r\n";

?>
