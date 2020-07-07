<?php 

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include "/lib/sd_340.php";
include "/lib/sn_tcp_ws.php";

define("ADC_MAX", 2520);

adc_setup(0, 0); // adc0, channel 0
ws_setup(0, "WebConsole", "text.phpoc");

$last_adc_in = 0;

while(1)
{
	if(ws_state(0) == TCP_CONNECTED)
	{
		$adc_in = adc_in(0, 30);

		if($adc_in > ADC_MAX)
			$adc_in = ADC_MAX;

		if(abs($adc_in - $last_adc_in) > 5)
		{
			ws_write(0, sprintf("Illuminance level : %d(%%)\r\n", $adc_in * 100 / ADC_MAX));

			$last_adc_in = $adc_in;

			sleep(1);
		}
	}
}

?>

