<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sn_dns.php";
include_once "/lib/sn_esmtp.php";

echo "PHPoC example : send email via out-going mail server\r\n";

//esmtp_setup(udp_id, tcp_id, "x.x.x.x");
//esmtp_hostname("from_domain.com");
esmtp_account("from_id@from_domain.com", "from_name");
esmtp_auth("msa_id", "msa_password");
esmtp_msa("smtp.gmail.com", 465);
//esmtp_msa("smtp.naver.com", 465);
//esmtp_msa("smtp.daum.net", 465);

$subject = "msa test";
$message = "Hi PHPoC\r\nThis is PHPoC msa test email\r\nGood bye\r\n";

$msg = esmtp_send("to_id@to_domain.com", "to_name", $subject, $message);

if($msg == "221")
	echo "send mail successful\r\n";
else
	echo "send mail failed\r\n";

?>
