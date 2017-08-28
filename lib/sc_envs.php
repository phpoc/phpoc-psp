<?php 

// $psp_id sc_envs.php date 20150424

define("ENV_CODE_IP4",   0x03);
define("ENV_CODE_IP6",   0x04);
define("ENV_CODE_NETID", 0x08);
define("ENV_CODE_WLAN",  0x09);
define("ENV_CODE_PNAME", 0x0c);
define("ENV_CODE_PHP",   0x0d);

define("NET_OPT_WLAN",       0x00000000);
define("NET_OPT_ARP",        0x00000101);
define("NET_OPT_DHCP",       0x00000202);
define("NET_OPT_AUTO_NS",    0x00000303);
define("NET_OPT_IP6",        0x00000404);
define("NET_OPT_IP6_EUI",    0x00000505);
define("NET_OPT_IP6_GUA",    0x00000606);
define("NET_OPT_SHORT_PRE",  0x00000707);
define("NET_OPT_SHORT_SLOT", 0x00000808);
define("NET_OPT_CTS_PROT",   0x00000909);

define("NET_OPT_TSF",  0x00010002);
define("NET_OPT_WPA",  0x00010306);
define("NET_OPT_AUTH", 0x00010709);
define("NET_OPT_CH",   0x00010a11);
define("NET_OPT_PHY",  0x00011215);

function envs_read()
{
	$envs_pid = pid_open("/mmap/envs");

	$code = 0;
	$id = 0;
	$blk_len = 0;
	$crc = 0;

	$env_head = "";
	$env_blk = "";
	$env_len = 0;

	while(pid_read($envs_pid, $env_head, 4) == 4)
	{
		$code = bin2int($env_head, 0, 1);
		$id = bin2int($env_head, 1, 1);
		$blk_len = bin2int($env_head, 2, 2);

		pid_lseek($envs_pid, -4, SEEK_CUR);

		if(pid_read($envs_pid, $env_blk, $blk_len - 2/*CRC*/) != ($blk_len - 2))
			break;

		if(pid_read($envs_pid, $crc, 2) != 2)
			break;

		if($crc != (int)system("crc 16 %1", $env_blk))
			exit("envs_read: $code $id crc error\r\n");

		$env_len += $blk_len;

		if($code == 0xff)
			break;
	}

	pid_lseek($envs_pid, 0, SEEK_SET);

	$envs = "";
	pid_read($envs_pid, $envs, $env_len);

	pid_close($envs_pid);

	return $envs;
}

function envs_get_wkey()
{
	return system("nvm wkey envs");
}

function envs_write($envs, $wkey)
{
	system("nvm write envs $wkey %1", $envs);
}

function envs_find(&$envs, $req_code, $req_id)
{
	$offset_end = strlen($envs);
	$offset = 0;

	while($offset < $offset_end)
	{
		$env_code = bin2int($envs, $offset + 0, 1);
		$env_id   = bin2int($envs, $offset + 1, 1);
		$env_len  = bin2int($envs, $offset + 2, 2);

		if(($env_code == $req_code) && ($env_id == $req_id))
		{
			$env_crc = bin2int($envs, $offset + $env_len - 2, 2);
			$env_blk = substr($envs, $offset, $env_len - 2);

			if($env_crc != (int)system("crc 16 %1", $env_blk))
				exit("envs_find: $req_code $req_id crc error\r\n");

			return substr($env_blk, 4, $env_len - 4 - 2);
		}

		if($env_code == 0xff)
			break;

		$offset += $env_len;
	}

	return "";
}

function envs_update(&$envs, $req_code, $req_id, $env_blk)
{
	$offset_end = strlen($envs);
	$offset = 0;

	while($offset < $offset_end)
	{
		$env_head = substr($envs, $offset, 4);

		$env_code = bin2int($env_head, 0, 1);
		$env_id   = bin2int($env_head, 1, 1);
		$env_len  = bin2int($env_head, 2, 2);

		if(($env_code == $req_code) && ($env_id == $req_id))
		{
			if(strlen($env_blk) > ($env_len - 4 - 2))
				exit("envs_update: env data too big\r\n");

			$pad_len = $env_len - (4 + strlen($env_blk) + 2);

			while($pad_len--)
				$env_blk .= "\x00";

			$env_blk = $env_head . $env_blk;
			$env_crc = (int)system("crc 16 %1", $env_blk);

			$env_blk .= int2bin($env_crc, 2);

			$envs = substr_replace($envs, $env_blk, $offset, $env_len);

			return $env_len;
		}

		if($env_code == 0xff)
			break;

		$offset += $env_len;
	}

	exit("envs_update: $req_code $req_id not found\r\n");

	return 0;
}

function envs_set_net_opt(&$envs, $bitmap, $set)
{
	$net_opt = envs_find($envs, ENV_CODE_PHP, 0x01);

	if($net_opt)
	{
		$offset = ($bitmap >> 16) & 0xff;
		$start  = ($bitmap >>  8) & 0xff;
		$end    = ($bitmap >>  0) & 0xff;

		$net_opt32 = bin2int($net_opt, $offset * 4, 4);

		$bit = $start;

		for($mask = 1 << $bit; $bit <= $end; $mask <<= 1, $bit++)
			$net_opt32 &= ~$mask;

		$net_opt32 |= ($set << $start);

		$net_opt = substr_replace($net_opt, int2bin($net_opt32, 4), $offset * 4, 4);

		envs_update($envs, ENV_CODE_PHP, 0x01, $net_opt);
	}
}

function envs_get_net_opt(&$envs, $bitmap)
{
	$net_opt = envs_find($envs, ENV_CODE_PHP, 0x01);

	if($net_opt)
	{
		$offset = ($bitmap >> 16) & 0xff;
		$start  = ($bitmap >>  8) & 0xff;
		$end    = ($bitmap >>  0) & 0xff;

		$mask = 0xffffffff >> (31 - ($end - $start));

		$net_opt32 = bin2int($net_opt, $offset * 4, 4);

		return ($net_opt32 >> $start) & $mask;
	}
	else
		return 0;
}

function envs_dump(&$envs, $eol = "")
{
	$offset = 0;

	while($offset < strlen($envs))
	{
		$code = bin2int($envs, $offset, 1);
		$id   = bin2int($envs, $offset + 1, 1);
		$len  = bin2int($envs, $offset + 2, 2);

		printf("%02x/%02x : ", $code, $id);

		if($len > 24)
			$dump_len = 18;
		else
			$dump_len = $len - 6;

		for($i = 0; $i < $dump_len; $i++)
			printf("%02x ", bin2int($envs, $offset + 4 + $i, 1));

		if($len > 24)
			printf("...$eol\r\n");
		else
			printf("$eol\r\n");

		$offset += $len;
	}
}

?>
