<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sd_340.php";

uart_setup(0, 9600, "N81N");

while(1)
{
	uart_write(0, "hello, world!\r\n");
	sleep(1);
}

?>
