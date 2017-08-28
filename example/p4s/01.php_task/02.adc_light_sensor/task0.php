<?php 

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include "/lib/sd_340.php";

define("ADC_MAX", 2520);

echo "PHPoC example : P4S-34X / ADC / Catalex Light Sensor\r\n";

adc_setup(0, 0); // adc0, channel 0

$last_adc_in = 0;

while(1)
{
	$adc_in = adc_in(0, 30);

	if($adc_in > ADC_MAX)
		$adc_in = ADC_MAX;

	if(abs($adc_in - $last_adc_in) > 5)
	{
		printf("Illuminance level : %d(%%)\r\n", $adc_in * 100 / ADC_MAX);

		$last_adc_in = $adc_in;

		sleep(1);
	}
}

?>

