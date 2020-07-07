<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

function echo_loop($port)
{
	global $sn_tcpn_pid;

	$tcp_pid = $sn_tcpn_pid[$port];

	$state = pid_ioctl($tcp_pid, "get state");

	if($state == TCP_CLOSED)
	{
		pid_listen($tcp_pid);
		return TCP_LISTEN;
	}

	if($state != TCP_CONNECTED)
		return;

	$len = pid_ioctl($tcp_pid, "get txfree");
	if(!$len)
		return;

	$rwbuf = "";

	$len = pid_recv($tcp_pid, $rwbuf, $len);
	if($len)
	{
		pid_send($tcp_pid, $rwbuf);
		echo $rwbuf;
	}
}

function echo_loop_rand_len($port)
{
	global $sn_tcpn_pid;

	$tcp_pid = $sn_tcpn_pid[$port];

	$state = pid_ioctl($tcp_pid, "get state");

	if($state == TCP_CLOSED)
	{
		pid_listen($tcp_pid);
		return TCP_LISTEN;
	}

	if($state != TCP_CONNECTED)
		return;

	$len = pid_ioctl($tcp_pid, "get txfree");
	if(!$len)
		return;

	$len = rand() % $len;

	if(!$len)
		$len = 1;

	$rwbuf = "";

	$len = pid_recv($tcp_pid, $rwbuf, $len);
	if($len)
	{
		pid_send($tcp_pid, $rwbuf);
		echo $rwbuf;
	}
}

echo "PHPoC example : multi-port TCP echo\r\n";

$sn_tcpn_pid = array(0, 0, 0, 0);

for($i = 0; $i < 4; $i++)
{
	$sn_tcpn_pid[$i] = pid_open("/mmap/tcp$i");
	pid_bind($sn_tcpn_pid[$i], "", 14700 + $i);
}

for(;;)
{
	/*
	echo_loop(0);
	echo_loop(1);
	echo_loop(2);
	echo_loop(3);
	*/
	echo_loop_rand_len(0);
	echo_loop_rand_len(1);
	echo_loop_rand_len(2);
	echo_loop_rand_len(3);
}

?>
