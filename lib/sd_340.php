<?php

// $psp_id sd_340.php date 20170314
// P4S-340/342 basic library

define("LOW",    0);
define("HIGH",   1);
define("TOGGLE", 2);

function pid_open_nodie($name, $from)
{
	while(($pid = pid_open($name, O_NODIE)) < 0)
	{
		if($pid == -EBUSY)
			usleep(500);
		else
		if($pid == -ENOENT)
			exit("$from: $name - file not found\r\n");
		else
			exit("$from: pid_open error $pid\r\n");
	}

	return $pid;
}

function uio_check_args($uio_id, $pin, $from)
{
	if(($uio_id == 0) && ($pin >= 0) && ($pin <= 31))
		return;

	if($uio_id != 0)
		exit("$from: uio_id out of range $uio_id\r\n");

	if(($pin < 0) || ($pin > 31))
		exit("$from: pin number out of range $pin\r\n");
}

function uio_ioctl($uio_id, $cmd)
{
	if($uio_id != 0)
		exit("uio_ioctl: uio_id out of range $uio_id\r\n");

	$pid = pid_open_nodie("/mmap/uio$uio_id", "uio_ioctl");

	$retval = pid_ioctl($pid, $cmd);

	pid_close($pid);

	return $retval;
}

function uio_setup($uio_id, $pin, $mode)
{
	uio_check_args($uio_id, $pin, "uio_setup");

	$pid = pid_open_nodie("/mmap/uio$uio_id", "uio_setup");

	pid_ioctl($pid, "set $pin mode $mode");

	pid_close($pid);
}

function uio_out($uio_id, $pin, $type)
{
	uio_check_args($uio_id, $pin, "uio_out");

	$pid = pid_open_nodie("/mmap/uio$uio_id", "uio_out");

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
			exit("uio_out: invalid output $type\r\n");
			break;
	}

	pid_close($pid);
}

function uio_in($uio_id, $pin)
{
	uio_check_args($uio_id, $pin, "uio_in");

	$pid = pid_open_nodie("/mmap/uio$uio_id", "uio_in");

	$in = pid_ioctl($pid, "get $pin input");

	pid_close($pid);

	return $in;
}

function adc_setup($adc_id, $ch)
{
	if(($adc_id < 0) || ($adc_id > 1))
		exit("adc_setup: adc_id out of range $adc_id\r\n");

	if(($ch < 0) || ($ch > 5))
		exit("adc_setup: channel number out of range $ch\r\n");

	$pid = pid_open_nodie("/mmap/adc$adc_id", "adc_setup");

	pid_ioctl($pid, "set ch $ch");

	pid_close($pid);
}

function adc_in($adc_id, $sc = 1)
{
	if(($adc_id < 0) || ($adc_id > 1))
		exit("adc_in: adc_id out of range $adc_id\r\n");

	if(($sc < 1) || ($sc > 100))
		exit("adc_in: sample count out of range $sc\r\n");

	$pid = pid_open_nodie("/mmap/adc$adc_id", "adc_in");

	$adc = 0;
	$adc_sum = 0;

	for($i = 0; $i < $sc; $i++)
	{
		pid_read($pid, $adc);
		$adc_sum += $adc;
	}

	pid_close($pid);

	return $adc_sum / $sc;
}

function uart_setup($uart_id, $baud, $set = "N81N")
{
	if(($uart_id < 0) || ($uart_id > 1))
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
			break;
		case "H": /* H/W flow control */
			$flowctrl = 1;
			break;
		case "S": /* S/W flow control */
			$flowctrl = 2;
			break;
		case "T": /* TxDE flow control */
			$flowctrl = 3;
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
	if(($uart_id < 0) || ($uart_id > 1))
		exit("uart_read: uart_id out of range $uart_id\r\n");

	$pid = pid_open_nodie("/mmap/uart$uart_id", "uart_read");

	$retval = pid_read($pid, $rbuf, $rlen);

	pid_close($pid);

	return $retval;
}

function uart_readn($uart_id, &$rbuf, $rlen)
{
	if(($uart_id < 0) || ($uart_id > 1))
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
	if(($uart_id < 0) || ($uart_id > 1))
		exit("uart_write: uart_id out of range $uart_id\r\n");

	$pid = pid_open_nodie("/mmap/uart$uart_id", "uart_write");

	if(is_string($wbuf))
		$max_len = strlen($wbuf);
	else
		$max_len = 8;

	if($wlen > $max_len)
		$wlen = $max_len;

	if($wlen)
	{
	 	if(pid_ioctl($pid, "get txfree") >= $wlen)
			$retval = pid_write($pid, $wbuf, $wlen);
		else
			exit("uart_write: tx buffer full\r\n");
	}
	else
		$retval = 0;

	pid_close($pid);

	return $retval;
}

