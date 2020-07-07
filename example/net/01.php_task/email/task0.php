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
	echo "Send email successful\r\n";
else
{
	echo "Send email failed: ";
	echo "Some receiving mail servers may check the 'Reverse DNS Mismatch' as an indication of a possible spam source. ";
	echo "If the hostname did not match the reverse lookup (PTR) for the IP Address, ";
	echo "the server may move the email to the spam mailbox or reject it without notice\r\n";
}

?>
