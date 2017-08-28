<?php

// $psp_id sn_mysql.php date 20150427

define("MYSQL_STATE_IDLE",        0);
define("MYSQL_STATE_RR",          1);
define("MYSQL_STATE_READY",       2);
define("MYSQL_STATE_CON",         3);
define("MYSQL_STATE_CON_FIN",     4);
define("MYSQL_STATE_AUTH",        5);
define("MYSQL_STATE_QUERY_READY", 6);
define("MYSQL_STATE_QUERY",       7);

$sn_mysql_pid = 0;
$sn_mysql_tcp_id = 0;
$sn_mysql_state = 0;
$sn_mysql_next_tick = 0;
$sn_mysql_server_addr = "";
$sn_mysql_port = "";
$sn_mysql_user_name = "";
$sn_mysql_raw_pwd = "";
$sn_mysql_query_str = "";
$sn_mysql_db_name = "";
$sn_mysql_fetch_count = 0;
$sn_mysql_text_pointer = 0;
$sn_mysql_ip6 = 0;
$sn_mysql_dns_query_count = 0;
$sn_mysql_dns_query_name = "";

function sn_mysql_get_tick()
{
	while(($pid = pid_open("/mmap/st9", O_NODIE)) == -EBUSY)
		usleep(500);
	if(!pid_ioctl($pid, "get state"))
		pid_ioctl($pid, "start");
	$tick = pid_ioctl($pid, "get count");
	pid_close($pid);
	return $tick;
}

function sn_mysql_hash($msg)
{
	if(PHP_VERSION_ID > 10000)
		return hash("sha1", $msg, true);
	else
	{
		$sha1 = system("sha1 init");
		system("sha1 update %1 %2", $sha1, $msg);
		return system("sha1 final %1", $sha1);
	}
}

function sn_mysql_make_pwd($rdata, $raw_pwd)
{
	$data = "";
	$data1 = sn_mysql_hash($raw_pwd);
	$data2 = sn_mysql_hash($data1);
	$sdata = $rdata . $data2;
	$data3 = sn_mysql_hash($sdata);
	for($i = 0; $i < 5; $i++)
	{
		$data_int1 = bin2int($data1, $i*4, 4);
		$data_int2 = bin2int($data3, $i*4, 4);
		$data_int = $data_int1 ^ $data_int2;
		$data .= int2bin($data_int, 4);
	}
	return $data;
}

function sn_mysql_cleanup()
{
	global $sn_mysql_state;
	global $sn_mysql_pid;

	if($sn_mysql_pid)
	{
		pid_close($sn_mysql_pid);
		$sn_mysql_pid = 0;
	}

	$sn_mysql_state = MYSQL_STATE_IDLE;
}

function mysql_close()
{
	global $sn_mysql_pid;
	global $sn_mysql_tcp_id;

	if(pid_ioctl($sn_mysql_pid, "get state") != TCP_CONNECTED)
	{
		sn_mysql_cleanup();
		return false;
	}
	else
	{
		pid_send($sn_mysql_pid, hex2bin("0100000001"));
		sn_mysql_cleanup();
		return true;
	}
}

function mysql_setup($udp_id, $tcp_id, $dns_server = "", $ip6 = false)
{
	global $sn_mysql_tcp_id, $sn_mysql_ip6;

	sn_mysql_cleanup();

	$sn_mysql_tcp_id = $tcp_id;
	$sn_mysql_ip6 = $ip6;

	dns_setup($udp_id, $dns_server, $sn_mysql_ip6);
}