function uart_txfree($uart_id)
{
	if(($uart_id < 0) || ($uart_id > 1))
		exit("uart_txfree: uart_id out of range $uart_id\r\n");

	$pid = pid_open_nodie("/mmap/uart$uart_id", "uart_txfree");

	$retval = pid_ioctl($pid, "get txfree");

	pid_close($pid);

	return $retval;
}

function spi_ioctl($spi_id, $cmd)
{
	if($spi_id != 0)
		exit("spi_ioctl: spi_id out of range $spi_id\r\n");

	$pid = pid_open_nodie("/mmap/spi$spi_id", "spi_ioctl");

	$retval = pid_ioctl($pid, $cmd);

	pid_close($pid);

	return $retval;
}

function spi_setup($spi_id, $div = 256, $mode = 3)
{
	if($spi_id != 0)
		exit("spi_setup: spi_id out of range $spi_id\r\n");

	$pid = pid_open_nodie("/mmap/spi$spi_id", "spi_setup");

	pid_ioctl($pid, "set div $div");
	pid_ioctl($pid, "set mode $mode");

	pid_close($pid);
}

function spi_read($spi_id, &$rbuf, $rlen)
{
	if($spi_id != 0)
		exit("spi_read: spi_id out of range $spi_id\r\n");

	$pid = pid_open_nodie("/mmap/spi$spi_id", "spi_read");

	if($rlen)
	{
		if(pid_ioctl($pid, "get txfree") >= $rlen)
			$retval = pid_write($pid, str_repeat("\x00", $rlen), $rlen);
		else
			exit("spi_read: tx buffer full\r\n");
	}
	else
		$retval = 0;

	$rbuf = "";

	if($retval)
	{
		pid_ioctl($pid, "req start");

		while(pid_ioctl($pid, "get txlen"))
			;

		if(pid_read($pid, $rbuf) != $rlen)
			exit("spi_read: read length mismatch\r\n");
	}

	pid_close($pid);

	return $retval;
}

function spi_write($spi_id, $wbuf, $wlen = MAX_STRING_LEN)
{
	if($spi_id != 0)
		exit("spi_write: spi_id out of range $spi_id\r\n");

	$pid = pid_open_nodie("/mmap/spi$spi_id", "spi_write");

	if(is_string($wbuf))
		$max_len = strlen($wbuf);
	else
		$max_len = 8;

	if($wlen > $max_len)
		$wlen = $max_len;

	if($wlen)
	{
		if(pid_ioctl($pid, "get txfree") >= $wlen)
			$retval = pid_write($pid, $wbuf, $wlen);
		else
			exit("spi_write: tx buffer full\r\n");
	}
	else
		$retval = 0;

	if($retval)
	{
		pid_ioctl($pid, "req start");

		while(pid_ioctl($pid, "get txlen"))
			;

		$rbuf = "";

		pid_read($pid, $rbuf); // drop loop-back data
	}

	pid_close($pid);

	return $retval;
}

function spi_write_read($spi_id, $wbuf, &$rbuf, $rlen)
{
	if(!$rlen)
		return spi_write($spi_id, $wbuf);

	if(!is_string($wbuf))
		exit("spi_write_read: only string can be used for wbuf\r\n");

	$wlen = strlen($wbuf);

	if(!$wlen)
		return spi_read($spi_id, $rbuf, $rlen);

	if($spi_id != 0)
		exit("spi_write_read: spi_id out of range $spi_id\r\n");

	$pid = pid_open_nodie("/mmap/spi$spi_id", "spi_write_read");

	if(pid_ioctl($pid, "get txfree") >= ($wlen + $rlen))
	{
		pid_write($pid, $wbuf, $wlen);
		pid_write($pid, str_repeat("\x00", $rlen), $rlen);
	}
	else
		exit("spi_write_read: tx buffer full\r\n");

	$rbuf = "";

	pid_ioctl($pid, "req start");

	while(pid_ioctl($pid, "get txlen"))
		;

	pid_read($pid, $rbuf, $wlen); // drop loop-back data

	$retval = pid_read($pid, $rbuf);

	pid_close($pid);

	if($retval != $rlen)
		exit("spi_write_read: read length mismatch $retval $rlen\r\n");

	return $retval;
}

function i2c_ioctl($i2c_id, $cmd)
{
	if($i2c_id != 0)
		exit("i2c_ioctl: i2c_id out of range $i2c_id\r\n");

	$pid = pid_open_nodie("/mmap/i2c$i2c_id", "i2c_ioctl");

	$retval = pid_ioctl($pid, $cmd);

	pid_close($pid);

	return $retval;
}

