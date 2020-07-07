<?php

// $psp_id sn_http.php date 20200629

include_once "/lib/sn_dns.php";

define("HTTP_STATE_CLOSED",     0);
define("HTTP_STATE_RR_A",       1);
define("HTTP_STATE_CONNECT",    2);
define("HTTP_STATE_HEADER",     3);
define("HTTP_STATE_BODY",       4);
define("HTTP_STATE_CHUNK_CRLF", 5);
define("HTTP_STATE_CHUNK_LEN",  6);
define("HTTP_STATE_CHUNK",      7);

define("HTTP_VERSION", "HTTP/1.1");
define("HTTP_RECV_TIMEOUT", 10); // receiving header & read_sync timeout

$sn_http_state = 0;
$sn_http_tcp_id = 0;
$sn_http_tcp_pid = 0;
$sn_http_ip6 = false;
$sn_http_protocol = "";
$sn_http_method = "";
$sn_http_host = "";
$sn_http_port = 0;
$sn_http_path = "";
$sn_http_req_header = "";
$sn_http_req_body = "";
$sn_http_chunk_len = 0;
$sn_http_query_count = 0;
$sn_http_next_tick = 0;

function sn_http_get_tick()
{
	while(($pid = pid_open("/mmap/st9", O_NODIE)) == -EBUSY)
		usleep(500);

	if(!pid_ioctl($pid, "get state"))
		pid_ioctl($pid, "start");

	$tick = pid_ioctl($pid, "get count");
	pid_close($pid);

	return $tick;
}

function sn_http_cleanup()
{
	global $sn_http_state, $sn_http_tcp_pid;
	global $sn_http_req_header;

	if($sn_http_tcp_pid)
	{
		pid_close($sn_http_tcp_pid);
		$sn_http_tcp_pid = 0;
	}

	$sn_http_req_header = "";
	$sn_http_chunk_len = 0;

	$sn_http_state = HTTP_STATE_CLOSED;
}

function http_setup($udp_id, $tcp_id, $dns_server = "", $ip6 = false)
{
	global $sn_http_tcp_id, $sn_http_ip6;

	sn_http_cleanup();

	$sn_http_tcp_id = $tcp_id;
	$sn_http_ip6 = $ip6;

	$pid = pid_open("/mmap/tcp$tcp_id");
	pid_close($pid);

	dns_setup($udp_id, $dns_server, $ip6);
}

function http_tcp_ioctl($cmd)
{
	global $sn_http_tcp_id, $sn_http_tcp_pid;

	if($sn_http_tcp_pid)
		$close_pid = false;
	else
		$close_pid = true;

	if(!$sn_http_tcp_pid)
		$sn_http_tcp_pid = pid_open("/mmap/tcp$sn_http_tcp_id");

	$args = explode(" ", $cmd);

	if(($args[1] == "ssl") || ($args[1] == "tls"))
		pid_ioctl($sn_http_tcp_pid, "set api ssl");

	$retval = pid_ioctl($sn_http_tcp_pid, $cmd);

	if($close_pid)
	{
		pid_close($sn_http_tcp_pid);
		$sn_http_tcp_pid = 0;
	}

	return $retval;
}

// internal function header() is for http response
// http_req_header() is for http request
function http_req_header($field)
{
	global $sn_http_state;
	global $sn_http_req_header;

	if($sn_http_state >= HTTP_STATE_CONNECT)
	{
		echo "http_req_header: session busy\r\n";
		return;
	}

	if($field == "")
	{
		$sn_http_req_header = "";
		return;
	}

	// don't use explode(). field value may contain ':'
	$pos = strpos($field, ":");

	if($pos === false)
	{
		echo "http_req_header: invalid header field $field\r\n";
		return;
	}

	$field_name = ltrim(rtrim(substr($field, 0, $pos)));

	if(!$field_name)
	{
		echo "http_req_header: invalid header field $field\r\n";
		return;
	}

	if(strlen($field) > ($pos + 1))
		$field_value = ltrim(rtrim(substr($field, $pos + 1)));
	else
		$field_value = "";

	$sn_http_req_header .= ($field_name . ":");

	if($field_value)
 		$sn_http_req_header .= (" " . $field_value);

	$sn_http_req_header .= "\r\n";
}

