<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

// [PBH-204 to PC/Server]
// 'i' - input port 0 is LOW
// 'j' - input port 1 is LOW
// 'k' - input port 2 is LOW
// 'l' - input port 3 is LOW
// 'I' - input port 0 is HIGH
// 'J' - input port 1 is HIGH
// 'K' - input port 2 is HIGH
// 'L' - input port 3 is HIGH
//
// [PC/Server to PBH-204]
// 'a' - set ouput port 0 LOW
// 'b' - set ouput port 1 LOW
// 'c' - set ouput port 2 LOW
// 'd' - set ouput port 3 LOW
// 'A' - set ouput port 0 HIGH
// 'B' - set ouput port 1 HIGH
// 'C' - set ouput port 2 HIGH
// 'D' - set ouput port 3 HIGH

include_once "/lib/sd_204.php";
include_once "/lib/sn_tcp_ac.php";

echo "PHPoC example : PBH-204 / DIO control over TCP\r\n";

tcp_server(0, 14700);

$rbuf = "";
$in_data = array( LOW, LOW, LOW, LOW );

while(1)
{
	$len = tcp_readn(0, $rbuf, 1);

	if($len)
	{
		$ascii = bin2int($rbuf, 0, 1);

		if(($ascii >= 0x61) && ($ascii <= 0x64))
			dio_out(DO_0 + $ascii - 0x61, LOW); // 'a', 'b', 'c', 'd'
		else
		if(($ascii >= 0x41) && ($ascii <= 0x44))
			dio_out(DO_0 + $ascii - 0x41, HIGH); // 'A', 'B', 'C', 'D'
	}

	for($port = 0; $port < 4; $port++)
	{
		if($in_data[$port] != dio_in(DI_0 + $port))
		{
			$in_data[$port] = dio_in(DI_0 + $port);

			if($in_data[$port])
				tcp_write(0, int2bin(0x49 + $port, 1)); // 'I', 'J', 'K', 'L'
			else
				tcp_write(0, int2bin(0x69 + $port, 1)); // 'i', 'j', 'k', 'l'
		}
	}
}

?>
