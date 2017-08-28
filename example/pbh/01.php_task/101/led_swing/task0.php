<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sd_101.php";

function led_inc_toggle($loop_count)
{
	for($i = 0; $i < $loop_count; $i++)
	{
		led_out(LED_A + $i % 8, TOGGLE);
		usleep(100000);
	}
}

function led_dec_toggle($loop_count)
{
	for($i = 0; $i < $loop_count; $i++)
	{
		led_out(LED_A + (7 - $i % 8), TOGGLE);
		usleep(100000);
	}
}

echo "PHPoC example : PBH-101 / swing LED A~H\r\n";

for($port = LED_A; $port <= LED_H; $port++)
	led_out($port, HIGH);

while(1)
{
	led_inc_toggle(16);
	led_dec_toggle(16);
}

?>
