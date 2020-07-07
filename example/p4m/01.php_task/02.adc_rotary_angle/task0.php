<?php 

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include "/lib/sd_340.php";

echo "PHPoC example : P4M-400 / ADC / Catalex Rotary Angle Sensor\r\n";

adc_setup(0, 0); // adc0, channel 0

$last_adc_in = 0;

while(1)
{
	$adc_in = adc_in(0, 30);

	if(abs($adc_in - $last_adc_in) > 5)
	{
		printf("Voltage : %.2fV\r\n", $adc_in / 4095.0 * 3.3);

		$last_adc_in = $adc_in;

		usleep(100000);
	}
}

?>

