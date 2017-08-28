<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sd_204.php";

echo "PHPoC example : PBH-204 / DIO input test\r\n";

while(1)
{
	for($port = 0; $port < 4; $port++)
	{
		if(dio_in(DI_0 + $port))
			echo "H";
		else
			echo "L";
	}
	echo "\r\n";

	sleep(2);
}

?>
