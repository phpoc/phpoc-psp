<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sd_340.php";

define("IN_PIN", 0);

echo "PHPoC example : P4M-400 / UIO / Catalex Touch Sensor\r\n";

uio_setup(0, IN_PIN, "in");

$last_touch = 0;

while(1)
{
	$touch = uio_in(0, IN_PIN);

	if($touch != $last_touch)
	{
		if($touch)
			echo "touch ON\r\n";
		else
			echo "touch OFF\r\n";

		$last_touch = $touch;
	}
}

?>
