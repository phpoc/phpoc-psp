<?php

// $psp_id sn_ddns.php date 20170705

/*-----------------------------
The list of time servers tested
 - time.apple.com
 - time.bora.net
 - time.nist.gov
 - time.windows.com
-----------------------------*/

include_once "/lib/sn_dns.php";

define("SNTP_STATE_IDLE",               0);
define("SNTP_STATE_RR",                 1);
define("SNTP_STATE_READY",              2);
define("SNTP_STATE_WAIT_ANSWER",        3);

define("SNTP_PORT",                   123);
define("UNIX_TIMSTAMP_OFFSET", 2208988800);

$sn_sntp_pid = 0;
$sn_sntp_udp_id = 1;
$sn_sntp_state = 0;
$sn_sntp_next_tick = 0;
$sn_sntp_dns_query_count = 0;
$sn_sntp_ip6 = false;
$sn_sntp_server_addr = "";
$sn_sntp_server_port = SNTP_PORT;
$sn_sntp_timezone = 0;

function sn_sntp_get_tick()
{
	while(($pid = pid_open("/mmap/st9", O_NODIE)) == -EBUSY)
		usleep(500);
	if(!pid_ioctl($pid, "get state"))
		pid_ioctl($pid, "start");
	$tick = pid_ioctl($pid, "get count");
	pid_close($pid);
	return $tick;
}

function sn_sntp_cleanup()
{
	global $sn_sntp_state;
	global $sn_sntp_pid;

	if($sn_sntp_pid)
	{
		pid_close($sn_sntp_pid);
		$sn_sntp_pid = 0;
	}

	$sn_sntp_state = SNTP_STATE_IDLE;
}

function sntp_setup($udp_id, $sntp_server, $dns_server_addr = "", $ip6 = false)
{
	global $sn_sntp_udp_id;
	global $sn_sntp_ip6;
	global $sn_sntp_server_addr;

	sn_sntp_cleanup();

	$sn_sntp_udp_id = $udp_id;
	$sn_sntp_ip6 = $ip6;

	dns_setup($udp_id, $dns_server_addr, $ip6);

	$sn_sntp_server_addr = $sntp_server;
}

function sntp_timezone($offset = 0)
{
	global $sn_sntp_timezone;

	if(is_int($offset) == true)
	{
		if(($offset > -12) && ($offset < +14))
		{
			$sn_sntp_timezone = $offset * 3600;
			return true;
		}
	}
	echo "sn_sntp: invalid timezone offset!\r\n";
	return false;
}

function sntp_start()
{
	global $sn_sntp_state;
	global $sn_sntp_server_addr;
	global $sn_sntp_dns_query_count;
	global $sn_sntp_ip6;

	if(inet_pton($sn_sntp_server_addr) !== false)
		$sn_sntp_state = SNTP_STATE_READY;
	else
	{
		if($sn_sntp_ip6)
			dns_send_query($sn_sntp_server_addr, RR_AAAA, 500);
		else
			dns_send_query($sn_sntp_server_addr, RR_A, 500);
		$sn_sntp_dns_query_count = 2;
		$sn_sntp_state = SNTP_STATE_RR;
	}
}

function sn_sntp_loop_rr()
{
	global $sn_sntp_state;
	global $sn_sntp_dns_query_count;
	global $sn_sntp_server_addr;
	global $sn_sntp_ip6;

	$rr = dns_loop();

	if($rr === false)
		return false;

	if($rr == "")
	{
		if($sn_sntp_dns_query_count)
		{
			echo "sn_sntp: retry lookup $sn_sntp_server_addr\r\n";

			if($sn_sntp_ip6)
				dns_send_query($sn_sntp_server_addr, RR_AAAA, 1500);
			else
				dns_send_query($sn_sntp_server_addr, RR_A, 1500);

			$sn_sntp_dns_query_count--;
			return false;
		}
		else
		{
			echo "sn_sntp: dns lookup failed\r\n";
			return "";
		}
	}
	else
	{
		$sn_sntp_server_addr = $rr;
		$sn_sntp_state = SNTP_STATE_READY;
		return false;
	}
}

function sn_sntp_send_query()
{
	global $sn_sntp_pid;
	global $sn_sntp_state;
	global $sn_sntp_udp_id;
	global $sn_sntp_server_addr;
	global $sn_sntp_server_port;
	global $sn_sntp_next_tick;

	$sn_sntp_pid = pid_open("/mmap/udp$sn_sntp_udp_id");

	pid_bind($sn_sntp_pid, "",0);
	pid_ioctl($sn_sntp_pid, "set dstport $sn_sntp_server_port");

	if($sn_sntp_server_addr)
		pid_ioctl($sn_sntp_pid, "set dstaddr $sn_sntp_server_addr");

	$sntp_buf = "\x0b" . str_repeat("\x00", 47);
	$sn_sntp_next_tick = sn_sntp_get_tick() + 5000;

	echo "sn_sntp: getting timestamp from $sn_sntp_server_addr ...";
	if(pid_sendto($sn_sntp_pid, $sntp_buf))
		$sn_sntp_state = SNTP_STATE_WAIT_ANSWER;
	else
		sn_sntp_cleanup();
}

function sntp_loop()
{
	global $sn_sntp_pid;
	global $sn_sntp_state;
	global $sn_sntp_next_tick;
	global $sn_sntp_timezone;

	$rbuf = "";

	if($sn_sntp_state == SNTP_STATE_IDLE)
		return -1;

	elseif($sn_sntp_state == SNTP_STATE_RR)
	{
		if(sn_sntp_loop_rr() === "")
		{
			sn_sntp_cleanup();
			return -1;
		}
		else
			return false;
	}

	elseif($sn_sntp_state == SNTP_STATE_READY)
	{
		sn_sntp_send_query();
		return false;
	}

	elseif($sn_sntp_state == SNTP_STATE_WAIT_ANSWER)
	{
		if(sn_sntp_get_tick() >= $sn_sntp_next_tick)
		{
			echo "sn_sntp: receive timeout\r\n";
			sn_sntp_cleanup();
			return -1;
		}

		if(!pid_ioctl($sn_sntp_pid, "get rxlen"))
			return false;

		pid_recvfrom($sn_sntp_pid, $rbuf);
		$transmit_timestamp = substr($rbuf, -8, 4);

		if($transmit_timestamp)
			echo "done\r\n";

		sn_sntp_cleanup();

		$timestamp = bin2int($transmit_timestamp, 0, 4, true);

		if($timestamp == 0)
			return -1;

	 	return $timestamp;
	}
	else
	{
		echo "sn_sntp: unknown state\r\n";
		return -1;
	}
}

function sntp_query_timestamp()
{
	sntp_start();
	
	while(1)
	{
		$timestamp = sntp_loop();

		if($timestamp === false)
			usleep(1000);
		else
			return $timestamp;
	}
}


function sntp_time()
{
	global $sn_sntp_timezone;

	$timestamp = sntp_query_timestamp();

	if($timestamp !== -1)
	{
		$timestamp -= UNIX_TIMSTAMP_OFFSET;
		$timestamp += $sn_sntp_timezone;
		return $timestamp;
	}
	else
		return false;
}

?>
