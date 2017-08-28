<?php

// $psp_id sn_smtp.php date 20170329

include_once "/lib/sn_dns.php";

define("SMTP_STATE_IDLE",     0);
define("SMTP_STATE_RR_MX",    1);
define("SMTP_STATE_RR_A",     2);
define("SMTP_STATE_CONNECT",  3);
define("SMTP_STATE_220",      4);
define("SMTP_STATE_HELO",     5);
define("SMTP_STATE_FROM",     6);
define("SMTP_STATE_RCPT",     7);
define("SMTP_STATE_DATA",     8);
define("SMTP_STATE_BODY",     9);
define("SMTP_STATE_QUIT",    10);

$sn_smtp_state = 0;
$sn_smtp_tcp_id = 0;
$sn_smtp_tcp_pid = 0;
$sn_smtp_ip6 = false;
$sn_smtp_next_tick = 0;
$sn_smtp_hostname = "";
$sn_smtp_helo_host = "";
$sn_smtp_query_name = "";
$sn_smtp_query_count = 0;
$sn_smtp_from_email = "";
$sn_smtp_from_name = "";
$sn_smtp_rcpt_email = "";
$sn_smtp_rcpt_name = "";
$sn_smtp_subject = "";
$sn_smtp_body = "";

function sn_smtp_get_tick()
{
	while(($pid = pid_open("/mmap/st9", O_NODIE)) == -EBUSY)
		usleep(500);

	if(!pid_ioctl($pid, "get state"))
		pid_ioctl($pid, "start");

	$tick = pid_ioctl($pid, "get count");
	pid_close($pid);

	return $tick;
}

function sn_smtp_cleanup()
{
	global $sn_smtp_state, $sn_smtp_tcp_pid;

	if($sn_smtp_tcp_pid)
	{
		pid_close($sn_smtp_tcp_pid);
		$sn_smtp_tcp_pid = 0;
	}

	$sn_smtp_state = SMTP_STATE_IDLE;
}

function sn_smtp_update_helo_host()
{
	global $sn_smtp_ip6;
	global $sn_smtp_hostname, $sn_smtp_helo_host;

	if($sn_smtp_hostname)
		return;

	if((int)ini_get("init_net0"))
		$pid_net = pid_open("/mmap/net0");
	else
		$pid_net = pid_open("/mmap/net1");

	if($sn_smtp_ip6)
		$sn_smtp_helo_host = pid_ioctl($pid_net, "get ipaddr6");
	else
		$sn_smtp_helo_host = pid_ioctl($pid_net, "get ipaddr");

	pid_close($pid_net);
}

function smtp_setup($udp_id, $tcp_id, $dns_server = "", $ip6 = false)
{
	global $sn_smtp_tcp_id, $sn_smtp_ip6;

	sn_smtp_cleanup();

	$sn_smtp_tcp_id = $tcp_id;
	$sn_smtp_ip6 = $ip6;

	dns_setup($udp_id, $dns_server, $sn_smtp_ip6);
}

function smtp_hostname($hostname)
{
	global $sn_smtp_hostname, $sn_smtp_helo_host;

	if($hostname)
	{
		$sn_smtp_hostname = $hostname;
		$sn_smtp_helo_host = $hostname;
	}
}

function smtp_account($email, $name)
{
	global $sn_smtp_hostname, $sn_smtp_helo_host;
	global $sn_smtp_from_email, $sn_smtp_from_name;

	if($sn_smtp_hostname == "")
		sn_smtp_update_helo_host();

	if($email == "")
	{
		if(inet_pton($sn_smtp_helo_host) === false)
			$sn_smtp_from_email = "PHPoC@$sn_smtp_helo_host";
		else
			$sn_smtp_from_email = "PHPoC@[$sn_smtp_helo_host]";
	}
	else
		$sn_smtp_from_email = $email;

	if($name == "")
		$sn_smtp_from_name = "PHPoC";
	else
		$sn_smtp_from_name = $name;
}

