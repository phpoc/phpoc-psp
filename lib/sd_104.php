<?php

// $psp_id sd_104.php date 20160308
// PBH-104 basic library

define("LED_A", 0);
define("LED_B", 1);
define("LED_C", 2);
define("LED_D", 3);
define("LED_E", 4);
define("LED_F", 5);
define("LED_G", 6);
define("LED_H", 7);

define("LOW",    0);
define("HIGH",   1);
define("TOGGLE", 2);

function pid_open_nodie($file, $func)
{
	while(($pid = pid_open($file, O_NODIE)) < 0)
	{
		if($pid == -EBUSY)
			usleep(500);
		else
		if($pid == -ENOENT)
			exit("$func: $file - file not found\r\n");
		else
			exit("$func: pid_open error $pid\r\n");
	}

	return $pid;
}

function led_setup($pin, $mode)
{
	if(($pin < LED_A) || ($pin > LED_H))
		exit("led_setup: pin number out of range $pin\r\n");

	$pid = pid_open_nodie("/mmap/io3", "led_setup");

	if($pin > 3)
		$pin += 8;

	pid_ioctl($pid, "set $pin mode $mode");

	pid_close($pid);
}

function led_out($pin, $type)
{
	if(($pin < LED_A) || ($pin > LED_H))
		exit("led_out: pin number out of range $pin\r\n");

	$pid = pid_open_nodie("/mmap/io3", "led_out");

	if($pin > 3)
		$pin += 8;

	if(!pid_ioctl($pid, "get $pin mode"))
		pid_ioctl($pid, "set $pin mode out");

	switch($type)
	{
		case LOW:
			pid_ioctl($pid, "set $pin output low");
			break;
		case HIGH:
			pid_ioctl($pid, "set $pin output high");
			break;
		case TOGGLE:
			pid_ioctl($pid, "set $pin output toggle");
			break;
		default:
			exit("led_out: invalid output $type\r\n");
			break;
	}

	pid_close($pid);
}

function led_in($pin)
{
	if(($pin < LED_A) || ($pin > LED_H))
		exit("led_in: pin number out of range $pin\r\n");

	$pid = pid_open_nodie("/mmap/io3", "led_in");

	if($pin > 3)
		$pin += 8;

	$in = pid_ioctl($pid, "get $pin input");

	pid_close($pid);

	return $in;
}

function uart_setup_driver($uart_id, $set)
{
	if(($set != 0x02) && ($set != 0x05) && ($set != 0x0c))
		exit("uart_setup_driver: invalid set value $set\r\n");

	$pid = pid_open_nodie("/mmap/io4", "uart_setup_driver");

	$pin_start = $uart_id * 4;
	$pin_end = $uart_id * 4 + 3;
	pid_ioctl($pid, "set $pin_start-$pin_end mode out");

	$io16 = 0;
	pid_read($pid, $io16);

	$io16 &= ~(0xf << $uart_id * 4);
	pid_write($pid, $io16 | ($set << ($uart_id * 4)));

	pid_close($pid);
}

function uart_setup($uart_id, $baud, $set = "N81N")
{
	if(($uart_id < 0) || ($uart_id > 3))
		exit("uart_setup: uart_id out of range $uart_id\r\n");

	$pid = pid_open_nodie("/mmap/uart$uart_id", "uart_setup");

	pid_ioctl($pid, "set baud $baud");

	$set = strtoupper($set);

	if(strlen($set) > 0)
		$parity = substr($set, 0, 1);
	else
		$parity = "N";

	if(strlen($set) > 1)
		$data = substr($set, 1, 1);
	else
		$data = "8";

	if(strlen($set) > 2)
		$stop = substr($set, 2, 1);
	else
		$stop = "1";

	if(strlen($set) > 3)
		$flowctrl = substr($set, 3, 1);
	else
		$flowctrl = "N";

	switch($parity)
	{
		case "N":
			$parity = 0;
			break;
		case "E":
			$parity = 1;
			break;
		case "O":
			$parity = 2;
			break;
		case "M":
			$parity = 3;
			break;
		case "S":
			$parity = 4;
			break;
		default:
			exit("uart_setup: invalid parity $parity\r\n");
			break;
	}
	pid_ioctl($pid, "set parity $parity");

	switch($data)
	{
		case "7":
			break;
		case "8":
			break;
		default:
			exit("uart_setup: invalid data bits $data\r\n");
			break;
	}
	pid_ioctl($pid, "set data $data");

	switch($stop)
	{
		case "1":
			break;
		case "2":
			break;
		default:
			exit("uart_setup: invalid stop bits $stop\r\n");
			break;
	}
	pid_ioctl($pid, "set stop $stop");

	switch($flowctrl)
	{
		case "N":
			$flowctrl = 0;
			uart_setup_driver($uart_id, 0x05);
			break;
		case "H": /* H/W flow control */
			$flowctrl = 1;
			uart_setup_driver($uart_id, 0x05);
			break;
		case "S": /* S/W flow control */
			$flowctrl = 2;
			uart_setup_driver($uart_id, 0x05);
			break;
		case "D": /* Differential Drive */
			$flowctrl = 3;
			uart_setup_driver($uart_id, 0x02);
			break;
		case "T": /* Differential Drive - TxDE flow control */
			$flowctrl = 3;
			uart_setup_driver($uart_id, 0x0c);
			break;
		default:
			exit("uart_setup: invalid flow control $flowctrl\r\n");
			break;
	}
	pid_ioctl($pid, "set flowctrl $flowctrl");

	pid_close($pid);
}

