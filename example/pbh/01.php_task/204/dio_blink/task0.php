<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sd_204.php";

echo "PHPoC example : PBH-204 / blink DIO output 0\r\n";

while(1)
{
	dio_out(DO_0, LOW);
	sleep(1);

	dio_out(DO_0, HIGH);
	sleep(1);
}

?>
