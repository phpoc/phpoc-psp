<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sd_spc.php";

define("IO_OUT_SID", 1);

echo "PHPoC example : P4S-34X / PES-2401 / blink output\r\n";

spc_reset(); // reset all smart slaves stacked on P4S-34X
spc_sync_baud(115200); // synchronize master to slave baud-rate

printf("%d smart expansion(s) found\r\n", spc_scan(1, 14, true));

while(1)
{
	spc_request_dev(IO_OUT_SID, "set 0 output high");
	spc_request_dev(IO_OUT_SID, "set 1 output high");
	spc_request_dev(IO_OUT_SID, "set 2 output high");
	spc_request_dev(IO_OUT_SID, "set 3 output high");
	sleep(1);

	spc_request_dev(IO_OUT_SID, "set 0 output low");
	spc_request_dev(IO_OUT_SID, "set 1 output low");
	spc_request_dev(IO_OUT_SID, "set 2 output low");
	spc_request_dev(IO_OUT_SID, "set 3 output low");
	sleep(1);
}

?>
