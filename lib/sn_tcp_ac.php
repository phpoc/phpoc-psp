<?php

// $psp_id sn_tcp_ac.php date 20160308

$sn_tcp_ac_pid = array( 0, 0, 0, 0, 0 );
$sn_tcp_ac_addr = array( "", "", "", "", "" );
$sn_tcp_ac_port = array( 0, 0, 0, 0, 0 );

function sn_tcp_ac_start($tcp_id)
{
	global $sn_tcp_ac_pid, $sn_tcp_ac_addr, $sn_tcp_ac_port;

	$pid = $sn_tcp_ac_pid[$tcp_id];

	$rbuf = "";
	while(pid_recv($pid, $rbuf) > 0) // flush rx buffer
		;

	if($sn_tcp_ac_addr[$tcp_id] == "")
		pid_listen($pid);
	else
		pid_connect($pid, $sn_tcp_ac_addr[$tcp_id], $sn_tcp_ac_port[$tcp_id]);
}

function tcp_client($tcp_id, $addr, $port)
{
	global $sn_tcp_ac_pid, $sn_tcp_ac_addr, $sn_tcp_ac_port;

	if(($tcp_id < 0) || ($tcp_id > 4))
		exit("tcp_client: tcp_id out of range $tcp_id\r\n");

	$pid = $sn_tcp_ac_pid[$tcp_id];

	if($pid)
		pid_close($pid);

	$pid = pid_open("/mmap/tcp$tcp_id");

	$sn_tcp_ac_pid[$tcp_id] = $pid;
	$sn_tcp_ac_addr[$tcp_id] = $addr;
	$sn_tcp_ac_port[$tcp_id] = $port;

	sn_tcp_ac_start($tcp_id);
}

function tcp_server($tcp_id, $port)
{
	global $sn_tcp_ac_pid, $sn_tcp_ac_addr, $sn_tcp_ac_port;

	if(($tcp_id < 0) || ($tcp_id > 4))
		exit("tcp_server: tcp_id out of range $tcp_id\r\n");

	$pid = $sn_tcp_ac_pid[$tcp_id];

	if($pid)
		pid_close($pid);

	$pid = pid_open("/mmap/tcp$tcp_id");

	pid_bind($pid, "", $port);

	$sn_tcp_ac_pid[$tcp_id] = $pid;
	$sn_tcp_ac_addr[$tcp_id] = "";
	$sn_tcp_ac_port[$tcp_id] = $port;

	sn_tcp_ac_start($tcp_id);
}

function tcp_check_get_pid($tcp_id, $from)
{
	global $sn_tcp_ac_pid;

	if(($tcp_id < 0) || ($tcp_id > 4))
		exit("$from: tcp_id out of range $tcp_id\r\n");

	$pid = $sn_tcp_ac_pid[$tcp_id];

	if(!$pid)
		exit "$from: tcp$tcp_id not initialized\r\n";

	return $pid;
}

function tcp_read($tcp_id, &$rbuf, $rlen = MAX_STRING_LEN)
{
	global $sn_tcp_ac_pid;

	$pid = tcp_check_get_pid($tcp_id, "tcp_read");

	if(pid_ioctl($pid, "get rxlen"))
		return pid_recv($pid, $rbuf, $rlen);

	if(pid_ioctl($pid, "get state") == TCP_CLOSED)
		sn_tcp_ac_start($tcp_id);

	return 0;
}

function tcp_readn($tcp_id, &$rbuf, $rlen)
{
	global $sn_tcp_ac_pid;

	$pid = tcp_check_get_pid($tcp_id, "tcp_readn");

	$len = pid_ioctl($pid, "get rxlen");

	if($len && ($len >= $rlen))
		return pid_recv($pid, $rbuf, $rlen);

	if(pid_ioctl($pid, "get state") == TCP_CLOSED)
		sn_tcp_ac_start($tcp_id);

	return 0;
}

function tcp_write($tcp_id, $wbuf, $wlen = MAX_STRING_LEN)
{
	global $sn_tcp_ac_pid;

	$pid = tcp_check_get_pid($tcp_id, "tcp_write");

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
		sn_tcp_ac_start($tcp_id);

	return 0;
}

function tcp_txfree($tcp_id)
{
	global $sn_tcp_ac_pid;

	$pid = tcp_check_get_pid($tcp_id, "tcp_txfree");

	$state = pid_ioctl($pid, "get state");

	if($state == TCP_CONNECTED)
		return pid_ioctl($pid, "get txfree");
	else
	if($state == TCP_CLOSED)
		sn_tcp_ac_start($tcp_id);

	return 0;
}

function tcp_state($tcp_id)
{
	global $sn_tcp_ac_pid;

	$pid = tcp_check_get_pid($tcp_id, "tcp_state");

	return pid_ioctl($pid, "get state");
}

?>
