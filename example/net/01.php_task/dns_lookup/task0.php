<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sn_dns.php";

echo "PHPoC example : get ip address by host name\r\n";

dns_setup(0, "");

$host_name = "www.google.com";
$host_addr = dns_lookup($host_name, RR_A);

if($host_addr == $host_name)
	echo "$host_name : Not Found\r\n";
else
	echo "$host_name : $host_addr\r\n";

?>
