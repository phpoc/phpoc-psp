<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sd_204.php";

echo "PHPoC example : PBH-204 / UART hex echo\r\n";

uart_setup(0, 115200, "N81N");

uart_write(0, "\r\nUART hex echo ready\r\n");

$rwbuf = "";
$txbuf = "";

while(1)
{
	if(uart_readn(0, $rwbuf, 1))
	{
		$ascii = bin2int($rwbuf, 0, 1);

		if(($ascii >= 0x20) && ($ascii <= 0x7e))
			$txbuf = sprintf("%c(0x%02x)\r\n", $ascii, $ascii);
		else
			$txbuf = sprintf(".(0x%02x)\r\n", $ascii, $ascii);

		uart_write(0, $txbuf);
	}
}

?>