function sn_smtp_loop_rr()
{
	global $sn_smtp_state, $sn_smtp_tcp_id, $sn_smtp_tcp_pid;
	global $sn_smtp_ip6;
	global $sn_smtp_query_name, $sn_smtp_query_count;

	$rr = dns_loop();

	if($rr === false)
		return false;

	if($rr == "")
	{
		if($sn_smtp_query_count)
		{
			echo "sn_smtp: retry lookup $sn_smtp_query_name\r\n";

			if($sn_smtp_state == SMTP_STATE_RR_MX)
				dns_send_query($sn_smtp_query_name, RR_MX, 1000);
			else
			{
				if($sn_smtp_ip6)
					dns_send_query($sn_smtp_query_name, RR_AAAA, 1000);
				else
					dns_send_query($sn_smtp_query_name, RR_A, 1000);
			}

			$sn_smtp_query_count--;
			return false;
		}
		else
		{
			echo "sn_smtp: lookup failed\r\n";
			return "";
		}
	}

	if($sn_smtp_state == SMTP_STATE_RR_MX)
	{
		echo "sn_smtp: MX $rr\r\n";

		if($sn_smtp_ip6)
			dns_send_query($rr, RR_AAAA, 500);
		else
			dns_send_query($rr, RR_A, 500);

		$sn_smtp_query_name = $rr;
		$sn_smtp_query_count = 1;

		$sn_smtp_state = SMTP_STATE_RR_A;
	}
	else
	{
		$sn_smtp_tcp_pid = pid_open("/mmap/tcp$sn_smtp_tcp_id");

		echo "sn_smtp: connect to $rr:25...";
		pid_connect($sn_smtp_tcp_pid, $rr, 25);
		$sn_smtp_state = SMTP_STATE_CONNECT;
	}

	return false;
}

function smtp_loop()
{
	global $sn_smtp_state, $sn_smtp_tcp_pid, $sn_smtp_next_tick;
	global $sn_smtp_helo_host; 
	global $sn_smtp_from_email, $sn_smtp_from_name;
	global $sn_smtp_rcpt_email, $sn_smtp_rcpt_name;
	global $sn_smtp_subject, $sn_smtp_body;

	if($sn_smtp_state == SMTP_STATE_IDLE)
		return "";

	$rbuf = "";

	if($sn_smtp_state > SMTP_STATE_CONNECT)
	{
		$len = pid_ioctl($sn_smtp_tcp_pid, "get rxlen \x0d\x0a");

		if(!$len)
		{
			$state = pid_ioctl($sn_smtp_tcp_pid, "get state");

			if($state != TCP_CONNECTED)
			{
				echo "sn_smtp: connection closed\r\n";

				if($sn_smtp_state == SMTP_STATE_QUIT)
					$rbuf = "221";
				else
					$rbuf = "";

				sn_smtp_cleanup();
				return $rbuf;
			}

			if(sn_smtp_get_tick() >= $sn_smtp_next_tick)
			{
				echo "sn_smtp: sending email timed out\r\n";
				sn_smtp_cleanup();
				return "";
			}

			return false;
		}

		pid_recv($sn_smtp_tcp_pid, $rbuf, $len);
		echo "<< ", $rbuf;

		if((substr($rbuf, 0, 1) == "4") || (substr($rbuf, 0, 1) == "5"))
		{
			for(;;)
			{
				$len = pid_ioctl($sn_smtp_tcp_pid, "get rxlen \x0d\x0a");
				if(!$len)
					break;
				pid_recv($sn_smtp_tcp_pid, $rbuf, $len);
				echo "<< ", $rbuf;
			}

			sn_smtp_cleanup();
			return substr($rbuf, 0, 3);
		}
	}

	switch($sn_smtp_state)
	{
		case SMTP_STATE_RR_MX:
		case SMTP_STATE_RR_A:
			if(sn_smtp_loop_rr() === "")
			{
				sn_smtp_cleanup();
				return "";
			}
			break;

		case SMTP_STATE_CONNECT:
			$state = pid_ioctl($sn_smtp_tcp_pid, "get state");

			if($state == TCP_CONNECTED)
			{
				echo "ok\r\n";
				$sn_smtp_next_tick = sn_smtp_get_tick() + 30000; // 30 seconds
				$sn_smtp_state = SMTP_STATE_220;
			}
			else
			if($state == TCP_CLOSED)
			{
				echo "failed\r\n";
				sn_smtp_cleanup();
				return "";
			}
			break;

		case SMTP_STATE_220:
			if(substr($rbuf, 0, 3) == "220")
			{
				$msg = "HELO $sn_smtp_helo_host\r\n";
				pid_send($sn_smtp_tcp_pid, $msg);
				echo ">> ", $msg;

				$sn_smtp_state = SMTP_STATE_HELO;
			}
			break;

		case SMTP_STATE_HELO:
			if(substr($rbuf, 0, 3) == "250")
			{
				$msg = "MAIL FROM:<$sn_smtp_from_email>\r\n";
				pid_send($sn_smtp_tcp_pid, $msg);
				echo ">> ", $msg;

				$sn_smtp_state = SMTP_STATE_FROM;
			}
			break;

		case SMTP_STATE_FROM:
			if(substr($rbuf, 0, 3) == "250")
			{
				$msg = "RCPT TO:<$sn_smtp_rcpt_email>\r\n";
				pid_send($sn_smtp_tcp_pid, $msg);
				echo ">> ", $msg;

				$sn_smtp_state = SMTP_STATE_RCPT;
			}
			break;

		case SMTP_STATE_RCPT:
			if(substr($rbuf, 0, 3) == "250")
			{
				$msg = "DATA\r\n";
				pid_send($sn_smtp_tcp_pid, $msg);
				echo ">> ", $msg;

				$sn_smtp_state = SMTP_STATE_DATA;
			}
			break;

		case SMTP_STATE_DATA:
			if(substr($rbuf, 0, 3) == "354")
			{
				$msg  = "From: \"$sn_smtp_from_name\" <$sn_smtp_from_email>\r\n";
				$msg .= "To: \"$sn_smtp_rcpt_name\" <$sn_smtp_rcpt_email>\r\n";
				$msg .= "Subject: $sn_smtp_subject\r\n\r\n";

				if(strlen($msg) + strlen($sn_smtp_body) + 5 >= MAX_STRING_LEN)
				{
					echo "sn_smtp: body too long\r\n";
					sn_smtp_cleanup();
					return "";
				}

				$msg .= $sn_smtp_body;
				$msg .= "\r\n.\r\n";

				pid_send($sn_smtp_tcp_pid, $msg);
				echo ">> $msg";

				$sn_smtp_state = SMTP_STATE_BODY;
			}
			break;

		case SMTP_STATE_BODY:
			if(substr($rbuf, 0, 3) == "250")
			{
				$msg = "QUIT\r\n";
				pid_send($sn_smtp_tcp_pid, $msg);
				echo ">> ", $msg;

				$sn_smtp_state = SMTP_STATE_QUIT;
			}
			break;

		case SMTP_STATE_QUIT:
			sn_smtp_cleanup();
			return substr($rbuf, 0, 3);
	}

	return false;
}