function i2c_setup($i2c_id, $saddr, $mode = "sm")
{
	if($i2c_id != 0)
		exit("i2c_setup: i2c_id out of range $i2c_id\r\n");

	$pid = pid_open_nodie("/mmap/i2c$i2c_id", "i2c_setup");

	$saddr = sprintf("%02x", $saddr << 1);

	pid_ioctl($pid, "set saddr $saddr");
	pid_ioctl($pid, "set mode $mode");

	pid_close($pid);
}

function i2c_scan($i2c_id, $rw_bit = 1, $len = 0)
{
	if($i2c_id != 0)
		exit("i2c_scan: i2c_id out of range $i2c_id\r\n");

	$pid = pid_open_nodie("/mmap/i2c$i2c_id", "i2c_scan");

	pid_ioctl($pid, "req reset");

	echo "i2c_scan: ";

	$found = 0;

	for($addr = 0x10; $addr < 0xf0; $addr += 2)
	{
		$hex_addr = sprintf("%02x", $addr);

		pid_ioctl($pid, "set saddr $hex_addr");

		if($rw_bit)
		{
		 	// WARNING : some slave devices hold SDA if zero length read requested
			pid_ioctl($pid, "req read $len");
		}
		else
		{
			if($len)
				pid_write($pid, str_repeat("\x00", $len), $len);

			pid_ioctl($pid, "req write");
		}

		while(pid_ioctl($pid, "get state"))
			;

		if(!pid_ioctl($pid, "get error"))
		{
			printf("0x%02x/0x%02x ", $addr, $addr >> 1);
			$found++;
		}
	}

	if($found)
		echo "\r\n";
	else
		echo "none\r\n";

	pid_close($pid);

	return $found;
}

function i2c_read($i2c_id, &$rbuf, $rlen)
{
	if($i2c_id != 0)
		exit("i2c_read: i2c_id out of range $i2c_id\r\n");

	$pid = pid_open_nodie("/mmap/i2c$i2c_id", "i2c_read");

	pid_ioctl($pid, "req read $rlen");
	while(pid_ioctl($pid, "get state"))
		;

	$retval = pid_read($pid, $rbuf);

	pid_close($pid);

	return $retval;
}

function i2c_write($i2c_id, $wbuf, $wlen = MAX_STRING_LEN)
{
	if($i2c_id != 0)
		exit("i2c_write: i2c_id out of range $i2c_id\r\n");

	$pid = pid_open_nodie("/mmap/i2c$i2c_id", "i2c_write");

	if(is_string($wbuf))
		$max_len = strlen($wbuf);
	else
		$max_len = 8;

	if($wlen > $max_len)
		$wlen = $max_len;

	if($wlen)
	{
		if(pid_ioctl($pid, "get txfree") >= $wlen)
			$retval = pid_write($pid, $wbuf, $wlen);
		else
			exit("i2c_write: tx buffer full\r\n");
	}
	else
		$retval = 0;

	pid_ioctl($pid, "req write");
	while(pid_ioctl($pid, "get state"))
		;

	pid_close($pid);

	return $retval;
}

function i2c_write_read($i2c_id, $wbuf, &$rbuf, $rlen)
{
	if(!$rlen)
		return i2c_write($i2c_id, $wbuf);

	if(!is_string($wbuf))
		exit("i2c_write_read: only string can be used for wbuf\r\n");

	$wlen = strlen($wbuf);

	if(!$wlen)
		return i2c_read($i2c_id, $rbuf, $rlen);

	if($i2c_id != 0)
		exit("i2c_write_read: i2c_id out of range $i2c_id\r\n");

	$pid = pid_open_nodie("/mmap/i2c$i2c_id", "i2c_write_read");

	if(pid_ioctl($pid, "get txfree") >= $wlen)
		pid_write($pid, $wbuf, $wlen);
	else
		exit("i2c_write_read: tx buffer full\r\n");

	pid_ioctl($pid, "req write wait");
	while(pid_ioctl($pid, "get state") && pid_ioctl($pid, "get txlen"))
		;

	if(pid_ioctl($pid, "get error"))
	{
		pid_ioctl($pid, "req stop");
		pid_close($pid);
		return 0;
	}
	else
	{
		pid_close($pid);
		return i2c_read($i2c_id, $rbuf, $rlen);
	}
}

function ht_ioctl($ht_id, $cmd)
{
	if(($ht_id < 0) || ($ht_id > 3))
		exit("ht_ioctl: ht_id out of range $ht_id\r\n");

	$pid = pid_open_nodie("/mmap/ht$ht_id", "ht_ioctl");

	$retval = pid_ioctl($pid, $cmd);

	pid_close($pid);

	return $retval;
}

function ht_pwm_setup($ht_id, $width, $period, $div = "us")
{
	if(($ht_id < 0) || ($ht_id > 3))
		exit("ht_pwm_setup: ht_id out of range $ht_id\r\n");

	$pid = pid_open_nodie("/mmap/ht$ht_id", "ht_pwm_setup");

	$c2 = $period - $width;

	pid_ioctl($pid, "reset");
	pid_ioctl($pid, "set div $div");
	pid_ioctl($pid, "set mode output pwm");
	pid_ioctl($pid, "set count $width $c2");
	pid_ioctl($pid, "start");

	pid_close($pid);
}

