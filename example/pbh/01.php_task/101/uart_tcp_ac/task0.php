<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sd_101.php";
include_once "/lib/sn_tcp_ac.php";

echo "PHPoC example : PBH-101 / convert UART to TCP\r\n";

uart_setup(0, 115200, "N81N");
tcp_server(0, 14700);

$rwbuf = "";

while(1)
{
	if(tcp_state(0) == TCP_CONNECTED)
		led_out(LED_A, LOW);
	else
		led_out(LED_A, HIGH);

	$len = uart_read(0, $rwbuf, tcp_txfree(0));
	if($len > 0)
		tcp_write(0, $rwbuf);

	$len = tcp_read(0, $rwbuf, uart_txfree(0));
	if($len > 0)
		uart_write(0, $rwbuf);
}

?>
