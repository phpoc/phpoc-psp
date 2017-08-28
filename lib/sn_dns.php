<?php

// $psp_id sn_dns.php date 20160420

define("RR_A",     1); // Address record
define("RR_NS",    2); // Name Server record
define("RR_CNAME", 5); // Canonical name record
define("RR_SOA",   6); // Start Of Authority record
define("RR_MX",   15); // Mail eXchange record
define("RR_AAAA", 28); // IPv6 Address record

$sn_dns_pid = 0;
$sn_dns_tid = 0;
$sn_dns_ip6 = false;
$sn_dns_server = "";
$sn_dns_query_type = 0;
$sn_dns_timeout_update = 0;
$sn_dns_timeout_query = 0;

function sn_dns_get_tick()
{
	while(($pid = pid_open("/mmap/st9", O_NODIE)) == -EBUSY)
		usleep(500);

	if(!pid_ioctl($pid, "get state"))
		pid_ioctl($pid, "start");

	$tick = pid_ioctl($pid, "get count");
	pid_close($pid);

	return $tick;
}

function sn_dns_encode_name($name)
{
	$enc = "";

	while($name)
	{
		$len = strpos($name, ".");

		if($len === false)
		{
			$enc .= int2bin(strlen($name), 1);
			$enc .= $name;
			break;
		}
		else
		if($len > 0)
		{
			$enc .= int2bin($len, 1);
			$enc .= substr($name, 0, $len);
			$name = substr($name, $len + 1);
		}
		else
		{
			echo "sn_dns: invalid RR name $name\r\n";
			return "";
		}
	}

	$enc .= "\x00";

	return $enc;
}

function sn_dns_decode_name($name, &$rbuf)
{
	$dec = "";

	for(;;)
	{
		$len = bin2int($name, 0, 1);

		if($len & 0xc0)
		{
			$len = bin2int($name, 0, 2, true) & 0x3fff;
			$name = substr($rbuf, $len);
			continue;
		}

		$dec .= substr($name, 1, $len);
		$name = substr($name, 1 + $len);

		if(bin2int($name, 0, 1))
			$dec .= ".";
		else
			return $dec;
	}
}

function sn_dns_skip_name(&$name)
{
	do
	{
		$len = bin2int($name, 0, 1);

		if($len & 0xc0)
		{
			$name = substr($name, 2);
			return;
		}

		if((1 + $len) > strlen($name))
			$len = strlen($name) - 1;

		$name = substr($name, $len + 1);
	}while($len && $name);
}

function dns_setup($udp_id, $server_addr = "", $ip6 = false)
{
	global $sn_dns_pid, $sn_dns_tid;
	global $sn_dns_ip6, $sn_dns_server;

	$sn_dns_ip6 = $ip6;

	if($server_addr && ($server_addr != "0.0.0.0") && ($server_addr != "::0"))
		$sn_dns_server = $server_addr;
	else
		$sn_dns_server = "";

	if(!$sn_dns_pid)
	{
		$sn_dns_pid = pid_open("/mmap/udp$udp_id");
		$sn_dns_tid = rand(0, 65536);

		pid_ioctl($sn_dns_pid, "set dstport 53");
	}

	if($sn_dns_server)
		pid_ioctl($sn_dns_pid, "set dstaddr $sn_dns_server");
}

function sn_dns_check_update_dstaddr()
{
	global $sn_dns_pid, $sn_dns_ip6, $sn_dns_server;
	global $sn_dns_timeout_update;

	if($sn_dns_server)
		return true;

	if($sn_dns_timeout_update > sn_dns_get_tick())
		return true;

	if((int)ini_get("init_net0"))
		$pid_net = pid_open("/mmap/net0");
	else
		$pid_net = pid_open("/mmap/net1");

	if($sn_dns_ip6)
	{
		$nsaddr6 = pid_ioctl($pid_net, "get nsaddr6");

		pid_close($pid_net);

		if($nsaddr6 == "::0")
			return false;

		if($nsaddr6 != pid_ioctl($sn_dns_pid, "get dstaddr"))
		{
			echo "sn_dns: new server address $nsaddr6\r\n";
			pid_ioctl($sn_dns_pid, "set dstaddr $nsaddr6");
		}
	}
	else
	{
		$nsaddr = pid_ioctl($pid_net, "get nsaddr");

		pid_close($pid_net);

		if($nsaddr == "0.0.0.0")
			return false;

		if($nsaddr != pid_ioctl($sn_dns_pid, "get dstaddr"))
		{
			echo "sn_dns: new server address $nsaddr\r\n";
			pid_ioctl($sn_dns_pid, "set dstaddr $nsaddr");
		}
	}

	$sn_dns_timeout_update = sn_dns_get_tick() + 30000;

	return true;
}

