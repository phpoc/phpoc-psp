<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sd_204.php";

echo "PHPoC example : PBH-204 / route DIO input to output\r\n";

while(1)
{
	for($port = 0; $port < 4; $port++)
	{
		if(dio_in(DI_0 + $port))
			dio_out(DO_0 + $port, HIGH);
		else
			dio_out(DO_0 + $port, LOW);
	}

	usleep(100000);
}

?>
