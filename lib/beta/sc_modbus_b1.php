<?php

// $psp_id sc_modbus.php date 20200706

/* Public function codes */
define("READ_COILS",            1);
define("READ_INPUT_PORTS",      2);
define("READ_HOLDING_REGS",     3);
define("READ_INPUT_REGS",       4);
define("WRITE_COIL",            5);
define("WRITE_SINGLE_REG",      6);
define("READ_EXCEPTION_STATUS", 7);
define("WRITE_MULTI_COILS",    15);
define("WRITE_MULTIPLE_REGS",  16);
define("MEI_TRANSPORT",        43);

/* Vendor specific function codes */
define("WRITE_PULSE",         105);

define("MBAP_ILLEGAL_FC",       0x01);
define("MBAP_ILLEGAL_ADDR",     0x02);
define("MBAP_ILLEGAL_VALUE",    0x03);
define("MBAP_SERVER_FAILURE",   0x04);

define("GW_PATH_UNAVAILABLE",   0x0a);
define("GW_TARGET_NOT_RESPOND", 0x0b);

// flags for device
define("MODBUS_XUART",  0x0001);
define("MODBUS_TCP",    0x0002);

// flags for modbus type
define("MODBUS_SLAVE",  0x0010);
define("MODBUS_MASTER", 0x0020);
define("MODBUS_RTU",    0x0040);
define("MODBUS_ASCII",  0x0080);

// flags for network
define("MODBUS_SERVER", 0x0100);
define("MODBUS_CLIENT", 0x0200);
define("MODBUS_SECURE", 0x0400);

// flags for user
define("MODBUS_NO_CHECK",  0x1000);
define("MODBUS_PEEK",      0x2000);

define("MBT_MAX_FSIZE",  260);
define("RTU_MAX_FSIZE",  256);
define("ASC_MAX_FSIZE",  513);

/* global variables */
$modbus_flags = array(0x0000, 0x0000, 0x0000, 0x0000, 0x0000);

/* local variables */
$sc_modbus_dev_id   = array( 0,  0,  0,  0,  0);
$sc_modbus_dev_pid  = array( 0,  0,  0,  0,  0);
$sc_modbus_tcp_addr = array("", "", "", "", "");
$sc_modbus_tcp_port = array( 0,  0,  0,  0,  0);

$sc_modbus_cod_next_tick = array(0, 0, 0, 0, 0);
$sc_modbus_ip4_next_tick = 0;  // print out debug message for ipv4 ready

$sc_device_txcnt   = array(0, 0, 0, 0, 0);
$sc_device_rxcnt   = array(0, 0, 0, 0, 0);
$sc_device_dropcnt = array(0, 0, 0, 0, 0);

function sc_modbus_get_tick()
{
	while(($st_pid = pid_open("/mmap/st9", O_NODIE)) == -EBUSY)
		usleep(500);

	if(!pid_ioctl($st_pid, "get state"))
		pid_ioctl($st_pid, "start");

	$tick = pid_ioctl($st_pid, "get count");
	pid_close($st_pid);

	return $tick;
}

function sc_modbus_check_id($id, $from)
{
	if(($id < 0) || ($id > 4))
		exit("$from: modbus-id out of range $id\r\n");
}

function sc_modbus_get_pid($id, $from)
{
	global $sc_modbus_dev_pid;

	if(!$sc_modbus_dev_pid[$id])
		exit("$from: device for modbus$id is not initialized\r\n");

	return $sc_modbus_dev_pid[$id];
}

function sc_mbaptcp_flush($id, $tcp_pid)
{
	global $sc_device_dropcnt;

	$rbuf = "";
	while(($rlen = pid_recv($tcp_pid, $rbuf)) > 0)
		$sc_device_dropcnt[$id] += $rlen;
}

