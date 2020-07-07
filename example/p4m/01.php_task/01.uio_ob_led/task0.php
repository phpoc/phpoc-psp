<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sd_340.php";

echo "PHPoC example : P4M-400 / UIO / blink on-board LED\r\n";

uio_setup(0, 14, "out high");

while(1)
{
	uio_out(0, 14, LOW);
	usleep(250000);

	uio_out(0, 14, HIGH);
	usleep(250000);
}

?>