function http_auth($auth_id, $auth_pwd, $method = "Basic")
{
	if($method != "Basic")
	{
		echo "http_auth: unsupported authorization method \"$method\"\r\n";
		return;
	}

	$enc_id_pwd = system("base64 enc %1", "$auth_id:$auth_pwd");

	http_req_header("Authorization: $method $enc_id_pwd");
}

function http_find_header($header, $field_name)
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

function sn_http_connect($addr, $port)
{
	global $sn_http_tcp_id, $sn_http_tcp_pid;
	global $sn_http_state, $sn_http_protocol, $sn_http_host;

	if($sn_http_state >= HTTP_STATE_CONNECT)
		sn_http_cleanup();

	if(!$sn_http_tcp_pid)
		$sn_http_tcp_pid = pid_open("/mmap/tcp$sn_http_tcp_id");

	if($sn_http_protocol == "https")
	{
		pid_ioctl($sn_http_tcp_pid, "set api ssl");
		pid_ioctl($sn_http_tcp_pid, "set ssl method client");

		if(PHP_VERSION_ID >= 20200)
		{
			pid_ioctl($sn_http_tcp_pid, "set ssl extension sni $sn_http_host");
			pid_ioctl($sn_http_tcp_pid, "set ssl vsni 1");
		}
	}

	echo "sn_http: connect to $addr:$port...";

	pid_bind($sn_http_tcp_pid, "", 0);
	pid_connect($sn_http_tcp_pid, $addr, $port);

	$sn_http_state = HTTP_STATE_CONNECT;
}

function sn_http_loop_rr()
{
	global $sn_http_ip6;
	global $sn_http_host, $sn_http_port;
	global $sn_http_query_count;

	$rr = dns_loop();

	if($rr === false)
		return false;

	if($rr == "")
	{
		if($sn_http_query_count)
		{
			echo "sn_http: retry lookup $sn_http_host\r\n";

			if($sn_http_ip6)
				dns_send_query($sn_http_host, RR_AAAA, 1000);
			else
				dns_send_query($sn_http_host, RR_A, 1000);

			$sn_http_query_count--;
			return false;
		}
		else
		{
			echo "sn_http: lookup failed\r\n";
			return "";
		}
	}

	sn_http_connect($rr, $sn_http_port);

	return false;
}