function sc_mbaptcp_ac_start($id)
{
	global $modbus_flags, $sc_modbus_tcp_addr, $sc_modbus_tcp_port;
	global $sc_modbus_ip4_next_tick, $sc_modbus_cod_next_tick;

	if((int)system("net if get state") != 2)
	{
		if($sc_modbus_ip4_next_tick <= sc_modbus_get_tick())
		{
			if($sc_modbus_ip4_next_tick)
				printf("Local IPv4 is NOT ready\r\n");
			$sc_modbus_ip4_next_tick = sc_modbus_get_tick() + 10000;
		}

		return 0;
	}

	$sc_modbus_ip4_next_tick = 0;

	$tcp_pid = sc_modbus_get_pid($id, "sc_mbaptcp_ac_start");

	if(pid_ioctl($tcp_pid, "get rxlen"))
		sc_mbaptcp_flush($id, $tcp_pid);

	if($modbus_flags[$id] & MODBUS_SERVER)
		pid_listen($tcp_pid);
	else
	if($modbus_flags[$id] & MODBUS_CLIENT)
	{
		if($sc_modbus_cod_next_tick[$id] <= sc_modbus_get_tick())
		{
			pid_bind($tcp_pid, "", 0);
			if($sc_modbus_tcp_addr[$id])
				pid_connect($tcp_pid, $sc_modbus_tcp_addr[$id], $sc_modbus_tcp_port[$id]);

			$sc_modbus_cod_next_tick[$id] = sc_modbus_get_tick() + rand(3000,5000);
		}
	}

	return 0;
}

function sc_mbaptcp_reset($id)
{
	global $modbus_flags, $sc_modbus_dev_id, $sc_modbus_tcp_port;

	$tcp_pid = sc_modbus_get_pid($id, "sc_mbaptcp_reset");
	if(pid_ioctl($tcp_pid, "get rxlen"))
		sc_mbaptcp_flush($id, $tcp_pid);
	if($tcp_pid)
		pid_close($tcp_pid);

	$tcp_id = $sc_modbus_dev_id[$id];
	while(($tcp_pid = pid_open("/mmap/tcp$tcp_id", O_NODIE)) == -EBUSY)
		usleep(500);

	pid_ioctl($tcp_pid, "set nodelay 1");

	if($modbus_flags[$id] & MODBUS_SECURE)
	{
		pid_ioctl($tcp_pid, "set api tls");
		if($modbus_flags[$id] & MODBUS_CLIENT)
			pid_ioctl($tcp_pid, "set tls method tls12_client");
		else
			pid_ioctl($tcp_pid, "set tls method tls12_server");
	}
	else
		pid_ioctl($tcp_pid, "set api tcp");

	if($modbus_flags[$id] & MODBUS_SERVER)
		pid_bind($tcp_pid, "", $sc_modbus_tcp_port[$id]);

	sc_mbaptcp_ac_start($id);

	return 0;
}

function sc_modbus_ioctl($id, $cmd)
{
	global $modbus_flags, $sc_device_rxcnt, $sc_device_dropcnt, $sc_device_txcnt;

	$arg = explode(" ", $cmd);

	if(!count($arg))
		return false;

	if($arg[0] == "get")
	{
		if(count($arg) < 2)
			return false;

		if($arg[1] == "count")
		{
			if(count($arg) < 3)
				return false;

			if($arg[2] == "rcvd")
				return $sc_device_rxcnt[$id];
			else
			if($arg[2] == "drop")
				return $sc_device_dropcnt[$id];
			else
			if($arg[2] == "sent")
				return $sc_device_txcnt[$id];
		}
		else
		if($arg[1] == "flags")
			return $modbus_flags[$id];
	}
	else
	if($arg[0] == "set")
	{
		if(count($arg) < 3)
			return false;

		if($arg[1] == "count")
		{
			if($arg[2] == "reset")
			{
				$sc_device_rxcnt[$id] = 0;
				$sc_device_dropcnt[$id] = 0;
				$sc_device_txcnt[$id] = 0;

				return 0;
			}
		}
	}

	return false;
}

function sc_modbus_check_pdu($id, $pdu)
{
	global $modbus_flags;

	if($modbus_flags[$id] & MODBUS_SLAVE)
		$query = true;
	else
		$query = false;

	$plen = strlen($pdu);

	$rcvd_fc = bin2int($pdu, 0, 1);
	if($rcvd_fc & 0x80)
		$vsize = 2;
	else
	{
		$vsize = $plen;
		switch($rcvd_fc)
		{
			case READ_COILS:
			case READ_INPUT_PORTS:
			case READ_HOLDING_REGS:
			case READ_INPUT_REGS:
				if($query)
					$vsize = 5;
				break;
			case WRITE_COIL:
			case WRITE_SINGLE_REG:
				$vsize = 5;
				break;
			case READ_EXCEPTION_STATUS:
				if($query)
					$vsize = 1;
				else
					$vsize = 2;
				break;
			case WRITE_MULTI_COILS:
			case WRITE_MULTIPLE_REGS:
				if($query)
				{
					if($plen > 6)
					{
						$cov = bin2int($pdu, 3, 2, true);
						if($rcvd_fc == WRITE_MULTI_COILS)
						{
							$bc = $cov >> 3;
							if($cov & 0x07)
								$bc++;
						}
						else
							$bc = $cov << 1;
					}
					else
						$bc = 0;
					$vsize = 6 + $bc;
				}
				else
					$vsize = 5;
				break;
			case MEI_TRANSPORT:
				if($query)
					$vsize = 4;
				break;
			case WRITE_PULSE:
				$vsize = 6;
				break;
		}
	}

	if($plen != $vsize)
		return false;

	return true;
}


