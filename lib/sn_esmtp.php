<?php

// $psp_id sn_esmtp.php date 20170329

include_once "/lib/sn_dns.php";

define("ESMTP_STATE_IDLE",        0);
define("ESMTP_STATE_RR_MX",       1);
define("ESMTP_STATE_RR_A",        2);
define("ESMTP_STATE_CONNECT",     3);
define("ESMTP_STATE_TLS",         4);
define("ESMTP_STATE_220",         5);
define("ESMTP_STATE_HELO",        6);
define("ESMTP_STATE_EHLO",        7);
define("ESMTP_STATE_STARTTLS",    8);
define("ESMTP_STATE_AUTH_PLAIN",  9);
define("ESMTP_STATE_AUTH_LOGIN", 10);
define("ESMTP_STATE_FROM",       11);
define("ESMTP_STATE_RCPT",       12);
define("ESMTP_STATE_DATA",       13);
define("ESMTP_STATE_BODY",       14);
define("ESMTP_STATE_QUIT",       15);

$sn_esmtp_state = 0;
$sn_esmtp_tcp_id = 0;
$sn_esmtp_tcp_pid = 0;
$sn_esmtp_ip6 = false;
$sn_esmtp_next_tick = 0;
$sn_esmtp_hostname = "";
$sn_esmtp_helo_host = "";
$sn_esmtp_msa_name = "";
$sn_esmtp_msa_port = 0;
$sn_esmtp_query_name = "";
$sn_esmtp_query_count = 0;
$sn_esmtp_auth_id = "";
$sn_esmtp_auth_pwd = "";
$sn_esmtp_msg_starttls = "";
$sn_esmtp_msg_auth = "";
$sn_esmtp_from_email = "";
$sn_esmtp_from_name = "";
$sn_esmtp_rcpt_email = "";
$sn_esmtp_rcpt_name = "";
$sn_esmtp_subject = "";
$sn_esmtp_body = "";

function sn_esmtp_get_tick()
{
	while(($pid = pid_open("/mmap/st9", O_NODIE)) == -EBUSY)
		usleep(500);

	if(!pid_ioctl($pid, "get state"))
		pid_ioctl($pid, "start");

	$tick = pid_ioctl($pid, "get count");
	pid_close($pid);

	return $tick;
}

function sn_esmtp_cleanup()
{
	global $sn_esmtp_state, $sn_esmtp_tcp_pid;

	if($sn_esmtp_tcp_pid)
	{
		pid_close($sn_esmtp_tcp_pid);
		$sn_esmtp_tcp_pid = 0;
	}

	$sn_esmtp_state = ESMTP_STATE_IDLE;
}

function sn_esmtp_update_helo_host()
{
	global $sn_esmtp_ip6;
	global $sn_esmtp_hostname, $sn_esmtp_helo_host;

	if($sn_esmtp_hostname)
		return;

	if((int)ini_get("init_net0"))
		$pid_net = pid_open("/mmap/net0");
	else
		$pid_net = pid_open("/mmap/net1");

	if($sn_esmtp_ip6)
		$sn_esmtp_helo_host = pid_ioctl($pid_net, "get ipaddr6");
	else
		$sn_esmtp_helo_host = pid_ioctl($pid_net, "get ipaddr");

	pid_close($pid_net);
}

function sn_esmtp_update_auth($msg_250_auth)
{
	global $sn_esmtp_auth_id, $sn_esmtp_auth_pwd;

	if(strpos($msg_250_auth, "LOGIN") !== false)
		return "AUTH LOGIN\r\n";
	else
	if(strpos($msg_250_auth, "PLAIN") !== false)
	{
		$auth_plain  = $sn_esmtp_auth_id;
		$auth_plain .= "\x00";
		$auth_plain .= $sn_esmtp_auth_id;
		$auth_plain .= "\x00";
		$auth_plain .= $sn_esmtp_auth_pwd;

		$auth_cmd  = "AUTH PLAIN ";
		$auth_cmd .= system("base64 enc %1 mime", $auth_plain);
		$auth_cmd .= "\r\n";

		return $auth_cmd;
	}
	else
		return "";
}

function esmtp_setup($udp_id, $tcp_id, $dns_server = "", $ip6 = false)
{
	global $sn_esmtp_tcp_id, $sn_esmtp_ip6;

	sn_esmtp_cleanup();

	$sn_esmtp_tcp_id = $tcp_id;
	$sn_esmtp_ip6 = $ip6;

	dns_setup($udp_id, $dns_server, $sn_esmtp_ip6);
}