function mysql_start($server, $user_name, $raw_pwd)
{
	global $sn_mysql_state;
	global $sn_mysql_server_addr, $sn_mysql_port;
	global $sn_mysql_dns_query_name;
	global $sn_mysql_user_name, $sn_mysql_raw_pwd;
	global $sn_mysql_ip6;
	global $sn_mysql_dns_query_count;

	$cnt = count(explode(':', $server));
	if($cnt == 1)
		$sn_mysql_port = 3306;
	elseif($cnt == 2)
	{
		$pos = strpos($server, ':');
		$sn_mysql_port = (int)ltrim(rtrim((substr($server, ($pos + 1)))));
		$server = ltrim(rtrim(substr($server, 0, $pos)));
	}
	else
	{
		echo "sn_mysql: invalid hostname or IP address\r\n";
		exit(0);
	}
	if(inet_pton($server) !== false)
	{
		$sn_mysql_server_addr = $server;
		$sn_mysql_state = MYSQL_STATE_READY;
	}
	else
	{
		$sn_mysql_dns_query_name = $server;
		if($sn_mysql_ip6)
			dns_send_query($sn_mysql_dns_query_name, RR_AAAA, 500);
		else
			dns_send_query($sn_mysql_dns_query_name, RR_A, 500);
		$sn_mysql_dns_query_count = 2;
		$sn_mysql_state = MYSQL_STATE_RR;
	}
	$sn_mysql_user_name = ltrim(rtrim($user_name));
	$sn_mysql_raw_pwd = ltrim(rtrim($raw_pwd));
}

function sn_mysql_loop_rr()
{
	global $sn_mysql_state;
	global $sn_mysql_dns_query_count;
	global $sn_mysql_dns_query_name;
	global $sn_mysql_server_addr;
	global $sn_mysql_ip6;

	$rr = dns_loop();

	if($rr === false)
		return false;

	if($rr == "")
	{
		if($sn_mysql_dns_query_count)
		{
			echo "sn_mysql: retry lookup $sn_mysql_dns_query_name\r\n";

			if($sn_mysql_ip6)
				dns_send_query($sn_mysql_dns_query_name, RR_AAAA, 1000);
			else
				dns_send_query($sn_mysql_dns_query_name, RR_A, 1000);

			$sn_mysql_dns_query_count--;
			return false;
		}
		else
		{
			echo "sn_mysql: dns lookup failed\r\n";
			return -1;
		}
	}
	else
	{
		$sn_mysql_server_addr = $rr;
		$sn_mysql_state = MYSQL_STATE_READY;
	}
}