function modbus_lrc8(&$buf, $len)
{
	$lrc8 = 0;

	if($len <= 0)
		return 0;

	for($i=0;$i<$len;$i++)
		$lrc8 += bin2int($buf, $i, 1);

	return ((~$lrc8 + 1) & 0xff);
}

function modbus_crc16(&$buf, $len)
{
	if($len <= 0)
		return 0;

	return (int)system("crc 16 %1 ffff a001 lsb", substr($buf, 0, $len));
}

function sc_modbus_rtu_rcvd($id, &$buf, $fsize)
{
	if(($fsize < 4) || ($fsize > RTU_MAX_FSIZE))
		return false;

	$crc16 = modbus_crc16($buf, $fsize - 2);
	$rcvd_crc16 = bin2int($buf, $fsize - 2, 2);
	if($crc16 != $rcvd_crc16)
		return false;

	$uid = bin2int($buf, 0, 1);
	if(($uid < 0) || ($uid > 247))
		return false;

	return $fsize;
}

function sc_modbus_ascii_rcvd($id, &$buf, $fsize)
{
	if(!($fsize & 1) || ($fsize < 9) || ($fsize > ASC_MAX_FSIZE))
		return false;

	$msg = hex2bin(substr($buf, 1, -2));
	$msg_len = strlen($msg);

	if(modbus_lrc8($msg, $msg_len - 1) != bin2int($msg, $msg_len - 1, 1))
		return false;

	$uid = bin2int($msg, 0, 1);
	if(($uid < 0) || ($uid > 247))
		return false;

	return $fsize;
}

function sc_modbus_read_xuart($id, &$rbuf, $flags)
{
	global $modbus_flags, $sc_device_dropcnt;

	$eio_pid = sc_modbus_get_pid($id, "sc_modbus_read_xuart");

	$rlen = pid_ioctl($eio_pid, "get rxlen");
	if($rlen > 0)
	{
		if($flags & MODBUS_NO_CHECK)
			$fsize = $rlen;
		else
		{
			$xfer_buf = "";
			if(pid_peek($eio_pid, $xfer_buf, $rlen) <= 0)
				return 0;

			if($modbus_flags[$id] & MODBUS_ASCII)
				$fsize = sc_modbus_ascii_rcvd($id, $xfer_buf, $rlen);
			else
				$fsize = sc_modbus_rtu_rcvd($id, $xfer_buf, $rlen);

			if($fsize === false)
			{
				while(($rlen = pid_read($eio_pid, $xfer_buf)) > 0)
					$sc_device_dropcnt[$id] += $rlen;
				return 0;
			}
			else
			if($fsize > 0)
			{
				if($modbus_flags[$id] & MODBUS_RTU)
					$msg = substr($xfer_buf, 0, -2);
				else
					$msg = hex2bin(substr($xfer_buf, 1, -4));
				if(sc_modbus_check_pdu($id, substr($msg, 1)) === false)
				{
					while(($rlen = pid_read($eio_pid, $xfer_buf)) > 0)
						$sc_device_dropcnt[$id] += $rlen;
					return 0;
				}
			}
		}

		if($rlen >= $fsize)
		{
			if($flags & MODBUS_PEEK)
				return pid_peek($eio_pid, $rbuf, $fsize);
			else
				return pid_read($eio_pid, $rbuf, $fsize);
		}
	}

	return $rlen;
}

