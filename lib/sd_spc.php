<?php

// $psp_id sd_spc.php date 20170216

function sd_spc_pid_open_nodie($name, $from)
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

function spc_reset($t1 = 10, $t2 = 500)
{
	$pid = sd_spc_pid_open_nodie("/mmap/spc0", "spc_reset");

	pid_ioctl($pid, "reset $t1 $t2");

	while(pid_ioctl($pid, "get state"))
		usleep(1);

	pid_close($pid);
}

function spc_sync_baud($baud = 115200, $t1 = 1, $t2 = 100)
{
	$pid = sd_spc_pid_open_nodie("/mmap/spc0", "spc_sync_baud");

	pid_ioctl($pid, "set baud $baud");
	pid_ioctl($pid, "sync baud $t1 $t2");

	while(pid_ioctl($pid, "get state"))
		usleep(1);

	pid_close($pid);
}

function spc_decrypt_uid($uid)
{
	$uid = hex2bin($uid);

	if(strlen($uid) != 12)
		return "";

	$uid32  = bin2int($uid, 0, 4);
	$uid32s = bin2int($uid, 0, 4, true);

	$uid64_0 = bin2int($uid, 4, 4);
	$uid64_1 = bin2int($uid, 8, 4);

	$uid64_0 = $uid64_0 ^ $uid32 ^ $uid32s;
	$uid64_1 = $uid64_1 ^ $uid32 ^ $uid32s;

	$uid = int2bin($uid32, 4) . int2bin($uid64_0, 4) . int2bin($uid64_1, 4);
	$crc8 = bin2int($uid, 11, 1);

	if($crc8 == (int)system("crc 8 %1 f3 07 msb", substr($uid, 0, 11)))
		return $uid;
	else
		return "";
}

function spc_scan($start = 1, $end = 14, $verbose = false)
{
	$pid = sd_spc_pid_open_nodie("/mmap/spc0", "spc_scan");

	$rbuf = "";
	$found = 0;

	if($start < 1)
		$start = 1;

	if($start > 14)
		$start = 14;

	if($end < 1)
		$end = 1;

	if($end > 14)
		$end = 14;

	if($start > $end)
	{
		$tmp = $start;
		$start = $end;
		$end = $tmp;
	}

	pid_ioctl($pid, "sets $start-$end crc 1");

	for($sid = $start; $sid <= $end; $sid++)
	{
		pid_write($pid, "get uid");
		pid_ioctl($pid, "spc $sid 0");

		while(pid_ioctl($pid, "get state"))
			;

		usleep(20000); // wait response from duplicated sid slave

		if(pid_ioctl($pid, "get error"))
		{
			if(pid_ioctl($pid, "get error sto"))
				continue; // slave timeout

			printf("sid%02d : ", $sid);

			if(pid_ioctl($pid, "get error mbit"))
				echo "Mbit error ";

			if(pid_ioctl($pid, "get error csum"))
				echo "csum mismatch ";

			if(pid_ioctl($pid, "get error urg"))
				echo "Ubit error ";

			if(pid_ioctl($pid, "get error sid"))
				echo "sid mismatch ";

			if(pid_ioctl($pid, "get error addr"))
				echo "address mismatch ";

			echo "\r\n";
		}
		else
		{
			pid_read($pid, $rbuf);

			$resp = explode(",", $rbuf);

			if(count($resp) == 2)
			{
				$uid_hex = $resp[1];

				if(strlen($uid_hex) != 24)
					$uid_hex = "";
			}
			else
				$uid_hex = "";

			if($uid_hex)
			{
				$uid_bin = spc_decrypt_uid($uid_hex);

				if($uid_bin)
				{
					pid_write($pid, "get did");
					pid_ioctl($pid, "spc $sid 0");

					while(pid_ioctl($pid, "get state"))
						;

					pid_read($pid, $rbuf);

					$resp = explode(",", $rbuf);

					if($verbose)
						printf("sid%02d : %s %12x\r\n", $sid, $resp[2], bin2int($uid_bin, 5, 6, true));

					$found++;
				}
				else
				{
					if($verbose)
						printf("sid%02d : invalid uid\r\n", $sid);
				}
			}
			else
			{
				if($verbose)
					printf("sid%02d : invalid 'get uid' response\r\n", $sid);
			}
		}
	}

	pid_ioctl($pid, "sets $start-$end crc 0");

	pid_close($pid);

	return $found;
}

function spc_ioctl($cmd)
{
	$pid = sd_spc_pid_open_nodie("/mmap/spc0", "spc_ioctl");

	$retval = pid_ioctl($pid, $cmd);

	pid_close($pid);

	return $retval;
}

function spc_request($sid, $addr, $msg, $opt = "")
{
	$pid = sd_spc_pid_open_nodie("/mmap/spc0", "spc_request");

	pid_write($pid, $msg);
	pid_ioctl($pid, "spc $sid $addr $opt");

	while(pid_ioctl($pid, "get state"))
		;

	if(pid_ioctl($pid, "get error"))
	{
		echo "spc_request : $msg - $sid/$addr ";

		if(pid_ioctl($pid, "get error sto"))
			echo "slave timeout ";

		if(pid_ioctl($pid, "get error mbit"))
			echo "Mbit error ";

		if(pid_ioctl($pid, "get error csum"))
			echo "csum mismatch ";

		if(pid_ioctl($pid, "get error urg"))
			echo "Ubit error ";

		if(pid_ioctl($pid, "get error sid"))
			echo "sid mismatch ";

		if(pid_ioctl($pid, "get error addr"))
			echo "address mismatch ";

		echo "\r\n";

		$rbuf = false;
	}
	else
	{
		$rbuf = "";
		pid_read($pid, $rbuf);
	}

	pid_close($pid);

	return $rbuf;
}

function spc_request_csv($sid, $addr, $msg)
{
	$resp = spc_request($sid, $addr, $msg);

	if($resp)
		return explode(",", $resp);
	else
		return false;
}

?>
