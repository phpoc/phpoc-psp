<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include "/lib/sd_340.php";
include "/lib/sn_tcp_ws.php";

adc_setup(0, 0); // adc0, channel 0
ws_setup(0, "WebConsole", "text.phpoc");

$last_adc_in = 0;
 
while(1)
{
	if(ws_state(0) == TCP_CONNECTED)
	{
		$adc_in = adc_in(0, 30);

		if(abs($adc_in - $last_adc_in) > 5)
		{
			ws_write(0, sprintf("Voltage : %.2fV\r\n", $adc_in / 4095.0 * 3.3));

			$last_adc_in = $adc_in;

			usleep(100000);
		}
	}
}

?>