function dns_send_query($name, $type, $timeout = 2000)
{
	global $sn_dns_query_type, $sn_dns_timeout_query;
	global $sn_dns_pid, $sn_dns_tid;

	if(!$sn_dns_pid)
		dns_setup(0, "");

	if(!sn_dns_check_update_dstaddr())
	{
		echo "sn_dns: destination unreachable\r\n";
		return 0;
	}

	$sn_dns_query_type = $type;

	$sn_dns_tid++;
	$query  = int2bin($sn_dns_tid, 2, true);
	$query .= hex2bin("0100"); // QR(0), OPCODE(0), AA(0), TC(0), RD(1), RA(0), Z(0), RCODE(0)
	$query .= hex2bin("0001"); // question count 1
	$query .= hex2bin("0000"); // answer count 0
	$query .= hex2bin("0000"); // name server count 0
	$query .= hex2bin("0000"); // additional count 0
	$query .= sn_dns_encode_name($name);
	$query .= int2bin($type, 2, true); // qtype
	$query .= hex2bin("0001"); // class : IN

	$len = pid_sendto($sn_dns_pid, $query);

	if(!$len)
		echo "sn_dns: sendto failed\r\n";

	$sn_dns_timeout_query = sn_dns_get_tick() + $timeout;

	// we should return positive value, even though pid_sendto failed.
	// postive return prevents application from retry send_query befere ARP update
	return strlen($query);
}

function dns_loop()
{
	global $sn_dns_query_type, $sn_dns_timeout_query;
	global $sn_dns_pid, $sn_dns_tid;

	if(!$sn_dns_timeout_query) // query not sent or timed out
		return "";

	if(sn_dns_get_tick() >= $sn_dns_timeout_query)
	{
		echo "sn_dns: lookup timeout\r\n";
		$sn_dns_timeout_query = 0;
		return "";
	}

	if(!pid_ioctl($sn_dns_pid, "get rxlen"))
		return false;

	$sn_dns_timeout_query = 0;

	$rbuf = "";

	pid_recvfrom($sn_dns_pid, $rbuf);

	$questions = bin2int($rbuf, 4, 2, true);
	$answers = bin2int($rbuf, 6, 2, true);

	if(!$answers)
	{
		echo "sn_dns: query failed\r\n";
		return "";
	}

	$pbuf = substr($rbuf, 12); // skip dns header

	for($i = 0; $i < $questions; $i++)
	{
		sn_dns_skip_name($pbuf);  // skip QNAME
		$pbuf = substr($pbuf, 4); // skip QTYPE/QCLASS
	}

	for($i = 0; $i <= $answers; $i++)
	{
		sn_dns_skip_name($pbuf);  // skip NAME

		$rr_type = bin2int($pbuf, 0, 2, true); 
		$pbuf = substr($pbuf, 4); // skip TYPE/CLASS
		$pbuf = substr($pbuf, 4); // skip TTL
		$rd_len = bin2int($pbuf, 0, 2, true);
		$pbuf = substr($pbuf, 2); // skip RDLENGTH

		if($rr_type == $sn_dns_query_type)
		{
			switch($sn_dns_query_type)
			{
				case RR_A:
					return inet_ntop(substr($pbuf, 0, 4));

				case RR_NS:
					return sn_dns_decode_name($pbuf, $rbuf);

				case RR_MX:
					$pbuf = substr($pbuf, 2); // skip PREFERENCE
					return sn_dns_decode_name($pbuf, $rbuf);

				case RR_AAAA:
					return inet_ntop(substr($pbuf, 0, 16));
			}
		}

		$pbuf = substr($pbuf, $rd_len); // skip RDATA
	}

	echo "sn_dns: expected answer not found\r\n";
	return "";
}

function dns_lookup($name, $type, $timeout = 2000)
{
	if(!dns_send_query($name, $type, $timeout))
		return $name;

	while(1)
	{
		$rr = dns_loop();

		if($rr === false)
			usleep(1000);
		else
		{
			if($rr == "")
				return $name;
			else
				return $rr;
		}
	}
}

?>