function esmtp_hostname($hostname)
{
	global $sn_esmtp_hostname, $sn_esmtp_helo_host;

	if($hostname)
	{
		$sn_esmtp_hostname = $hostname;
		$sn_esmtp_helo_host = $hostname;
	}
}

function esmtp_account($email, $name)
{
	global $sn_esmtp_hostname, $sn_esmtp_helo_host;
	global $sn_esmtp_from_email, $sn_esmtp_from_name;

	if($sn_esmtp_hostname == "")
		sn_esmtp_update_helo_host();

	if($email == "")
	{
		if(inet_pton($sn_esmtp_helo_host) === false)
			$sn_esmtp_from_email = "PHPoC@$sn_esmtp_helo_host";
		else
			$sn_esmtp_from_email = "PHPoC@[$sn_esmtp_helo_host]";
	}
	else
		$sn_esmtp_from_email = $email;

	if($name == "")
		$sn_esmtp_from_name = "PHPoC";
	else
		$sn_esmtp_from_name = $name;
}

function esmtp_auth($auth_id, $auth_pwd)
{
	global $sn_esmtp_auth_id, $sn_esmtp_auth_pwd;

	$sn_esmtp_auth_id = $auth_id;
	$sn_esmtp_auth_pwd = $auth_pwd;
}

function esmtp_msa($msa_name, $msa_port)
{
	global $sn_esmtp_msa_name, $sn_esmtp_msa_port;

	$sn_esmtp_msa_name = $msa_name;
	$sn_esmtp_msa_port = $msa_port;
}

function sn_esmtp_loop_rr()
{
	global $sn_esmtp_state, $sn_esmtp_tcp_id, $sn_esmtp_tcp_pid;
	global $sn_esmtp_ip6;
	global $sn_esmtp_msa_name, $sn_esmtp_msa_port;
	global $sn_esmtp_query_name, $sn_esmtp_query_count;

	$rr = dns_loop();

	if($rr === false)
		return false;

	if($rr == "")
	{
		if($sn_esmtp_query_count)
		{
			echo "sn_esmtp: retry lookup $sn_esmtp_query_name\r\n";

			if($sn_esmtp_state == ESMTP_STATE_RR_MX)
				dns_send_query($sn_esmtp_query_name, RR_MX, 1000);
			else
			{
				if($sn_esmtp_ip6)
					dns_send_query($sn_esmtp_query_name, RR_AAAA, 1000);
				else
					dns_send_query($sn_esmtp_query_name, RR_A, 1000);
			}

			$sn_esmtp_query_count--;
			return false;
		}
		else
		{
			echo "sn_esmtp: lookup failed\r\n";
			return "";
		}
	}

	if($sn_esmtp_state == ESMTP_STATE_RR_MX)
	{
		echo "sn_esmtp: MX $rr\r\n";

		if($sn_esmtp_ip6)
			dns_send_query($rr, RR_AAAA, 500);
		else
			dns_send_query($rr, RR_A, 500);

		$sn_esmtp_query_name = $rr;
		$sn_esmtp_query_count = 1;

		$sn_esmtp_state = ESMTP_STATE_RR_A;
	}
	else
	{
		$sn_esmtp_tcp_pid = pid_open("/mmap/tcp$sn_esmtp_tcp_id");

		if($sn_esmtp_msa_name == "")
		{
			echo "sn_esmtp: connect to $rr:25...";
			pid_connect($sn_esmtp_tcp_pid, $rr, 25);
		}
		else
		{
			if($sn_esmtp_msa_port == 465)
			{
				echo "sn_esmtp: connect to $rr:smtps...";
				pid_ioctl($sn_esmtp_tcp_pid, "set api ssl");
				pid_ioctl($sn_esmtp_tcp_pid, "set ssl method tls1_client");
			}
			else
				echo "sn_esmtp: connect to $rr:$sn_esmtp_msa_port...";

			pid_connect($sn_esmtp_tcp_pid, $rr, $sn_esmtp_msa_port);
		}

		$sn_esmtp_state = ESMTP_STATE_CONNECT;
	}

	return false;
}

