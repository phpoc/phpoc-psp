<?php

// $psp_id sn_tcp_ws.php date 20160308

$sn_tcp_ws_pid = array( 0, 0, 0, 0, 0 );

function ws_setup($tcp_id, $path, $proto, $port = 0)
{
	global $sn_tcp_ws_pid;

	if(($tcp_id < 0) || ($tcp_id > 4))
		exit("ws_setup: tcp_id out of range $tcp_id\r\n");

	$pid = $sn_tcp_ws_pid[$tcp_id];

	if($pid)
		pid_close($pid);

	$pid = pid_open("/mmap/tcp$tcp_id");

	pid_ioctl($pid, "set api ws");
	pid_ioctl($pid, "set ws path $path");
	pid_ioctl($pid, "set ws proto $proto");

	pid_bind($pid, "", $port);

	pid_listen($pid);

	$sn_tcp_ws_pid[$tcp_id] = $pid;
}

function ws_check_get_pid($tcp_id, $from)
{
	global $sn_tcp_ws_pid;

	if(($tcp_id < 0) || ($tcp_id > 4))
		exit("$from: tcp_id out of range $tcp_id\r\n");

	$pid = $sn_tcp_ws_pid[$tcp_id];

	if(!$pid)
		exit("$from: tcp$tcp_id not initialized\r\n");

	return $pid;
}

function ws_read($tcp_id, &$rbuf, $rlen = MAX_STRING_LEN)
{
	$pid = ws_check_get_pid($tcp_id, "ws_read");

	if(pid_ioctl($pid, "get rxlen"))
		return pid_recv($pid, $rbuf, $rlen);

	if(pid_ioctl($pid, "get state") == TCP_CLOSED)
		pid_listen($pid);

	return 0;
}

function ws_readn($tcp_id, &$rbuf, $rlen)
{
	$pid = ws_check_get_pid($tcp_id, "ws_readn");

	$len = pid_ioctl($pid, "get rxlen");

	if($len && ($len >= $rlen))
		return pid_recv($pid, $rbuf, $rlen);

	if(pid_ioctl($pid, "get state") == TCP_CLOSED)
		pid_listen($pid);

	return 0;
}

// read line terminated by CRLF
function ws_read_line($tcp_id, &$rbuf)
{
	$pid = ws_check_get_pid($tcp_id, "ws_read_line");

	$len = pid_ioctl($pid, "get rxlen \r\n");

	if($len)
		return pid_recv($pid, $rbuf, $len);

	if(pid_ioctl($pid, "get state") == TCP_CLOSED)
		pid_listen($pid);

	return 0;
}

function ws_write($tcp_id, $wbuf, $wlen = MAX_STRING_LEN)
{
	$pid = ws_check_get_pid($tcp_id, "ws_write");

	$state = pid_ioctl($pid, "get state");

	if($state == TCP_CONNECTED)
	{
		if(is_string($wbuf))
			$max_len = strlen($wbuf);
		else
			$max_len = 8;

		if($wlen > $max_len)
			$wlen = $max_len;

		if($wlen && (pid_ioctl($pid, "get txfree") >= $wlen))
			return pid_send($pid, $wbuf, $wlen);
	}
	else
	if($state == TCP_CLOSED)
		pid_listen($pid);

	return 0;
}

function ws_txfree($tcp_id)
{
	$pid = ws_check_get_pid($tcp_id, "ws_txfree");

	$state = pid_ioctl($pid, "get state");

	if($state == TCP_CONNECTED)
		return pid_ioctl($pid, "get txfree");
	else
	if($state == TCP_CLOSED)
		pid_listen($pid);

	return 0;
}

function ws_state($tcp_id)
{
	$pid = ws_check_get_pid($tcp_id, "ws_state");

	$state = pid_ioctl($pid, "get state");

	if($state == TCP_CLOSED)
		pid_listen($pid);

	return $state;
}

?>
