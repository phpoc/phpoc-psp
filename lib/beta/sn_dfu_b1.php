<?php

// $psp_id sn_dfu.php date 20191111

include_once "/lib/sn_dns.php";

define("DFU_STATE_IDLE",     0);
define("DFU_STATE_CONNECT",  1);
define("DFU_STATE_REQUEST",  2);
define("DFU_STATE_DOWNLOAD", 3);
define("DFU_STATE_VERIFY",   4);
define("DFU_STATE_UPDATE",   5);

function sn_dfu_find_header($header, $field_name)
{
	$pos = strpos($header, "\r\n");

	if($pos === false)
		return "";

	$status_line = substr($header, 0, $pos);

	switch(strtoupper($field_name))
	{
		case "STATUS-LINE":
			return $status_line;
		case "HTTP-VERSION":
			return substr($status_line, 0, 8);
		case "STATUS-CODE":
			return substr($status_line, 9, 3);
		case "REASON-PHRASE":
			return substr($status_line, 13);
	}

	$header_array = explode("\r\n", $header);
	$header_count = count($header_array) - 1;

	for($i = 0; $i < $header_count; $i++)
	{
		$field = $header_array[$i];
		$pos = strpos($field, ":");

		if($pos === false)
			continue;

		if(strtoupper(substr($field, 0, $pos)) == strtoupper($field_name))
			return ltrim(rtrim(substr($field, $pos + 1)));
	}

	return false;
}

function sn_dfu_wait_rxlen($tcp_pid, $rxlen)
{
	for(;;)
	{
		$len = pid_ioctl($tcp_pid, "get rxlen");

		if($len >= $rxlen)
			return $len;

		if(pid_ioctl($tcp_pid, "get state") < TCP_CONNECTED)
			return 0;

		usleep(10000);
	}
}

function sn_dfu_connect($tcp_id, $host_name, $port)
{
	if(inet_pton($host_name))
		$host_addr = $host_name;
	else
	{
		$host_addr = dns_lookup($host_name, RR_A);

		if($host_addr == $host_name)
		{
			echo "$host_name : Not Found\r\n";
			return 0;
		}
	}

	$tcp_pid = pid_open("/mmap/tcp$tcp_id");

	pid_bind($tcp_pid, "", 0);
	pid_ioctl($tcp_pid, "set api tls");
	pid_ioctl($tcp_pid, "set tls method client");

	if(PHP_VERSION_ID >= 20200)
	{
		pid_ioctl($tcp_pid, "set tls extension sni $host_name");
		pid_ioctl($tcp_pid, "set tls vsni 1");
		pid_ioctl($tcp_pid, "set tls vchain 1");
	}

	echo "dfu: connect to $host_addr:$port...";

	pid_connect($tcp_pid, $host_addr, $port);

	for(;;)
	{
		$state = pid_ioctl($tcp_pid, "get state");

		if($state == SSL_CLOSED)
		{
			pid_close($tcp_pid);
			echo "failed\r\n";
			return 0;
		}

		if($state == SSL_CONNECTED)
			break;
	}

	echo "connected\r\n";

	return $tcp_pid;
}

function sn_dfu_request($tcp_pid, $pkgware_path, $host_name)
{
	$version = system("uname -v");

	$http_req  = "GET $pkgware_path HTTP/1.1\r\n";
	$http_req .= "Host: $host_name\r\n";
	$http_req .= "User-Agent: sn_dfu.php (PHPoC $version)\r\n";
	$http_req .= "Connection: closed\r\n";
	$http_req .= "\r\n\r\n";

	pid_send($tcp_pid, $http_req);

	$resp_head = "";

	for(;;)
	{
		$len = pid_ioctl($tcp_pid, "get rxlen \r\n\r\n");

		if($len)
		{
			pid_recv($tcp_pid, $resp_head);
			break;
		}

		if(pid_ioctl($tcp_pid, "get state") < TCP_CONNECTED)
		{
			echo "dfu: connection closed\r\n";
			return 0;
		}
	}
	
	$status = (int)sn_dfu_find_header($resp_head, "Status-Code");

	if($status != 200)
	{
		echo "dfu: unexpected status $status\r\n";
		return 0;
	}

	return (int)sn_dfu_find_header($resp_head, "Content-Length");
}