function sc_modbus_tcp_rcvd($id, &$rbuf, $flags)
{
	$tcp_pid = sc_modbus_get_pid($id, "sc_modbus_tcp_rcvd");

	$rlen = pid_ioctl($tcp_pid, "get rxlen");
	if($rlen > 0)
	{
		if($flags & MODBUS_NO_CHECK)
			$fsize = $rlen;
		else
		{
			$xfer_buf = "";
			if(pid_peek($tcp_pid, $xfer_buf, 6) < 6)
				return 0;

			if(bin2int($xfer_buf, 2, 2, true))
				return false;

			$fsize = 6 + bin2int($xfer_buf, 4, 2, true);
			if(($fsize < 8) || ($fsize > MBT_MAX_FSIZE))
				return false;
		}

		if($rlen >= $fsize)
		{
			$retval = pid_peek($tcp_pid, $rbuf, $fsize);

			if($retval > 0)
			{
				if(sc_modbus_check_pdu($id, substr($rbuf, 7)) === false)
				{
					sc_mbaptcp_flush($id, $tcp_pid);
					return false;
				}
			}

			if(!($flags & MODBUS_PEEK))
				$retval = pid_recv($tcp_pid, $rbuf, $fsize);

			return $retval;
		}
	}

	return 0;
}

function sc_modbus_read_tcp($id, &$rbuf, $flags)
{
	$retval = sc_modbus_tcp_rcvd($id, $rbuf, $flags);
	if($retval === false)
		sc_mbaptcp_reset($id);

	$tcp_pid = sc_modbus_get_pid($id, "sc_modbus_read_tcp");
	if(pid_ioctl($tcp_pid, "get state") == TCP_CLOSED)
	{
		sc_mbaptcp_ac_start($id);
		$retval = false;
	}

	return $retval;
}

function sc_modbus_rtu_send($id, $eio_pid, &$wbuf, $fsize)
{
	if(($fsize < 4) || ($fsize > RTU_MAX_FSIZE))
	{
		printf("MBAP%d TX: invalid frame length - %d\r\n", $id, $fsize);
		return false;
	}

	$crc16 = modbus_crc16($wbuf, $fsize - 2);
	$send_crc16 = bin2int($wbuf, $fsize - 2, 2);
	if($crc16 != $send_crc16)
	{
		printf("MBAP%d TX: crc16 error: 0x%04x/0x%04x\r\n", $id, $crc16, $send_crc16);
		return false;
	}

	return pid_write($eio_pid, $wbuf, $fsize);
}

function sc_modbus_ascii_send($id, $eio_pid, &$wbuf, $fsize)
{
	if(!($fsize & 1) || ($fsize < 9) || ($fsize > ASC_MAX_FSIZE))
	{
		printf("MBAP%d TX: invalid frame length - %d\r\n", $id, $fsize);
		return false;
	}

	$msg = hex2bin(substr($wbuf, 1, $fsize - 3));

	$lrc8 = modbus_lrc8($msg, strlen($msg) - 1);
	$send_lrc8 = bin2int($msg, strlen($msg) - 1, 1);
	if($lrc8 != $send_lrc8)
	{
		printf("MBAP%d TX: lrc8 error: 0x%02x/0x%02x\r\n", $id, $lrc8, $send_lrc8);
		return false;
	}

	return pid_write($eio_pid, $wbuf, $fsize);
}

function sc_modbus_write_xuart($id, &$wbuf, $wlen)
{
	global $modbus_flags;

	$eio_pid = sc_modbus_get_pid($id, "sc_modbus_write_xuart");

	if($wlen > pid_ioctl($eio_pid, "get txfree"))
	{
		printf("MBAP%d TX: system error: tx buffer is full\r\n", $id);
		return 0;
	}

	if($modbus_flags[$id] & MODBUS_ASCII)
		return sc_modbus_ascii_send($id, $eio_pid, $wbuf, $wlen);
	else
		return sc_modbus_rtu_send($id, $eio_pid, $wbuf, $wlen);
}

function sc_modbus_tcp_send($id, $tcp_pid, &$wbuf, $wlen)
{
	$proto = bin2int($wbuf, 2, 2, true);
	if($proto)
	{
		printf("MBAP%d TX: invalid hdr: protocol id - 0x%04x\r\n", $id, $proto);
		return false;
	}

	$fsize = 6 + bin2int($wbuf, 4, 2, true);
	if(($fsize < 8) || ($fsize > MBT_MAX_FSIZE))
	{
		printf("MBAP%d TX: invalid hdr: length - %d\r\n", $id, $fsize);
		return false;
	}
	
	if($fsize != $wlen)
	{
		printf("MBAP%d TX: invalid frame length - %d, hdr(%d)\r\n", $id, $wlen, $fsize);
		return false;
	}

	if($fsize > pid_ioctl($tcp_pid, "get txfree"))
	{
		printf("MBAP%d TX: system error: tx buffer is full\r\n", $id);
		return 0;
	}

	return pid_send($tcp_pid, $wbuf, $fsize);
}