function http_loop()
{
	global $sn_http_state, $sn_http_tcp_pid, $sn_http_ip6, $sn_http_protocol;
	global $sn_http_method, $sn_http_host, $sn_http_port, $sn_http_path;
	global $sn_http_req_header, $sn_http_req_body;
	global $sn_http_next_tick;

	if($sn_http_state == HTTP_STATE_CLOSED)
		return "";

	switch($sn_http_state)
	{
		case HTTP_STATE_RR_A:
			if(sn_http_loop_rr() === "")
			{
				sn_http_cleanup();
				return "";
			}
			break;

		case HTTP_STATE_CONNECT:
			$state = pid_ioctl($sn_http_tcp_pid, "get state");

			if($state == TCP_CLOSED)
			{
				echo "failed\r\n";
				sn_http_cleanup();
				return "";
			}
			else
			{
				$connected = false;

				if(($sn_http_protocol == "https") && ($state == SSL_CONNECTED))
					$connected = true;

				if(($sn_http_protocol == "http") && ($state == TCP_CONNECTED))
					$connected = true;

				if($connected)
				{
					echo "ok\r\n";
					$sn_http_next_tick = sn_http_get_tick() + HTTP_RECV_TIMEOUT * 1000;

					pid_send($sn_http_tcp_pid, $sn_http_method . " ");
					pid_send($sn_http_tcp_pid, $sn_http_path . " " . HTTP_VERSION . "\r\n");
					pid_send($sn_http_tcp_pid, "Host: $sn_http_host\r\n");
					pid_send($sn_http_tcp_pid, "Connection: close\r\n");
					if($sn_http_method == "POST")
					{
						$req_body_len = strlen($sn_http_req_body);
						pid_send($sn_http_tcp_pid, "Content-Length: $req_body_len\r\n");
					}
					if($sn_http_req_header)
						pid_send($sn_http_tcp_pid, $sn_http_req_header);
					pid_send($sn_http_tcp_pid, "\r\n");
					if($sn_http_req_body)
						pid_send($sn_http_tcp_pid, $sn_http_req_body);
	
					$sn_http_state = HTTP_STATE_HEADER;
				}
			}
			break;

		case HTTP_STATE_HEADER:
			$len = pid_ioctl($sn_http_tcp_pid, "get rxlen \r\n\r\n");

			if($len)
			{
				$resp_head = "";
				pid_recv($sn_http_tcp_pid, $resp_head, $len);

				if(http_find_header($resp_head, "Transfer-Encoding") === "chunked")
					$sn_http_state = HTTP_STATE_CHUNK_LEN;
				else
					$sn_http_state = HTTP_STATE_BODY;

				return $resp_head;
			}

			$state = pid_ioctl($sn_http_tcp_pid, "get state");

			if(($state != TCP_CONNECTED) && ($state != SSL_CONNECTED))
			{
				echo "sn_http: connection closed\r\n";
				sn_http_cleanup();
				return "";
			}

			if(sn_http_get_tick() >= $sn_http_next_tick)
			{
				echo "sn_http: request timeout\r\n";
				sn_http_cleanup();
				return "";
			}

			break;
	}

	return false;
}

function http_start($method, $uri, $body = "")
{
	global $sn_http_state, $sn_http_ip6;
	global $sn_http_protocol, $sn_http_method;
	global $sn_http_host, $sn_http_port, $sn_http_path;
	global $sn_http_req_body;
	global $sn_http_query_count;

	if($sn_http_state != HTTP_STATE_CLOSED)
	{
		echo "http_start: session busy\r\n";
		return;
	}

	$sn_http_method = strtoupper($method);

	$pos = strpos($uri, "://");

	if($pos === false)
		$sn_http_protocol = "http";
	else
	{
		$sn_http_protocol = strtolower(substr($uri, 0, $pos));
		$uri = substr($uri, $pos + 3);
	}

	$pos = strpos($uri, "/");

	if($pos === false)
	{
		$sn_http_host = $uri;
		$sn_http_path = "/";
	}
	else
	{
		$sn_http_host = substr($uri, 0, $pos);
		$sn_http_path = substr($uri, $pos);
	}

	$pos = strpos($sn_http_host, ":");

	if($pos === false)
	{
		if($sn_http_protocol == "https")
			$sn_http_port = 443;
		else
			$sn_http_port = 80;
	}
	else
	{
		$sn_http_port = (int)substr($sn_http_host, $pos + 1);
		$sn_http_host = substr($sn_http_host, 0, $pos);
	}

	$sn_http_req_body = $body;

	if(inet_pton($sn_http_host) === false)
	{
		if($sn_http_ip6)
			dns_send_query($sn_http_host, RR_AAAA, 500);
		else
			dns_send_query($sn_http_host, RR_A, 500);

		$sn_http_query_count = 2;
		$sn_http_state = HTTP_STATE_RR_A;
	}
	else
		sn_http_connect($sn_http_host, $sn_http_port);
}

function http_request($method, $uri, $body = "")
{
	http_start($method, $uri, $body);

	while(1)
	{
		$resp_head = http_loop();

		if($resp_head === false)
			usleep(1000);
		else
			return $resp_head;
	}
}

function http_state()
{
	global $sn_http_state;

	return $sn_http_state;
}

