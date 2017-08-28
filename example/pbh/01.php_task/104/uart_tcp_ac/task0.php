<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sd_104.php";
include_once "/lib/sn_tcp_ac.php";

function uart_tcp_loop($id)
{
	if(tcp_state($id) == TCP_CONNECTED)
		led_out(LED_A + $id, LOW);
	else
		led_out(LED_A + $id, HIGH);

	$rwbuf = "";

	$len = uart_read($id, $rwbuf, tcp_txfree($id));
	if($len > 0)
		tcp_write($id, $rwbuf);

	$len = tcp_read($id, $rwbuf, uart_txfree($id));
	if($len > 0)
		uart_write($id, $rwbuf);
}

echo "PHPoC example : PBH-104 / convert UART to TCP\r\n";

uart_setup(0, 115200, "N81N");
uart_setup(1, 115200, "N81N");
uart_setup(2, 115200, "N81N");
uart_setup(3, 115200, "N81N");

tcp_server(0, 14700);
tcp_server(1, 14701);
tcp_server(2, 14702);
tcp_server(3, 14703);

while(1)
{
	uart_tcp_loop(0);
	uart_tcp_loop(1);
	uart_tcp_loop(2);
	uart_tcp_loop(3);
}

?>
