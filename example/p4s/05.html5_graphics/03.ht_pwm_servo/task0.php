<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include "/lib/sd_340.php";
include "/lib/sn_tcp_ws.php";

define("PWM_PERIOD", 20000); // 20000us (20ms)
define("WIDTH_MIN", 600);
define("WIDTH_MAX", 2450);

ht_pwm_setup(0, (WIDTH_MIN + WIDTH_MAX) / 2, PWM_PERIOD, "us");
ws_setup(0, "ht_pwm_servo", "csv.phpoc");

$rwbuf = "";
 
while(1)
{
	if(ws_state(0) == TCP_CONNECTED)
	{
		$rlen = ws_read_line(0, $rwbuf);

		if($rlen)
		{
			$angle = -(int)$rwbuf + 90;

			if($angle < 0)
				$angle = 0;

			if($angle > 180)
				$angle = 180;

			$width = WIDTH_MIN + (int)round((WIDTH_MAX - WIDTH_MIN) * $angle / 180.0);

			if(($width >= WIDTH_MIN) && ($width <= WIDTH_MAX))
				ht_pwm_width(0, $width, PWM_PERIOD);
		}
	}
}
 
?>