function sc_modbus_write_tcp($id, &$wbuf, $wlen)
{
	$tcp_pid = sc_modbus_get_pid($id, "sc_modbus_write_tcp");

	$state = pid_ioctl($tcp_pid, "get state");
	if(($state == TCP_CONNECTED) || ($state == SSL_CONNECTED))
		return sc_modbus_tcp_send($id, $tcp_pid, $wbuf, $wlen);
	else
	if($state == TCP_CLOSED)
	{
		sc_mbaptcp_ac_start($id);
		return false;
	}

	return 0;
}

function modbus_ioctl($id, $cmd)
{
	global $modbus_flags;

	sc_modbus_check_id($id, "modbus_ioctl");

	$retval = sc_modbus_ioctl($id, $cmd);
	if($retval === false)
	{
		if(($modbus_flags[$id] & MODBUS_XUART) || ($modbus_flags[$id] & MODBUS_TCP))
		{
			$dev_pid = sc_modbus_get_pid($id, "modbus_ioctl");
			return pid_ioctl($dev_pid, $cmd);
		}
		else
			exit("modbus_ioctl: device type is not configured\r\n");
	}

	return $retval;
}

function modbus_setup($id, $flags)
{
	global $modbus_flags;

	sc_modbus_check_id($id, "modbus_setup");

	$mask = MODBUS_XUART | MODBUS_TCP;
	if(!($flags & $mask) || (($flags & $mask) == $mask))
		exit("modbus_setup: device flag error\r\n");

	$mask = MODBUS_SLAVE | MODBUS_MASTER;
	if(!($flags & $mask) || (($flags & $mask) == $mask))
		exit("modbus_setup: master/slave flag error\r\n");

	if($flags & MODBUS_XUART)
	{
		$mask = MODBUS_RTU | MODBUS_ASCII;
		if(!($flags & $mask) || (($flags & $mask) == $mask))
			exit("modbus_setup: rtu/ascii flag error\r\n");
	}
	else
	if($flags & MODBUS_TCP)
	{
		$mask = MODBUS_SERVER | MODBUS_CLIENT;
		if(!($flags & $mask) || (($flags & $mask) == $mask))
			exit("modbus_setup: server/client flag error\r\n");
	}

	$modbus_flags[$id] = $flags;
}

function modbus_tcp_state($id)
{
	global $modbus_flags, $sc_modbus_cod_next_tick;

	sc_modbus_check_id($id, "modbus_tcp_state");

	if(!($modbus_flags[$id] & MODBUS_TCP))
		exit("modbus_tcp_state: device type of modbus$id is not TCP\r\n");

	$tcp_pid = sc_modbus_get_pid($id, "modbus_tcp_state");

	$tcp_state = pid_ioctl($tcp_pid, "get state");
	if(($tcp_state == TCP_CONNECTED) || ($tcp_state == SSL_CONNECTED))
	{
		if($modbus_flags[$id] & MODBUS_CLIENT)
			$sc_modbus_cod_next_tick[$id] = sc_modbus_get_tick() + rand(3000,5000);
	}
	else
	if($tcp_state == TCP_CLOSED)
		sc_mbaptcp_ac_start($id);

	return $tcp_state;
}

