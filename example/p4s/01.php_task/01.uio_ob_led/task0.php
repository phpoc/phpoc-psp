<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sd_340.php";

echo "PHPoC example : P4S-34X / UIO / blink on-board LED\r\n";

uio_setup(0, 30, "out high");
uio_setup(0, 31, "out high");

while(1)
{
	uio_out(0, 30, LOW);
	uio_out(0, 31, HIGH);
	usleep(250000);

	uio_out(0, 30, HIGH);
	uio_out(0, 31, LOW);
	usleep(250000);
}

?>
