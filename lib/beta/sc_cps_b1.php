<?php

// $psp_id sc_cps.php date 20200227

function sc_cps_comp_crc($cps_len)
{
	system("dfu lseek envf 4");
	$cps_len -= 4;

	$init = 0x1d0f;

	while($cps_len > 0)
	{
		if($cps_len >= 1024)
			$frag_len = 1024;
		else
			$frag_len = $cps_len;

		$rbuf = system("dfu read envf $frag_len");

		$init = (int)system("crc 16 %1 %2 1021", $rbuf, sprintf("%04x", $init));

		$cps_len -= $frag_len;
	}

	return $init;
}

function cps_erase($src_type)
{
	if($src_type != "obm/envf")
		exit("cps_erase: unsupported source type '$src_type'\r\n");

	system("dfu unlock a5c3");
	system("dfu erase envf all");
}

function cps_write_head($src_type, $file_name, $file_len)
{
	if($src_type != "obm/envf")
		exit("cps_write_head: unsupported source type '$src_type'\r\n");

	$file_name .= "\x00";

	$pad_len = strlen($file_name) & 3;
	if($pad_len)
		$pad_len = 4 - $pad_len;

	if($pad_len)
		$file_name .= str_repeat("\x00", $pad_len);

	$head  = "";
	$head .= int2bin(strlen($file_name) + 12, 1);
	$head .= int2bin($file_len, 3);
	$head .= $file_name;
	$head .= int2bin(time(), 4); // created unix time
	$head .= int2bin(time(), 4); // modified unix time
	$head .= "\x00\x00\x00\x0c";

	system("dfu lseek envf 0");
	system("dfu write envf %1", $head);
}

function cps_write_next($src_type, $next)
{
	if($src_type != "obm/envf")
		exit("cps_write_next: unsupported source type '$src_type'\r\n");

	system("dfu write envf %1", $next);
}

function cps_write_tail($src_type)
{
	if($src_type != "obm/envf")
		exit("cps_write_tail: unsupported source type '$src_type'\r\n");

	$cps_len = (int)system("dfu lseek envf 0 seek_cur");

	$pad_len = $cps_len & 3;
	if($pad_len)
		$pad_len = 4 - $pad_len;

	if($pad_len)
	{
		system("dfu write envf %1", str_repeat("\x00", $pad_len));
		$cps_len += $pad_len;
	}

	$tail  = "";
	$tail .= "\x00\x00";
	$tail .= int2bin(sc_cps_comp_crc($cps_len), 2);

	system("dfu write envf %1", $tail);
}

function cps_load($cps_from, $cps_to)
{
	if($cps_from != "obm/envf")
		exit("cps_load: unsupported source type '$cps_from'\r\n");

	system("dfu lseek envf 0");

	$head_len = bin2int(system("dfu read envf 1"), 0, 1);
	$file_len = bin2int(system("dfu read envf 3"), 0, 3);

	$pad_len = $file_len & 3;
	if($pad_len)
		$pad_len = 4 - $pad_len;

	$cps_len = 4 + $head_len + $file_len + $pad_len;

	$crc = sc_cps_comp_crc($cps_len);

	system("dfu lseek envf $cps_len");

	if($crc != bin2int(system("dfu read envf 4"), 2, 2))
		exit("cps_load: crc error\r\n");

	$head_len += 4;
	system("dfu lseek envf $head_len");

	$pid = pid_open($cps_to);

	while($file_len > 0)
	{
		if($file_len >= 1024)
			$frag_len = 1024;
		else
			$frag_len = $file_len;

		$rbuf = system("dfu read envf $frag_len");
		pid_write($pid, $rbuf);
		
		$file_len -= $frag_len;
	}

	pid_close($pid);
}

?>
