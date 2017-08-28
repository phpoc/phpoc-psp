<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sd_104.php";

function echo_loop($uart_id)
{
	$rwbuf = "";

	if(!uart_readn($uart_id, $rwbuf, 1))
		return;

	$ascii = bin2int($rwbuf, 0, 1);
	$txbuf = "";

	if(($ascii >= 0x20) && ($ascii <= 0x7e))
		$txbuf = sprintf("UART$uart_id: %c(0x%02x)\r\n", $ascii, $ascii);
	else
		$txbuf = sprintf("UART$uart_id: .(0x%02x)\r\n", $ascii, $ascii);

	uart_write($uart_id, $txbuf);
}

echo "PHPoC example : PBH-104 / UART hex echo\r\n";

uart_setup(0, 115200, "N81N");
uart_setup(1, 115200, "N81N");
uart_setup(2, 115200, "N81N");
uart_setup(3, 115200, "N81N");

uart_write(0, "\r\nUART0 hex echo ready\r\n");
uart_write(1, "\r\nUART1 hex echo ready\r\n");
uart_write(2, "\r\nUART2 hex echo ready\r\n");
uart_write(3, "\r\nUART3 hex echo ready\r\n");

while(1)
{
	echo_loop(0);
	echo_loop(1);
	echo_loop(2);
	echo_loop(3);
}

?>