function sn_esmtp_loop_ehlo(&$rbuf)
{
	global $sn_esmtp_state, $sn_esmtp_tcp_pid;
	global $sn_esmtp_msg_starttls, $sn_esmtp_msg_auth;
	global $sn_esmtp_auth_id, $sn_esmtp_auth_pwd;
	global $sn_esmtp_from_email, $sn_esmtp_from_name;

	switch($sn_esmtp_state)
	{
		case ESMTP_STATE_EHLO:
			if((substr($rbuf, 0, 3) == "250") && (substr($rbuf, 4, 8) == "STARTTLS"))
			{
				if(pid_ioctl($sn_esmtp_tcp_pid, "get state") == TCP_CONNECTED)
					$sn_esmtp_msg_starttls = "STARTTLS\r\n";
			}

			if((substr($rbuf, 0, 3) == "250") && (substr($rbuf, 4, 4) == "AUTH"))
				$sn_esmtp_msg_auth = sn_esmtp_update_auth($rbuf);

			if(pid_ioctl($sn_esmtp_tcp_pid, "get rxlen"))
				break;

		case ESMTP_STATE_HELO:
			if(substr($rbuf, 0, 3) == "250")
			{
				if($sn_esmtp_msg_starttls != "")
				{
					pid_send($sn_esmtp_tcp_pid, $sn_esmtp_msg_starttls);
					echo ">> ", $sn_esmtp_msg_starttls;

					$sn_esmtp_state = ESMTP_STATE_STARTTLS;
					$sn_esmtp_msg_starttls = "";
				}
				else
				if($sn_esmtp_msg_auth != "")
				{
					pid_send($sn_esmtp_tcp_pid, $sn_esmtp_msg_auth);
					echo ">> ", $sn_esmtp_msg_auth;

					if(substr($sn_esmtp_msg_auth, 5, 5) == "PLAIN")
						$sn_esmtp_state = ESMTP_STATE_AUTH_PLAIN;
					else
						$sn_esmtp_state = ESMTP_STATE_AUTH_LOGIN;

					$sn_esmtp_msg_auth = "";
				}
				else
				{
					$msg = "MAIL FROM:<$sn_esmtp_from_email>\r\n";
					pid_send($sn_esmtp_tcp_pid, $msg);
					echo ">> ", $msg;

					$sn_esmtp_state = ESMTP_STATE_FROM;
				}
			}
			break;

		case ESMTP_STATE_STARTTLS:
			if(substr($rbuf, 0, 3) == "220")
			{
				pid_ioctl($sn_esmtp_tcp_pid, "set api ssl");
				pid_ioctl($sn_esmtp_tcp_pid, "set ssl method tls1_client");
				$sn_esmtp_state = ESMTP_STATE_TLS;
			}
			break;

		case ESMTP_STATE_AUTH_PLAIN:
			if(substr($rbuf, 0, 3) == "235")
			{
				$msg = "MAIL FROM:<$sn_esmtp_from_email>\r\n";
				pid_send($sn_esmtp_tcp_pid, $msg);
				echo ">> ", $msg;

				$sn_esmtp_state = ESMTP_STATE_FROM;
			}
			break;

		case ESMTP_STATE_AUTH_LOGIN:
			if(substr($rbuf, 0, 3) == "235")
			{
				$msg = "MAIL FROM:<$sn_esmtp_from_email>\r\n";
				pid_send($sn_esmtp_tcp_pid, $msg);
				echo ">> ", $msg;

				$sn_esmtp_state = ESMTP_STATE_FROM;
			}
			else
			if(substr($rbuf, 0, 3) == "334")
			{
				$msg = system("base64 dec %1 mime", substr($rbuf, 4));
				echo "$msg\r\n";

				if(substr($msg, 0, 4) == "User")
					$msg = system("base64 enc %1 mime", $sn_esmtp_auth_id) . "\r\n";
				else
					$msg = system("base64 enc %1 mime", $sn_esmtp_auth_pwd) . "\r\n";

				pid_send($sn_esmtp_tcp_pid, $msg);
				echo ">> ", $msg;
			}
			break;
	}
}

