<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sn_dns.php";

echo "PHPoC example : get web page from http server\r\n";

$host_name = "example.phpoc.com";
$host_port = 80;

$host_addr = dns_lookup($host_name, RR_A);

if($host_addr == $host_name)
	exit "$host_name : Not Found\r\n";

$tcp0_pid = pid_open("/mmap/tcp0");
pid_bind($tcp0_pid, "", 0);

echo "connect to $host_addr:$host_port...";

pid_connect($tcp0_pid, $host_addr, $host_port);

for(;;)
{
	$state = pid_ioctl($tcp0_pid, "get state");

	if($state == TCP_CLOSED)
	{
		pid_close($tcp0_pid);
		exit "failed\r\n";
	}

	if($state == TCP_CONNECTED)
		break;
}

echo "connected\r\n";

//$http_req  = "GET /request_method/ HTTP/1.1\r\n";
//$http_req  = "GET /request_header/ HTTP/1.1\r\n";
//$http_req  = "GET /asciilogo.txt HTTP/1.1\r\n";
$http_req  = "GET / HTTP/1.1\r\n";
$http_req .= "Host: $host_name\r\n";
$http_req .= "Connection: closed\r\n";
$http_req .= "\r\n\r\n";

pid_send($tcp0_pid, $http_req);

$rbuf = "";

for(;;)
{
	if(pid_recv($tcp0_pid, $rbuf) > 0)
	{
		echo $rbuf;
		continue;
	}

	if(pid_ioctl($tcp0_pid, "get state") == TCP_CLOSED)
		break;
}

echo "\r\nconnection closed\r\n";

pid_close($tcp0_pid);

?>
