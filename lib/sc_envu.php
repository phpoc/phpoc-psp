<?php

// $psp_id sc_envu.php date 20191224

define("ENVU_SIZE", 1520);

$sc_envu_wkey = 0;

function envu_read($envu_name, $envu_size = 64, $envu_offset = 0)
{
	global $sc_envu_wkey;

	$envu = "";

	if($envu_name == "envu")
	{
		$pid = pid_open("/mmap/$envu_name");
		pid_read($pid, $envu);
		pid_close($pid);

		$sc_envu_wkey = system("nvm wkey envu");

		return rtrim($envu, "\xff\x00");  // remove trailing 0xff/0x00
	}
	else
	{
		$envu_hash = "";

		$pid = pid_open("/mmap/$envu_name");
		pid_lseek($pid, $envu_offset, SEEK_SET);
		pid_read($pid, $envu, $envu_size - 20);
		pid_read($pid, $envu_hash, 20);
		pid_close($pid);

		$sc_envu_wkey = 0;

		if(hash("sha1", $envu, true) == $envu_hash)
			return rtrim($envu, "\x00"); // remove trailing 0x00
		else
		{
			echo "envu_read: hash test failed, init /mmap/$envu_name\r\n"; 
			return "";
		}
	}
}

function envu_write($envu_name, $envu, $envu_size = 64, $envu_offset = 0)
{
	global $sc_envu_wkey;

	$envu_len = strlen($envu);

	if($envu_name == "envu")
	{
		if(!$sc_envu_wkey)
			exit("envu_write: wkey not initialized\r\n");

		if($envu_len > ENVU_SIZE)
			exit("envu_write_nvm: envu too big $envu_len\r\n");

		if($envu_len < ENVU_SIZE)
			$envu .= str_repeat("\x00", ENVU_SIZE - $envu_len);

		system("nvm write $envu_name $sc_envu_wkey %1", $envu);
	}
	else
	{
		if($sc_envu_wkey)
			exit("envu_write: wkey initialized\r\n");

		$envu_size -= 20;

		if($envu_len > $envu_size)
			exit("envu_write: env too big $envu_len\r\n");

		if($envu_len < $envu_size)
			$envu .= str_repeat("\x00", $envu_size - $envu_len);

		$pid = pid_open("/mmap/$envu_name");
		pid_lseek($pid, $envu_offset, SEEK_SET);
		pid_write($pid, $envu);
		pid_write($pid, hash("sha1", $envu, true));
		pid_close($pid);
	}

}

function envu_find(&$envu, $name)
{
	$nvp_list = explode("\r\n", $envu, 64);

	for($i = 0; $i < count($nvp_list); $i++)
	{
		$nvp = explode("=", $nvp_list[$i]);

		if($nvp[0] == $name)
			return $nvp[1];
	}

	return "";
}

function envu_update(&$envu, $name, $value)
{
	$name  = ltrim(rtrim($name));
	$value = ltrim(rtrim($value));

	if(!$name)
		exit("envu_update: null name not supported\r\n");

	$nvp_list = explode("\r\n", $envu, 64);

	$match_count = 0;
	$envu_new = "";

	for($i = 0; $i < count($nvp_list); $i++)
	{
		$nvp = explode("=", $nvp_list[$i]);

		if(!$nvp[0] || !$nvp[1])
			break;

		if($nvp[0] == $name)
		{
			if($value)
				$envu_new .= ($name . "=" . $value . "\r\n");
			$match_count++;
		}
		else
			$envu_new .= ($nvp_list[$i] . "\r\n");
	}

	if(!$match_count)
	{
		if($value)
			$envu_new .= ($name . "=" . $value . "\r\n");
	}

	$envu = $envu_new;
}

?>