function ht_pwm_width($ht_id, $width, $period)
{
	if(($ht_id < 0) || ($ht_id > 3))
		exit("ht_pwm_width: ht_id out of range $ht_id\r\n");

	$pid = pid_open_nodie("/mmap/ht$ht_id", "ht_pwm_width");

	$c2 =  $period - $width;

	pid_ioctl($pid, "set count $width $c2");

	pid_close($pid);
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

function st_pwm_setup($st_id, $pin, $width, $period, $div = "ms")
{
	if(($st_id < 0) || ($st_id > 7))
		exit("st_pwm_setup: st_id out of range $st_id\r\n");

	$pid = pid_open_nodie("/mmap/st$st_id", "st_pwm_setup");

	$c2 = $period - $width;

	pid_ioctl($pid, "reset");
	pid_ioctl($pid, "set div $div");
	pid_ioctl($pid, "set mode output pwm");
	pid_ioctl($pid, "set output dev uio0 $pin");
	pid_ioctl($pid, "set count $width $c2");
	pid_ioctl($pid, "start");

	pid_close($pid);
}

function st_pwm_width($st_id, $width, $period)
{
	if(($st_id < 0) || ($st_id > 7))
		exit("st_pwm_width: st_id out of range $st_id\r\n");

	$pid = pid_open_nodie("/mmap/st$st_id", "st_pwm_width");

	$c2 = $period - $width;

	pid_ioctl($pid, "set count $width $c2");

	pid_close($pid);
}

function um_read($um_id, $offset, &$rbuf, $rlen)
{
	if(($um_id < 0) || ($um_id > 3))
		exit("um_read: um_id out of range $um_id\r\n");

	$pid = pid_open_nodie("/mmap/um$um_id", "um_read");

	if($offset + $rlen > pid_lseek($pid, 0, SEEK_END))
	{
		pid_close($pid);
		exit("um_read: over-range um$um_id file offset\r\n");
	}

	pid_lseek($pid, $offset, SEEK_SET);
	$rlen = pid_read($pid, $rbuf, $rlen);

	pid_close($pid);

	return $rlen;
}

function um_write($um_id, $offset, $wbuf, $wlen = MAX_STRING_LEN)
{
	if(($um_id < 0) || ($um_id > 3))
		exit("um_write: um_id out of range $um_id\r\n");

	$pid = pid_open_nodie("/mmap/um$um_id", "um_write");

	if(is_string($wbuf))
		$max_len = strlen($wbuf);
	else
		$max_len = 8;

	if($wlen > $max_len)
		$wlen = $max_len;

	if($offset + $wlen > pid_lseek($pid, 0, SEEK_END))
	{
		pid_close($pid);
		exit("um_write: over-range um$um_id file offset\r\n");
	}

	if($wlen)
	{
		pid_lseek($pid, $offset, SEEK_SET);
		$retval = pid_write($pid, $wbuf, $wlen);
	}
	else
		$retval = 0;

	pid_close($pid);

	return $retval;
}

function nm_read($nm_id, $offset, &$rbuf, $rlen)
{
	if(($nm_id < 0) || ($nm_id > 1))
		exit("nm_read: nm_id out of range $nm_id\r\n");

	$pid = pid_open_nodie("/mmap/nm$nm_id", "nm_read");

	if($offset + $rlen > pid_lseek($pid, 0, SEEK_END))
	{
		pid_close($pid);
		exit("nm_read: over-range nm$nm_id file offset\r\n");
	}

	pid_lseek($pid, $offset, SEEK_SET);
	$rlen = pid_read($pid, $rbuf, $rlen);

	pid_close($pid);

	return $rlen;
}

function nm_write($nm_id, $offset, $wbuf, $wlen = MAX_STRING_LEN)
{
	if(($nm_id < 0) || ($nm_id > 1))
		exit("nm_write: nm_id out of range $nm_id\r\n");

	$pid = pid_open_nodie("/mmap/nm$nm_id", "nm_write");

	if(is_string($wbuf))
		$max_len = strlen($wbuf);
	else
		$max_len = 8;

	if($wlen > $max_len)
		$wlen = $max_len;

	if($offset + $wlen > pid_lseek($pid, 0, SEEK_END))
	{
		pid_close($pid);
		exit("nm_write: over-range nm$nm_id file offset\r\n");
	}

	if($wlen)
	{
		pid_lseek($pid, $offset, SEEK_SET);
		$retval = pid_write($pid, $wbuf, $wlen);
	}
	else
		$retval = 0;

	pid_close($pid);

	return $retval;
}

?>