function modbus_tcp($id, $tcp_id = 0, $addr = "", $port = 0)
{
	global $modbus_flags, $sc_modbus_dev_id, $sc_modbus_dev_pid;
	global $sc_modbus_tcp_addr, $sc_modbus_tcp_port;

	sc_modbus_check_id($id, "modbus_tcp");

	if(!($modbus_flags[$id] & MODBUS_TCP))
		exit("modbus_tcp: device type of modbus$id is not TCP\r\n");

	if(($tcp_id < 0) || ($tcp_id > 4))
		exit("modbus_tcp: tcp id out of range $tcp_id\r\n");

	$tcp_pid = $sc_modbus_dev_pid[$id];
	if($tcp_pid)
		pid_close($tcp_pid);

	while(($tcp_pid = pid_open("/mmap/tcp$tcp_id", O_NODIE)) == -EBUSY)
		usleep(500);

	$sc_modbus_dev_id[$id] = $tcp_id;
	$sc_modbus_dev_pid[$id] = $tcp_pid;

	pid_ioctl($tcp_pid, "set nodelay 1");

	if($modbus_flags[$id] & MODBUS_SECURE)
	{
		pid_ioctl($tcp_pid, "set api tls");
		if($modbus_flags[$id] & MODBUS_CLIENT)
			pid_ioctl($tcp_pid, "set tls method client");
		else
			pid_ioctl($tcp_pid, "set tls method server");
	}
	else
		pid_ioctl($tcp_pid, "set api tcp");

	if($addr && (inet_pton($addr) === false))
	{
		$sc_modbus_tcp_addr[$id] = "";
		printf("MBAP%d: invalid peer address\r\n", $id);
	}
	else
		$sc_modbus_tcp_addr[$id] = $addr;
	if($port)
		$sc_modbus_tcp_port[$id] = $port;
	else
	{
		if($modbus_flags[$id] & MODBUS_SECURE)
			$sc_modbus_tcp_port[$id] = 802;
		else
			$sc_modbus_tcp_port[$id] = 502;
	}

	if($modbus_flags[$id] & MODBUS_SERVER)
		pid_bind($tcp_pid, "", $sc_modbus_tcp_port[$id]);
	else
	if($modbus_flags[$id] & MODBUS_CLIENT)
	{
		if(!$sc_modbus_tcp_addr[$id])
			printf("MBAP%d: NO peer address\r\n", $id);
	}

	sc_mbaptcp_ac_start($id);
}

function modbus_uart($id, $eio_id = 0, $baud = 19200, $set = "E81N")
{
	global $modbus_flags, $sc_modbus_dev_id, $sc_modbus_dev_pid;

	sc_modbus_check_id($id, "modbus_uart");

	if(!($modbus_flags[$id] & MODBUS_XUART))
		exit("modbus_uart: device type of modbus$id is not XUART\r\n");

	if($eio_id)
		exit("modbus_uart: dev-id out of range $eio_id\r\n");

	$eio_pid = $sc_modbus_dev_pid[$id];
	if($eio_pid)
		pid_close($eio_pid);

	while(($eio_pid = pid_open("/mmap/xuart$eio_id", O_NODIE)) == -EBUSY)
		usleep(500);

	$sc_modbus_dev_id[$id] = $eio_id;
	$sc_modbus_dev_pid[$id] = $eio_pid;

	pid_ioctl($eio_pid, "set uart $baud$set");
	if($modbus_flags[$id] & MODBUS_ASCII)
		pid_ioctl($eio_pid, "set ifd 3a 0d0a");
	else
		pid_ioctl($eio_pid, "set ifg 40");
}

function modbus_read($id, &$rbuf, $flags = 0)
{
	global $modbus_flags, $sc_device_rxcnt;

	sc_modbus_check_id($id, "modbus_read");

	if($modbus_flags[$id] & MODBUS_XUART)
		$retval = sc_modbus_read_xuart($id, $rbuf, $flags);
	else
	if($modbus_flags[$id] & MODBUS_TCP)
		$retval = sc_modbus_read_tcp($id, $rbuf, $flags);
	else
		exit("modbus_read: device type of id$id is not initialized\r\n");

	if(((int)$retval > 0) && !($modbus_flags[$id] & MODBUS_PEEK))
		$sc_device_rxcnt[$id] += (int)$retval;

	return $retval;
}

function modbus_write($id, &$wbuf, $wlen = MAX_STRING_LEN)
{
	global $modbus_flags, $sc_device_txcnt;

	sc_modbus_check_id($id, "modbus_write");

	$max_len = strlen($wbuf);

	if($wlen > $max_len)
		$wlen = $max_len;

	if(!$wlen)
		return 0;

	if($modbus_flags[$id] & MODBUS_XUART)
		$retval = sc_modbus_write_xuart($id, $wbuf, $wlen);
	else
	if($modbus_flags[$id] & MODBUS_TCP)
		$retval = sc_modbus_write_tcp($id, $wbuf, $wlen);
	else
		exit("modbus_write: device type of id$id is not initialized\r\n");

	if((int)$retval > 0)
		$sc_device_txcnt[$id] += (int)$retval;

	return $retval;
}

?>
