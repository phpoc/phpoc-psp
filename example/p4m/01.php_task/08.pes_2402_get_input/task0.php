<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sd_spc.php";

define("IO_IN_SID", 1);

echo "PHPoC example : P4M-400 / PES-2402 / get input\r\n";

spc_reset(); // reset all smart slaves stacked on P4M-400
spc_sync_baud(115200); // synchronize master to slave baud-rate

printf("%d smart expansion(s) found\r\n", spc_scan(1, 14, true));

$last_input = array(1, 1, 1, 1);

while(1)
{
	for($port = 0; $port < 4; $port++)
		if($last_input[$port] != (int)spc_request_dev(IO_IN_SID, "get $port input"))
			break;

	if($port < 4)
	{
		$last_input[0] = (int)spc_request_dev(IO_IN_SID, "get 0 input");
		$last_input[1] = (int)spc_request_dev(IO_IN_SID, "get 1 input");
		$last_input[2] = (int)spc_request_dev(IO_IN_SID, "get 2 input");
		$last_input[3] = (int)spc_request_dev(IO_IN_SID, "get 3 input");

		echo $last_input[0], " ";
		echo $last_input[1], " ";
		echo $last_input[2], " ";
		echo $last_input[3], "\r\n";
	}

	usleep(1000);
}

?>
