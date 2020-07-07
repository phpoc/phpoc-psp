<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include "/lib/sd_340.php";
include "/lib/sn_tcp_ws.php";

define("IN_PIN", 0);

uio_setup(0, IN_PIN, "in");
ws_setup(0, "WebConsole", "text.phpoc");

$last_touch = 0;

while(1)
{
	if(ws_state(0) == TCP_CONNECTED)
	{
		$touch = uio_in(0, IN_PIN);

		if($touch != $last_touch)
		{
			if($touch)
				ws_write(0, "touch ON\r\n");
			else
				ws_write(0, "touch OFF\r\n");

			$last_touch = $touch;
		}
	}
}
 
?>
