<?php 

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include "/lib/sd_340.php";
include "/lib/sn_tcp_ws.php";

adc_setup(0, 0); // adc0, channel 0
ws_setup(0, "rotary_angle", "csv.phpoc");

$last_adc_in = 0;

while(1)
{
	if(ws_state(0) == TCP_CONNECTED)
	{
		$adc_in = adc_in(0, 30);

		if(abs($adc_in - $last_adc_in) > 5)
		{
			$adc_1000 = (int)round((float)$adc_in / 4095 * 1000);
			ws_write(0, (string)$adc_1000 . "\r\n");

			$last_adc_in = $adc_in;
		}
	}
}

?>

