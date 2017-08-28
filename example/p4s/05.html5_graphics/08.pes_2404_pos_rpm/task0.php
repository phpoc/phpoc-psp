<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include "/lib/sd_340.php";
include "/lib/sd_spc.php";
include "/lib/sn_tcp_ws.php";

define("DC_SID", 1);
define("ENC_PER_REV", 52); // 52 encoder count per revolution (13 pulses x 2CH x 2edge)

$ws_last_pos = 0;
$ws_last_rpm = 0;

function ws_send_pos_pwm()
{
	global $ws_last_pos, $ws_last_rpm;

	if(ws_state(0) != TCP_CONNECTED)
		return;

	$pos = (int)spc_request_dev(DC_SID, "dc1 enc get pos");
	$period = (int)spc_request_dev(DC_SID, "dc1 enc get period");

	if($period && ($period < 1000000))
		$rpm = (int)(1000000.0 / $period * 60.0 / ENC_PER_REV);
	else
		$rpm = 0;

	if(($pos != $ws_last_pos) || ($rpm != $ws_last_rpm))
	{
		ws_write(0, "$pos,$rpm\r\n");

		$ws_last_pos = $pos;
		$ws_last_rpm = $rpm;
	}
}

function dc_loop()
{
	$ws_next_tick = st_free_get_count(0) + 100;
	$rwbuf = "";

	while(ws_state(0) == TCP_CONNECTED)
	{
		$rlen = ws_read_line(0, $rwbuf);

		if($rlen)
		{
			$pwm = (int)$rwbuf * 100;

			if($pwm >= 0)
				spc_request_dev(DC_SID, "dc1 pwm set pol +");
			else
			{
				$pwm = -$pwm;
				spc_request_dev(DC_SID, "dc1 pwm set pol -");
			}

			spc_request_dev(DC_SID, "dc1 pwm set width $pwm");
		}

		if($ws_next_tick <= st_free_get_count(0))
		{
			ws_send_pos_pwm();
			$ws_next_tick = st_free_get_count(0) + 100;
		}

		usleep(1000);
	}
}

spc_reset(); // reset all smart slaves stacked on P4S-34X
spc_sync_baud(115200); // synchronize master to slave baud-rate

spc_request_dev(DC_SID, "dc1 pwm set period 10000");
//spc_request_dev(DC_SID, "dc1 pwm set pol -"); // winding current direction polarity
//spc_request_dev(DC_SID, "dc1 enc set pol -"); // encoder pulse to pos count polarity

ws_setup(0, "pes_2404_pos_rpm", "csv.phpoc");
 
while(1)
{
	if(ws_state(0) == TCP_CONNECTED)
	{
		ws_send_pos_pwm();
		dc_loop();
	}
}

?>
