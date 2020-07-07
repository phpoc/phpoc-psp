<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sd_spc.php";

spc_reset(); // reset all smart slaves stacked on P4M-400
spc_sync_baud(115200); // synchronize master to slave baud-rate

while(1)
	sleep(1);

?>
