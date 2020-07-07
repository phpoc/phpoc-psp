<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sd_340.php";

define("OUT_PIN", 0);

echo "PHPoC example : P4M-400 / UIO / Catalex Buzzer\r\n";

uio_setup(0, OUT_PIN, "out low");

while(1)
{
	uio_out(0, OUT_PIN, HIGH);
	usleep(100000);

	uio_out(0, OUT_PIN, LOW);
	usleep(900000);
}

?>