function esmtp_loop()
{
	global $sn_esmtp_state, $sn_esmtp_tcp_pid, $sn_esmtp_next_tick;
	global $sn_esmtp_helo_host, $sn_esmtp_msa_name;
	global $sn_esmtp_from_email, $sn_esmtp_from_name;
	global $sn_esmtp_rcpt_email, $sn_esmtp_rcpt_name;
	global $sn_esmtp_subject, $sn_esmtp_body;

	if($sn_esmtp_state == ESMTP_STATE_IDLE)
		return "";

	$rbuf = "";

	if($sn_esmtp_state > ESMTP_STATE_TLS)
	{
		$len = pid_ioctl($sn_esmtp_tcp_pid, "get rxlen \x0d\x0a");

		if(!$len)
		{
			$state = pid_ioctl($sn_esmtp_tcp_pid, "get state");

			if(($state != TCP_CONNECTED) && ($state != SSL_CONNECTED))
			{
				echo "sn_esmtp: connection closed\r\n";

				if($sn_esmtp_state == ESMTP_STATE_QUIT)
					$rbuf = "221";
				else
					$rbuf = "";

				sn_esmtp_cleanup();
				return $rbuf;
			}

			if(sn_esmtp_get_tick() >= $sn_esmtp_next_tick)
			{
				echo "sn_esmtp: sending email timed out\r\n";
				sn_esmtp_cleanup();
				return "";
			}

			return false;
		}

		pid_recv($sn_esmtp_tcp_pid, $rbuf, $len);
		echo "<< ", $rbuf;

		if((substr($rbuf, 0, 1) == "4") || (substr($rbuf, 0, 1) == "5"))
		{
			for(;;)
			{
				$len = pid_ioctl($sn_esmtp_tcp_pid, "get rxlen \x0d\x0a");
				if(!$len)
					break;
				pid_recv($sn_esmtp_tcp_pid, $rbuf, $len);
				echo "<< ", $rbuf;
			}

			sn_esmtp_cleanup();
			return substr($rbuf, 0, 3);
		}
	}

	switch($sn_esmtp_state)
	{
		case ESMTP_STATE_RR_MX:
		case ESMTP_STATE_RR_A:
			if(sn_esmtp_loop_rr() === "")
			{
				sn_esmtp_cleanup();
				return "";
			}
			break;

		case ESMTP_STATE_CONNECT:
			$state = pid_ioctl($sn_esmtp_tcp_pid, "get state");

			if(($state == TCP_CONNECTED) || ($state == SSL_CONNECTED))
			{
				echo "ok\r\n";
				$sn_esmtp_next_tick = sn_esmtp_get_tick() + 30000; // 30 seconds
				$sn_esmtp_state = ESMTP_STATE_220;
			}
			else
			if($state == TCP_CLOSED)
			{
				echo "failed\r\n";
				sn_esmtp_cleanup();
				return "";
			}
			break;

		case ESMTP_STATE_TLS:
			$state = pid_ioctl($sn_esmtp_tcp_pid, "get state");

			if($state == SSL_CLOSED)
			{
				echo "sn_esmtp: connection closed\r\n";
				sn_esmtp_cleanup();
				return "";
			}

			if($state == SSL_CONNECTED)
			{
				$msg = "EHLO $sn_esmtp_helo_host\r\n";
				pid_send($sn_esmtp_tcp_pid, $msg);
				echo ">> ", $msg;

				$sn_esmtp_state = ESMTP_STATE_EHLO;
			}
			break;

		case ESMTP_STATE_220:
			if(substr($rbuf, 0, 3) == "220")
			{
				if($sn_esmtp_msa_name == "")
					$msg = "HELO $sn_esmtp_helo_host\r\n";
				else
					$msg = "EHLO $sn_esmtp_helo_host\r\n";

				pid_send($sn_esmtp_tcp_pid, $msg);
				echo ">> ", $msg;

				if($sn_esmtp_msa_name == "")
					$sn_esmtp_state = ESMTP_STATE_HELO;
				else
					$sn_esmtp_state = ESMTP_STATE_EHLO;
			}
			break;

		case ESMTP_STATE_EHLO:
		case ESMTP_STATE_HELO:
		case ESMTP_STATE_STARTTLS:
		case ESMTP_STATE_AUTH_PLAIN:
		case ESMTP_STATE_AUTH_LOGIN:
			sn_esmtp_loop_ehlo($rbuf);
			break;

		case ESMTP_STATE_FROM:
			if(substr($rbuf, 0, 3) == "250")
			{
				$msg = "RCPT TO:<$sn_esmtp_rcpt_email>\r\n";
				pid_send($sn_esmtp_tcp_pid, $msg);
				echo ">> ", $msg;

				$sn_esmtp_state = ESMTP_STATE_RCPT;
			}
			break;

		case ESMTP_STATE_RCPT:
			if(substr($rbuf, 0, 3) == "250")
			{
				$msg = "DATA\r\n";
				pid_send($sn_esmtp_tcp_pid, $msg);
				echo ">> ", $msg;

				$sn_esmtp_state = ESMTP_STATE_DATA;
			}
			break;

		case ESMTP_STATE_DATA:
			if(substr($rbuf, 0, 3) == "354")
			{
				$msg  = "From: \"$sn_esmtp_from_name\" <$sn_esmtp_from_email>\r\n";
				$msg .= "To: \"$sn_esmtp_rcpt_name\" <$sn_esmtp_rcpt_email>\r\n";
				$msg .= "Subject: $sn_esmtp_subject\r\n\r\n";

				if(strlen($msg) + strlen($sn_esmtp_body) + 5 >= MAX_STRING_LEN)
				{
					echo "sn_esmtp: body too long\r\n";
					sn_esmtp_cleanup();
					return "";
				}

				$msg .= $sn_esmtp_body;
				$msg .= "\r\n.\r\n";

				pid_send($sn_esmtp_tcp_pid, $msg);
				echo ">> $msg";

				$sn_esmtp_state = ESMTP_STATE_BODY;
			}
			break;

		case ESMTP_STATE_BODY:
			if(substr($rbuf, 0, 3) == "250")
			{
				$msg = "QUIT\r\n";
				pid_send($sn_esmtp_tcp_pid, $msg);
				echo ">> ", $msg;

				$sn_esmtp_state = ESMTP_STATE_QUIT;
			}
			break;

		case ESMTP_STATE_QUIT:
			sn_esmtp_cleanup();
			return substr($rbuf, 0, 3);

	}

	return false;
}

