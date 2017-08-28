<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include "/lib/sd_340.php";
include "/lib/sd_spc.php";
include "/lib/sn_tcp_ws.php";

define("STEPPER_SID", 1);
define("STEPS_PER_REV", 200); // 200 steps per revolution (1.8 degree each step)
define("SPEED_LOWER_LIMIT", 32);
define("SPEED_UPPER_LIMIT", 100000);

function step_loop()
{
	$last_tick = st_free_get_count(0);
	$rwbuf = "";

	while(ws_state(0) == TCP_CONNECTED)
	{
		$rlen = ws_read_line(0, $rwbuf);

		if($rlen)
		{
			$dur_ms = st_free_get_count(0) - $last_tick;

			$last_tick = st_free_get_count(0);

			$angle = -(float)$rwbuf;
			$pos = (int)(STEPS_PER_REV * 32.0 * $angle / 360.0);

			$step = $pos - (int)spc_request_dev(STEPPER_SID, "get pos");

			if($step)
			{
				$speed = (int)abs((float)$step / (float)$dur_ms * 1000.0);
				$speed = $speed * 8 / 10;

				if($speed < SPEED_LOWER_LIMIT)
					$speed = SPEED_LOWER_LIMIT;
				else
				if($speed > SPEED_UPPER_LIMIT)
					$speed = SPEED_UPPER_LIMIT;

				spc_request_dev(STEPPER_SID, "goto $pos $speed 1000k");
			}
		}

		usleep(1000);
	}
}

spc_reset();
spc_sync_baud(115200);

spc_request_dev(STEPPER_SID, "set vref drive 12");
spc_request_dev(STEPPER_SID, "set mode 32");

ws_setup(0, "pes_2405_touch_rotate", "csv.phpoc");
 
while(1)
{
	if(ws_state(0) == TCP_CONNECTED)
	{
		$pos = (int)spc_request_dev(STEPPER_SID, "get pos");
		$angle = -(float)$pos / (STEPS_PER_REV * 32.0) * 360.0;

		ws_write(0, "$angle\r\n");

		step_loop();
	}
}

?>