function sn_mysql_loop_con()
{
	global $sn_mysql_pid;
	global $sn_mysql_tcp_id;
	global $sn_mysql_state;
	global $sn_mysql_next_tick;
	global $sn_mysql_server_addr, $sn_mysql_port;
	global $sn_mysql_user_name, $sn_mysql_raw_pwd;

	if($sn_mysql_state == MYSQL_STATE_IDLE)
		return -1;

	$rbuf = "";

	if($sn_mysql_state > MYSQL_STATE_CON)
	{
		$len = pid_ioctl($sn_mysql_pid, "get rxlen");
		if(!$len)
		{
			$state = pid_ioctl($sn_mysql_pid, "get state");
			if($state != TCP_CONNECTED)
			{
				echo "sn_mysql: connection closed!\r\n";
				return -1;
			}
			if(sn_mysql_get_tick() >= $sn_mysql_next_tick)
			{
				echo "sn_mysql: receve timeout!\r\n";
				mysql_close();
			}
			return false;
		}
		pid_recv($sn_mysql_pid, $rbuf, $len);
	}

	switch($sn_mysql_state)
	{
	case MYSQL_STATE_RR:
		if(sn_mysql_loop_rr() === -1)
		{
			sn_mysql_cleanup();
			return -1;
		}
		break;

	case MYSQL_STATE_READY:
		echo "sn_mysql: connect to $sn_mysql_server_addr : $sn_mysql_port ...\r\n";
		$sn_mysql_pid = pid_open("/mmap/tcp$sn_mysql_tcp_id");
		pid_connect($sn_mysql_pid, $sn_mysql_server_addr, $sn_mysql_port);
		$sn_mysql_next_tick = sn_mysql_get_tick() + 5000;
		$sn_mysql_state = MYSQL_STATE_CON;

	case MYSQL_STATE_CON:
		$state = pid_ioctl($sn_mysql_pid, "get state");
		if($state == TCP_CONNECTED)
		{
			echo "sn_mysql: connection completed!\r\n";
			$sn_mysql_state = MYSQL_STATE_CON_FIN;
			return false;
		}
		if(sn_mysql_get_tick() >= $sn_mysql_next_tick)
		{
			echo "sn_mysql: connection timeout!\r\n";
			return -1;
		}
		break;

	case MYSQL_STATE_CON_FIN:
		$rlen = bin2int($rbuf, 0, 3);
		$rbuf = substr($rbuf, 4, $rlen);
		$version_ref = strpos($rbuf, "\x00");

		//$version = substr($rbuf, 0, $version_ref);

		$salt_ref_1 = $version_ref + 5;
		$salt_ref_2 = $salt_ref_1 + 27;
		$salt = substr($rbuf, $salt_ref_1, 8) . substr($rbuf, $salt_ref_2, 12);
		
		$pwd = sn_mysql_make_pwd($salt, $sn_mysql_raw_pwd);

		$sdata_body = hex2bin("85a2");          //[ 2]Client Capabilities
		$sdata_body .= hex2bin("0e00");         //[ 2]Extended Client Capabilities
		$sdata_body .= hex2bin("00000040");     //[ 4]Max Packet
		$sdata_body .= hex2bin("08");           //[ 1]Charset
		for($i = 0; $i < 23; $i++)              //[23]Reserved 23 Bytes
			$sdata_body .= "\x00";
		$sdata_body .= $sn_mysql_user_name;     //[ ?]Username
		$sdata_body .= hex2bin("00");           //[ 1]End of Username
		$sdata_body .= hex2bin("14");           //[ 1]Length of auth-response
		$sdata_body .= $pwd;
		$sdata_body .= "mysql_native_password"; //[21]payload
		$sdata_body .= hex2bin("00");           //[ 1]End of payload

		$sdata_body_len = strlen($sdata_body);
		$sdata = int2bin($sdata_body_len, 3);   //[ 3]Packet Length
		$sdata .= hex2bin("01");                //[ 1]Packet Number
		$sdata .= $sdata_body;                  //[ ?]Entire Packet

		pid_send($sn_mysql_pid, $sdata, $sdata_body_len + 4);
		$sn_mysql_state = MYSQL_STATE_AUTH;
		break;

	case MYSQL_STATE_AUTH:
		if(bin2int($rbuf, 4, 1) == 0)
		{
			echo "sn_mysql: authentication is completed!\r\n";
			$sn_mysql_state = MYSQL_STATE_QUERY_READY;
			return $sn_mysql_pid;
		}
		else
		{
			echo "sn_mysql: authentication failed!\r\n";
			mysql_close();
		}
		break;
	}
	return false;
}

function mysql_connect($server, $user_name, $raw_pwd)
{
	mysql_start($server, $user_name, $raw_pwd);

	while(1)
	{
		$pid = sn_mysql_loop_con();

		if($pid === false)
			usleep(1000);
		elseif($pid < 0)
			return false;
		else
			return true;
	}
}

