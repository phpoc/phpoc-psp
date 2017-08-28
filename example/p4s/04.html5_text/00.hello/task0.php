<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include "/lib/sd_340.php";
include "/lib/sn_tcp_ws.php";

ws_setup(0, "WebConsole", "text.phpoc");

while(1)
{
	if(ws_state(0) == TCP_CONNECTED)
	{
		ws_write(0, "hello, world!\r\n");

		sleep(1);
	}
}
 
?>
