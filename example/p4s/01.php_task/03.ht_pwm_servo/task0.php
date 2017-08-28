<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sd_340.php";

define("PWM_PERIOD", 20000); // 20000us (20ms)
define("WIDTH_MIN", 600);
define("WIDTH_MAX", 2450);

echo "PHPoC example : P4S-34X / HT / Tower Pro SG92R Micro Servo\r\n";

ht_pwm_setup(0, WIDTH_MIN, PWM_PERIOD, "us");

echo "CCW ";
for($angle = 0; $angle <= 180; $angle += 45)
{
	echo $angle, " ";

	$width = WIDTH_MIN + (int)round($angle / 180.0 * (WIDTH_MAX - WIDTH_MIN));
	ht_pwm_width(0, $width, PWM_PERIOD);

	sleep(1);
}
echo "\r\n";

echo "CW ";
for($angle = 180; $angle >= 0; $angle -= 45)
{
	echo $angle, " ";

	$width = WIDTH_MIN + (int)round($angle / 180.0 * (WIDTH_MAX - WIDTH_MIN));
	ht_pwm_width(0, $width, PWM_PERIOD);

	sleep(1);
}
echo "\r\n";

?>