function mysql_query_loop()
{
	global $sn_mysql_pid;
	global $sn_mysql_state;
	global $sn_mysql_next_tick;
	global $sn_mysql_query_str;

	$rbuf = "";

	if($sn_mysql_state != MYSQL_STATE_QUERY_READY)
	{
		$len = pid_ioctl($sn_mysql_pid, "get rxlen");
		if(!$len)
		{
			$state = pid_ioctl($sn_mysql_pid, "get state");
			if($state != TCP_CONNECTED)
			{
				echo "sn_mysql: connection closed!\r\n";
				return -1;
			}
			if(sn_mysql_get_tick() >= $sn_mysql_next_tick)
			{
				echo "sn_mysql: receve timeout!\r\n";
				mysql_close();
			}
			return false;
		}
		pid_recv($sn_mysql_pid, $rbuf, $len);
	}

	switch($sn_mysql_state)
	{
		case MYSQL_STATE_QUERY_READY:
			$q_data = hex2bin("03");
			$q_data .= $sn_mysql_query_str;
			$slen = strlen($q_data);
			if(($slen > 0) && ($slen <= 0xff))
			{
				$q_sdata = int2bin($slen, 1);
				$q_sdata .= hex2bin("0000");
			}
			elseif(($slen >= 0xff) && ($slen <= 0xffff))
			{
				$q_sdata = int2bin($slen, 2);
				$q_sdata .= hex2bin("00");
			}
			elseif(($slen >= 0xffff) && ($slen <= 0xffffff))
				$q_sdata = int2bin($slen, 3);
			else
			{
				echo "sn_mysql: invalid query!\r\n";
				return -1;
			}
			$q_sdata .= hex2bin("00");
			$q_sdata .= $q_data;
			pid_send($sn_mysql_pid, $q_sdata);
			$sn_mysql_next_tick = sn_mysql_get_tick() + 5000;
			$sn_mysql_state = MYSQL_STATE_QUERY;
			break;

		case MYSQL_STATE_QUERY:
			$sn_mysql_state = MYSQL_STATE_QUERY_READY;
			if((bin2int($rbuf, 4, 1) == 0x00) && (bin2int($rbuf, 0, 3) <= 7))
				return true;
			return $rbuf;
	}

	return false;
}

function mysql_query($query)
{
	global $sn_mysql_state;
	global $sn_mysql_query_str;
	global $sn_mysql_fetch_count, $sn_mysql_text_pointer;

	$sn_mysql_query_str = $query;
	$sn_mysql_fetch_count = 0;
	$sn_mysql_text_pointer = 0;

	if($sn_mysql_state != MYSQL_STATE_QUERY_READY)
		return false;

	while(1)
	{
		$ret = mysql_query_loop();

		if($ret === false)
			usleep(1000);
		else
		{
			if($ret === true)
				return true;
			elseif($ret === -1)
				return false;
			else
				return $ret;
		}
	}
}

function mysql_selectdb_loop()
{
	global $sn_mysql_pid;
	global $sn_mysql_state;
	global $sn_mysql_next_tick;
	global $sn_mysql_db_name;

	$rbuf = "";

	if($sn_mysql_state != MYSQL_STATE_QUERY_READY)
	{
		$len = pid_ioctl($sn_mysql_pid, "get rxlen");
		if(!$len)
		{
			$state = pid_ioctl($sn_mysql_pid, "get state");
			if($state != TCP_CONNECTED)
			{
				echo "sn_mysql: connection closed!\r\n";
				return -1;
			}
			if(sn_mysql_get_tick() >= $sn_mysql_next_tick)
			{
				echo "sn_mysql: receve timeout!\r\n";
				mysql_close();
			}
			return false;
		}
		pid_recv($sn_mysql_pid, $rbuf, $len);
	}

	switch($sn_mysql_state)
	{
		case MYSQL_STATE_QUERY_READY:
			$ud_data = hex2bin("02");      //Use Database Command
			$ud_data .= $sn_mysql_db_name; //Database Name
			$slen = strlen($ud_data);      //Length of Request
			$ud_sdata = int2bin($slen, 1); //Length of Request
			$ud_sdata .= hex2bin("0000");  //Padding
			$ud_sdata .= hex2bin("00");    //Packet Number
			$ud_sdata .= $ud_data;

			pid_send($sn_mysql_pid, $ud_sdata);
			$sn_mysql_next_tick = sn_mysql_get_tick() + 5000;
			$sn_mysql_state = MYSQL_STATE_QUERY;
			break;

		case MYSQL_STATE_QUERY:
			$sn_mysql_state = MYSQL_STATE_QUERY_READY;
			if((bin2int($rbuf, 4, 1) == 0x00) && (bin2int($rbuf, 0, 3) <= 7))
				return true;
			return $rbuf;
	}

	return false;
}