function sn_dfu_update_ezfs($tcp_pid, $ezfs_len)
{
	$rbuf = "";
	$rx_count = 0;

	system("dfu unlock a5c3");

	echo "dfu: erasing ezfs ... ";
	system("dfu erase ezfs all");
	echo "done\r\n";

	echo "dfu: download ezfs ";

	while($ezfs_len > 0)
	{
		if(!sn_dfu_wait_rxlen($tcp_pid, 512))
			return 0;

		pid_recv($tcp_pid, $rbuf, 512);

		system("dfu write ezfs %1", $rbuf);

		if(!($rx_count % 65536))
			echo $rx_count / 1024, " ";

		$ezfs_len -= 512;
		$rx_count += 512;
	}

	echo $rx_count / 1024, "\r\n";

	return $rx_count;
}

function sn_dfu_update_firm($tcp_pid, $firm_len)
{
	$rbuf = "";
	$rx_count = 0;

	system("dfu unlock a5c3");

	echo "dfu: erasing firmware ... ";
	system("dfu erase firm all");
	echo "done\r\n";

	echo "dfu: download firmware ";

	while($firm_len > 0)
	{
		if(!($len = sn_dfu_wait_rxlen($tcp_pid, 1)))
			return 0;

		if($len > 512)
			$len = 512;

		pid_recv($tcp_pid, $rbuf, $len);

		system("dfu write firm %1", $rbuf);

		if(!($rx_count % 65536))
			echo $rx_count / 1024, " ";

		$firm_len -= $len;
		$rx_count += $len;
	}

	echo $rx_count / 1024, "\r\n";

	return $rx_count;
}

function sn_dfu_verify_pkgware()
{
	if(system("dfu test firm name") == "202")
		echo "dfu: firmware name test passed\r\n";
	else
	{
		echo "dfu: firmware name test failed\r\n";
		return 0;
	}

	if(system("dfu test firm sign") == "202")
		echo "dfu: firmware publisher signature test passed\r\n";
	else
	{
		echo "dfu: firmware publisher signature test failed\r\n";
		return 0;
	}

	if(system("dfu test ezfs name") == "202")
		echo "dfu: ezfs package/pkgware name test passed\r\n";
	else
	{
		echo "dfu: ezfs package/pkgware name test failed\r\n";
		return 0;
	}

	if(system("dfu test ezfs sign") == "202")
		echo "dfu: ezfs publisher signature test passed\r\n";
	else
	{
		echo "dfu: ezfs publisher signature test failed\r\n";
		return 0;
	}

	return 202;
}

function dfu_download_pkgware($tcp_id, $host_name, $port, $pkgware_path)
{
	if(!($tcp_pid = sn_dfu_connect($tcp_id, $host_name, $port)))
		return DFU_STATE_CONNECT;

	if(!($pkgware_len = sn_dfu_request($tcp_pid, $pkgware_path, $host_name)))
	{
		pid_close($tcp_pid);
		return DFU_STATE_REQUEST;
	}

	$ezfs_head = "";

	if(!sn_dfu_wait_rxlen($tcp_pid, 16))
	{
		pid_close($tcp_pid);
		return DFU_STATE_DOWNLOAD;
	}

	pid_peek($tcp_pid, $ezfs_head, 16);

	$ezfs_len = bin2int($ezfs_head,  4, 2) * 1024;
	$firm_len = $pkgware_len - $ezfs_len;

	if(!sn_dfu_update_ezfs($tcp_pid, $ezfs_len))
	{
		echo "dfu: connection closed\r\n";
		pid_close($tcp_pid);
		return DFU_STATE_DOWNLOAD;
	}

	if(!sn_dfu_update_firm($tcp_pid, $firm_len))
	{
		echo "dfu: connection closed\r\n";
		pid_close($tcp_pid);
		return DFU_STATE_DOWNLOAD;
	}

	pid_close($tcp_pid);

	$version_in_firm = system("dfu get firm version");
	$ezfs_info = system("dfu get ezfs info");
	$ezfs_info = explode(",", $ezfs_info);
	$version_in_info = trim($ezfs_info[2]);

	if($version_in_firm == $version_in_info)
		echo "dfu: firmware version test passed\r\n";
	else
	{
		echo "dfu: firmware version mismatch\r\n";
		return DFU_STATE_VERIFY;
	}

	if(!sn_dfu_verify_pkgware())
		return DFU_STATE_VERIFY;

	return DFU_STATE_UPDATE;
}

function dfu_update_pkgware($wait_ms = 500)
{
	return system("dfu update firm ezfs cc55 $wait_ms");
}

?>