function uart_read($uart_id, &$rbuf, $rlen = MAX_STRING_LEN)
{
	if(($uart_id < 0) || ($uart_id > 3))
		exit("uart_read: uart_id out of range $uart_id\r\n");

	$pid = pid_open_nodie("/mmap/uart$uart_id", "uart_read");

	$retval = pid_read($pid, $rbuf, $rlen);

	pid_close($pid);

	return $retval;
}

function uart_readn($uart_id, &$rbuf, $rlen)
{
	if(($uart_id < 0) || ($uart_id > 3))
		exit("uart_readn: uart_id out of range $uart_id\r\n");

	$pid = pid_open_nodie("/mmap/uart$uart_id", "uart_readn");

	$len = pid_ioctl($pid, "get rxlen");

	if($len && ($len >= $rlen))
		$len =  pid_read($pid, $rbuf, $rlen);
	else
		$len = 0;

	pid_close($pid);

	return $len;
}

function uart_write($uart_id, $wbuf, $wlen = MAX_STRING_LEN)
{
	if(($uart_id < 0) || ($uart_id > 3))
		exit("uart_write: uart_id out of range $uart_id\r\n");

	$pid = pid_open_nodie("/mmap/uart$uart_id", "uart_write");

	if(is_string($wbuf))
		$max_len = strlen($wbuf);
	else
		$max_len = 8;

	if($wlen > $max_len)
		$wlen = $max_len;

	if($wlen && (pid_ioctl($pid, "get txfree") >= $wlen))
		$retval = pid_write($pid, $wbuf, $wlen);
	else
		$retval = 0;

	pid_close($pid);

	return $retval;
}

function uart_txfree($uart_id)
{
	if(($uart_id < 0) || ($uart_id > 3))
		exit("uart_txfree: uart_id out of range $uart_id\r\n");

	$pid = pid_open_nodie("/mmap/uart$uart_id", "uart_txfree");

	$retval = pid_ioctl($pid, "get txfree");

	pid_close($pid);

	return $retval;
}

function st_ioctl($st_id, $cmd)
{
	if(($st_id < 0) || ($st_id > 7))
		exit("st_ioctl: st_id out of range $st_id\r\n");

	$pid = pid_open_nodie("/mmap/st$st_id", "st_ioctl");

	$retval = pid_ioctl($pid, $cmd);

	pid_close($pid);

	return $retval;
}

function st_free_setup($st_id, $div = "ms")
{
	if(($st_id < 0) || ($st_id > 7))
		exit("st_free_setup: st_id out of range $st_id\r\n");

	$pid = pid_open_nodie("/mmap/st$st_id", "st_free_setup");

	pid_ioctl($pid, "reset");
	pid_ioctl($pid, "set div $div");
	pid_ioctl($pid, "start");

	pid_close($pid);
}

function st_free_get_count($st_id)
{
	if(($st_id < 0) || ($st_id > 7))
		exit("st_free_get_count: st_id out of range $st_id\r\n");

	$pid = pid_open_nodie("/mmap/st$st_id", "st_free_get_count");

	if(!pid_ioctl($pid, "get state"))
		pid_ioctl($pid, "start");

	$tick = pid_ioctl($pid, "get count");

	pid_close($pid);

	return $tick;
}

?>
