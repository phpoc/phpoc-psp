<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sd_spc.php";

define("STEPPER_SID", 1);

echo "PHPoC example : P4M-400 / PES-2405 / move slow\r\n";

spc_reset(); // reset all smart slaves stacked on P4M-400
spc_sync_baud(115200); // synchronize master to slave baud-rate

printf("%d smart expansion(s) found\r\n", spc_scan(1, 14, true));

spc_request_dev(STEPPER_SID, "set vref stop 4");   // set stop current to 4/15
spc_request_dev(STEPPER_SID, "set vref drive 12"); // set drive current to 12/15
spc_request_dev(STEPPER_SID, "set mode 32");       // set micro-step to 1/32

while(1)
{
	// steps +6400, speed 6400, accel 64000
	spc_request_dev(STEPPER_SID, "move +6400 6400 64000");
	while((int)spc_request_dev(STEPPER_SID, "get state"))
		usleep(1);
	sleep(1);

	// steps -6400, speed 6400, accel 64000
	spc_request_dev(STEPPER_SID, "move -6400 6400 64000");
	while((int)spc_request_dev(STEPPER_SID, "get state"))
		usleep(1);
	sleep(1);
}

?>
