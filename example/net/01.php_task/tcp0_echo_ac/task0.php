<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sn_tcp_ac.php";

echo "PHPoC example : TCP echo using auto connect library\r\n";

tcp_server(0, 14700);

$rwbuf = "";

while(1)
{
	$rwlen = tcp_read(0, $rwbuf, tcp_txfree(0));
	if($rwlen > 0)
		tcp_write(0, $rwbuf);
}

?>
