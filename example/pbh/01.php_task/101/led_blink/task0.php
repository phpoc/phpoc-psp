<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sd_101.php";

echo "PHPoC example : PBH-101 / blink LED A, B\r\n";

while(1)
{
	led_out(LED_A, LOW);
	led_out(LED_B, HIGH);
	usleep(250000);

	led_out(LED_A, HIGH);
	led_out(LED_B, LOW);
	usleep(250000);
}

?>