function sn_http_rxlen()
{
	global $sn_http_state, $sn_http_tcp_pid;
	global $sn_http_chunk_len;

	if($sn_http_state == HTTP_STATE_BODY)
		return pid_ioctl($sn_http_tcp_pid, "get rxlen");

	if($sn_http_state == HTTP_STATE_CHUNK_CRLF)
	{
		if(pid_ioctl($sn_http_tcp_pid, "get rxlen") > 2)
		{
			$rbuf = "";
			pid_recv($sn_http_tcp_pid, $rbuf, 2);

			if($rbuf != "\r\n")
				echo "sn_http: invalid chunk trailer\r\n";

			//echo "sn_http: trailing CRLF\r\n";

			$sn_http_state = HTTP_STATE_CHUNK_LEN;
		}
		else
			return 0;
	}

	if($sn_http_state == HTTP_STATE_CHUNK_LEN)
	{
		if($rlen = pid_ioctl($sn_http_tcp_pid, "get rxlen \r\n"))
		{
			$rbuf = "";
			pid_recv($sn_http_tcp_pid, $rbuf, $rlen);

			$sn_http_chunk_len = bin2int(hex2bin($rbuf), 0, 2, true);

			//echo "sn_http: chunk_len $sn_http_chunk_len\r\n";

			if($sn_http_chunk_len)
				$sn_http_state = HTTP_STATE_CHUNK;
			else
				$sn_http_state = HTTP_STATE_BODY;
		}
		else
			return 0;
	}

	if($sn_http_state == HTTP_STATE_CHUNK)
	{
		if($sn_http_chunk_len)
		{
			$rlen = pid_ioctl($sn_http_tcp_pid, "get rxlen");

			if($rlen > $sn_http_chunk_len)
				return $sn_http_chunk_len;
			else
				return $rlen;
		}
		else
		{
			$sn_http_state = HTTP_STATE_CHUNK_CRLF;
			return 0;
		}
	}

	return 0;
}

function http_read(&$rbuf, $rlen = MAX_STRING_LEN)
{
	global $sn_http_state, $sn_http_tcp_pid;
	global $sn_http_chunk_len;

	if($sn_http_state < HTTP_STATE_BODY)
		return 0;

	$state = pid_ioctl($sn_http_tcp_pid, "get state");

	if(($state != TCP_CONNECTED) && ($state != SSL_CONNECTED))
	{
		if(!pid_ioctl($sn_http_tcp_pid, "get rxlen"))
		{
			sn_http_cleanup();
			return 0;
		}
	}

	$rxlen = sn_http_rxlen();

	if($rxlen)
	{
		if($rlen > $rxlen)
			$rlen = $rxlen;

		if($sn_http_state == HTTP_STATE_CHUNK)
		{
			$sn_http_chunk_len -= $rlen;

			if($sn_http_chunk_len < 0)
			{
				echo "http_read: chunk underflow $sn_http_chunk_len\r\n";
				$sn_http_chunk_len = 0;
			}
		}

		return pid_recv($sn_http_tcp_pid, $rbuf, $rlen);
	}
	else
		return 0;
}

function http_read_sync(&$rbuf, $rlen = MAX_STRING_LEN)
{
	global $sn_http_state;

	if($sn_http_state < HTTP_STATE_BODY)
		return 0;

	$rbuf = "";
	$frag = "";

	$timeout_tick = sn_http_get_tick() + HTTP_RECV_TIMEOUT * 1000;

	while($rlen)
	{
		$len = http_read($frag, $rlen);

		if($len)
		{
			$rbuf .= $frag;
			$rlen -= $len;
		}
		else
			usleep(1000);

		if($sn_http_state == HTTP_STATE_CLOSED)
			break;

		if(sn_http_get_tick() >= $timeout_tick)
		{
			echo "http_read_sync: timeout\r\n";
			break;
		}
	}

	return strlen($rbuf);
}

function http_close()
{
	sn_http_cleanup();
}

?>