function esmtp_start($rcpt_email, $rcpt_name, $subject, $body)
{
	global $sn_esmtp_state, $sn_esmtp_tcp_pid;
	global $sn_esmtp_ip6;
	global $sn_esmtp_hostname;
	global $sn_esmtp_msa_name;
	global $sn_esmtp_query_name, $sn_esmtp_query_count;
	global $sn_esmtp_from_email, $sn_esmtp_from_name;
	global $sn_esmtp_rcpt_email, $sn_esmtp_rcpt_name;
	global $sn_esmtp_subject, $sn_esmtp_body;

	if($sn_esmtp_from_email == "")
		esmtp_account("", "");

	if($sn_esmtp_hostname == "")
		sn_esmtp_update_helo_host();

	$sn_esmtp_rcpt_email = $rcpt_email;
	$sn_esmtp_rcpt_name = $rcpt_name;
	$sn_esmtp_subject = $subject;

	$term_pos = strpos($body, "\r\n.\r\n");

	if($term_pos === false)
		$sn_esmtp_body = $body;
	else
	{
		echo "sn_esmtp: message body truncated\r\n";
		$sn_esmtp_body = substr($body, 0, $term_pos + 2);
	}

	if($sn_esmtp_msa_name == "")
	{
		$offset = strpos($rcpt_email, "@");

		if($offset === false)
			echo "sn_esmtp: invalid email address\r\n";
		else
		{
			$domain = substr($rcpt_email, $offset + 1);

			dns_send_query($domain, RR_MX, 500);

			$sn_esmtp_query_name = $domain;
			$sn_esmtp_query_count = 2;

			$sn_esmtp_state = ESMTP_STATE_RR_MX;
		}
	}
	else
	{
		if($sn_esmtp_ip6)
			dns_send_query($sn_esmtp_msa_name, RR_AAAA, 500);
		else
			dns_send_query($sn_esmtp_msa_name, RR_A, 500);

		$sn_esmtp_query_name = $sn_esmtp_msa_name;
		$sn_esmtp_query_count = 1;

		$sn_esmtp_state = ESMTP_STATE_RR_A;
	}
}

function esmtp_send($rcpt_email, $rcpt_name, $subject, $body)
{
	esmtp_start($rcpt_email, $rcpt_name, $subject, $body);

	while(1)
	{
		$msg = esmtp_loop();

		if($msg === false)
			usleep(1000);
		else
			return $msg;
	}
}

?>
