<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sd_spc.php";

define("STEPPER_SID", 1);

echo "PHPoC example : P4M-400 / PES-2405 / move fast\r\n";

spc_reset(); // reset all smart slaves stacked on P4M-400
spc_sync_baud(115200); // synchronize master to slave baud-rate

printf("%d smart expansion(s) found\r\n", spc_scan(1, 14, true));

spc_request_dev(STEPPER_SID, "set vref stop 4");   // set stop current to 4/15
spc_request_dev(STEPPER_SID, "set vref drive 15"); // set drive current to 15/15
spc_request_dev(STEPPER_SID, "set mode 32");       // set micro-step to 1/32

while(1)
{
	for($i = 0; $i < 4; $i++)
	{
		// equivalent command : "move +3200 32k 320k"
		spc_request_dev(STEPPER_SID, "move +3200 32000 320000");
		while((int)spc_request_dev(STEPPER_SID, "get state"))
			usleep(1);
		usleep(200000);
	}

	for($i = 0; $i < 4; $i++)
	{
		// equivalent command : "move -16000 160k 1600k"
		spc_request_dev(STEPPER_SID, "move -16000 160000 1600000");
		while((int)spc_request_dev(STEPPER_SID, "get state"))
			usleep(1);
		usleep(200000);
	}

	$pos = -(int)spc_request_dev(STEPPER_SID, "get pos");

	spc_request_dev(STEPPER_SID, "move $pos"); // return to initial position
	while((int)spc_request_dev(STEPPER_SID, "get state"))
		usleep(1);
	usleep(200000);
}

?>