function smtp_start($rcpt_email, $rcpt_name, $subject, $body)
{
	global $sn_smtp_state, $sn_smtp_tcp_pid;
	global $sn_smtp_hostname;
	global $sn_smtp_query_name, $sn_smtp_query_count;
	global $sn_smtp_from_email, $sn_smtp_from_name;
	global $sn_smtp_rcpt_email, $sn_smtp_rcpt_name;
	global $sn_smtp_subject, $sn_smtp_body;

	if($sn_smtp_from_email == "")
		smtp_account("", "");

	if($sn_smtp_hostname == "")
		sn_smtp_update_helo_host();

	$sn_smtp_rcpt_email = $rcpt_email;
	$sn_smtp_rcpt_name = $rcpt_name;
	$sn_smtp_subject = $subject;

	$term_pos = strpos($body, "\r\n.\r\n");

	if($term_pos === false)
		$sn_smtp_body = $body;
	else
	{
		echo "sn_smtp: message body truncated\r\n";
		$sn_smtp_body = substr($body, 0, $term_pos + 2);
	}

	$offset = strpos($rcpt_email, "@");

	if($offset === false)
		echo "sn_smtp: invalid email address\r\n";
	else
	{
		$domain = substr($rcpt_email, $offset + 1);
		dns_send_query($domain, RR_MX, 500);

		$sn_smtp_query_name = $domain;
		$sn_smtp_query_count = 2;

		$sn_smtp_state = SMTP_STATE_RR_MX;
	}
}

function smtp_send($rcpt_email, $rcpt_name, $subject, $body)
{
	smtp_start($rcpt_email, $rcpt_name, $subject, $body);

	while(1)
	{
		$msg = smtp_loop();

		if($msg === false)
			usleep(1000);
		else
			return $msg;
	}
}

?>