function mysql_select_db($db_name)
{
	global $sn_mysql_state;
	global $sn_mysql_db_name;

	$sn_mysql_db_name = $db_name;

	if($sn_mysql_state != MYSQL_STATE_QUERY_READY)
		return false;

	while(1)
	{
		$ret = mysql_selectdb_loop();

		if($ret === false)
			usleep(1000);
		else
		{
			if($ret === true)
				return true;
			elseif($ret === -1)
				return false;
			else
				return $ret;
		}
	}
}

function mysql_error($result)
{
	$error = "";
	if(($result !== true) && ($result !== false))
	{
		$resp_head = bin2int($result, 4, 1);
		if($resp_head == 0xff)
			$error = substr($result,  13);
	}
	return $error;
}

function mysql_errno($result)
{
	$errno = 0;
	if(($result !== true) && ($result !== false))
	{
		$resp_head = bin2int($result, 4, 1);
		if($resp_head == 0xff)
			$errno = bin2int($result, 5, 2);
	}
	return $errno;
}

function mysql_affected_rows($result)
{
	$aff_rows = -1;
	if(($result !== true) && ($result !== false))
	{
		$resp_head = bin2int($result, 4, 1);
		if($resp_head == 0x00)
			$aff_rows = bin2int($result, 5, 1);
	}
	return $aff_rows;
}

function mysql_num_rows($result)
{
	$num_rows = -1;
	if(($result !== true) && ($result !== false))
	{
		$resp_head = bin2int($result, 4, 1);

		if($resp_head != 0xff || $resp_head != 0xfe || $resp_head != 00)
			$num_cols = $resp_head;
		else
			return -1;
		$pos = strpos($result, int2bin(0xfe, 1)); 
		$num_1 = bin2int($result, $pos - 1, 1);
		$pos = strpos($result, int2bin(0xfe, 1), $pos + 1); 
		$num_2 = bin2int($result, $pos - 1, 1);
		$num_rows = $num_2 - $num_1 - 1;
	}
	return $num_rows;
}

function mysql_result($result, $row, $col = 0)
{
	if(($result === true) || ($result === false))
		return false;

	$num_cols = bin2int($result, 4, 1);
	if($num_cols < $col)
		return false;

	$num_rows = mysql_num_rows($result);
	if($num_rows < $row)
		return false;

	$pointer = strpos($result, int2bin(0xfe, 1)) + 5;
	//skipping records
	for($i = 0; $i < $row; $i++)
	{
		$packet_len = bin2int($result, $pointer, 3);
		$pointer += ($packet_len + 4);
	}
	$pointer += 4;
	//skipping columns
	for($i = 0; $i < $col; $i++)
	{
		$param_len = bin2int($result, $pointer, 1);
		$pointer += ($param_len + 1);
	}
	$param_len = bin2int($result, $pointer, 1);
	$text = substr($result, $pointer + 1, $param_len);
	return $text;
}

function mysql_fetch_row($result)
{
	global $sn_mysql_fetch_count;
	global $sn_mysql_text_pointer;

	$result_arr = array("", "", "", "", "", "", "", "");

	$num_cols = bin2int($result, 4, 1);
	$num_rows = mysql_num_rows($result);

	if($sn_mysql_fetch_count>= $num_rows)
		return false;

	if($sn_mysql_fetch_count== 0)
		$sn_mysql_text_pointer = strpos($result, int2bin(0xfe, 1)) + 5;

	$packet_len = bin2int($result, $sn_mysql_text_pointer, 3);
	$sn_mysql_text_pointer += 4;

	for($i = 0; $i < $num_cols; $i++)
	{
		$len = bin2int($result, $sn_mysql_text_pointer, 1);
		$result_arr[$i] = substr($result, $sn_mysql_text_pointer + 1, $len);
		$sn_mysql_text_pointer += ($len + 1);
	}
	
	$sn_mysql_fetch_count++;
	return $result_arr;
}

?>

