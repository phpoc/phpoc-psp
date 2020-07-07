<?php 

// $psp_id sc_envs.php date 20181109

// ezTCP compatible env codes
define("ENV_CODE_IP4",   0x03);
define("ENV_CODE_IP6",   0x04);
define("ENV_CODE_NETID", 0x08);
define("ENV_CODE_WLAN",  0x09);
define("ENV_CODE_PNAME", 0x0c);

// PHP specific env code (PHP_ID_DEV, PHP_ID_NET, ...)
define("ENV_CODE_PHP",   0x0d);

// PHP app env codes
define("ENV_CODE_APP_ANY",      0x80); // id only search
define("ENV_CODE_APP_UINT16",   0x90); // 16bit unsigned integer
define("ENV_CODE_APP_BOOL",     0x91); // 16bit bool number
define("ENV_CODE_APP_ASC_STR",  0x92); // ascii string
define("ENV_CODE_APP_BIN_STR",  0x93); // binary string
define("ENV_CODE_APP_IP4",      0x94); // 32bit ip4 address
define("ENV_CODE_APP_IP6",      0x95); // 128bit ip6 address
define("ENV_CODE_APP_MAC48",    0x96); // 48bit mac address in 64bit EUI
define("ENV_CODE_APP_INT32",    0x97); // 32bit signed integer
define("ENV_CODE_APP_INT64",    0x98); // 64bit signed integer
define("ENV_CODE_APP_FP32",     0x99); // 32bit single precision floating point
define("ENV_CODE_APP_FP64",     0x9a); // 64bit double precision floating point
define("ENV_CODE_APP_CSV_STR",  0xfb); // csv string
define("ENV_CODE_APP_UART",     0xfc); // 64bit uart parameter
define("ENV_CODE_APP_GROUP",    0xfd); // 16bit code/id list for UI group
define("ENV_CODE_APP_ENV_DESC", 0xfe); // app_env descriptor

// ENV_CODE_APP_ENV_DESC id
define("ENV_DESC_ID_POC_FILE", 0xfc); // app env poc file
define("ENV_DESC_ID_INI_FILE", 0xfd); // app env ini file
define("ENV_DESC_ID_UI_MAP",   0xfe); // app env UI map

// PHP network option bits : word offset 0 + start bit + end bit
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

// PHP network option bits : word offset 1 + start bit + end bit
define("NET_OPT_TSF",  0x00010002);
define("NET_OPT_WPA",  0x00010306);
define("NET_OPT_AUTH", 0x00010709);
define("NET_OPT_CH",   0x00010a11);
define("NET_OPT_PHY",  0x00011215);

function envs_read()
{
	$envs_pid = pid_open("/mmap/envs");

	$env_code = 0;
	$env_id = 0;
	$blk_len = 0;
	$crc = 0;

	$env_head = "";
	$env_blk = "";
	$env_len = 0;

	while(pid_read($envs_pid, $env_head, 4) == 4)
	{
		$env_code = bin2int($env_head, 0, 1);
		$env_id = bin2int($env_head, 1, 1);
		$blk_len = bin2int($env_head, 2, 2);

		pid_lseek($envs_pid, -4, SEEK_CUR);

		if(pid_read($envs_pid, $env_blk, $blk_len - 2/*CRC*/) != ($blk_len - 2))
			break;

		if(pid_read($envs_pid, $crc, 2) != 2)
			break;

		if($crc != (int)system("crc 16 %1", $env_blk))
			exit("envs_read: $env_code $env_id crc error\r\n");

		$env_len += $blk_len;

		if($env_code == 0xff)
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

function sc_envs_max_len(&$env_blk, $add_tail = false)
{
	$env_code = bin2int($env_blk, 0, 1);
	$env_id   = bin2int($env_blk, 1, 1);
	$blk_len  = bin2int($env_blk, 2, 2);

	$text_len = bin2int($env_blk, $blk_len - 4, 1) * 4;
	$opt_len  = bin2int($env_blk, $blk_len - 3, 1) * 4;
	$max_len  = $blk_len - (4 + $text_len + $opt_len + 4);

	switch($env_code)
	{
		case ENV_CODE_IP6:
			return 18; // last 2 bytes are used for prefix
		case ENV_CODE_APP_UINT16:
			return 2;
		case ENV_CODE_APP_BOOL:
			return 2;
		case ENV_CODE_APP_ASC_STR:
			if($add_tail)
				return $max_len;
			else
				return $max_len - 4;
		case ENV_CODE_APP_BIN_STR:
			if($add_tail)
				return $max_len;
			else
				return $max_len - 4;
		case ENV_CODE_APP_IP4:
			return 4;
		case ENV_CODE_APP_IP6:
			return 16;
		case ENV_CODE_APP_MAC48:
			return 8;
		case ENV_CODE_APP_INT32:
			return 4;
		case ENV_CODE_APP_INT64:
			return 8;
		case ENV_CODE_APP_FP32:
			return 4;
		case ENV_CODE_APP_FP64:
			return 8;
		case ENV_CODE_APP_CSV_STR:
			if($add_tail)
				return $max_len;
			else
				return $max_len - 4;
		case ENV_CODE_APP_UART:
			return 8;
		case ENV_CODE_APP_GROUP:
			if($add_tail)
				return $max_len;
			else
				return $max_len - 4;
		case ENV_CODE_APP_ENV_DESC:
			if($add_tail)
				return $max_len;
			else
				return $max_len - 4;
		default:
			return $max_len;
	}
}

function envs_find(&$envs, $req_code, $req_id, $add_tail = false)
{
	$offset_end = strlen($envs);
	$offset = 0;

	while($offset < $offset_end)
	{
		$env_code = bin2int($envs, $offset + 0, 1);
		$env_id   = bin2int($envs, $offset + 1, 1);
		$blk_len  = bin2int($envs, $offset + 2, 2);

		$found = false;

		if($req_code == ENV_CODE_APP_ANY)
		{
			if(($env_code >= 0x80) && ($env_id == $req_id))
				$found = true;
		}
		else
		{
			if(($env_code == $req_code) && ($env_id == $req_id))
				$found = true;
		}

		if($found)
		{
			if(($blk_len <= 8) || ($blk_len & 3))
				exit("envs_find: $env_code/$env_id invalid blk_len $blk_len\r\n");

			$env_blk = substr($envs, $offset, $blk_len);

			$max_len = sc_envs_max_len($env_blk, $add_tail);

			if($max_len <= 0)
				exit("envs_find: $env_code/$env_id invalid max_len $max_len\r\n");

			$env_crc = bin2int($env_blk, $blk_len - 2, 2);

			if($env_crc != (int)system("crc 16 %1", substr($env_blk, 0, $blk_len - 2)))
				exit("envs_find: $env_code/$env_id crc error\r\n");

			return substr($env_blk, 4, $max_len);
		}

		if($env_code == 0xff)
			break;

		$offset += $blk_len;
	}

	return "";
}

function envs_find_tag(&$envs, $find_tag)
{
	$offset_end = strlen($envs);
	$offset = 0;

	while($offset < $offset_end)
	{
		$env_code = bin2int($envs, $offset + 0, 1);
		$env_id   = bin2int($envs, $offset + 1, 1);
		$blk_len  = bin2int($envs, $offset + 2, 2);

		if(($blk_len <= 8) || ($blk_len & 3))
			exit("envs_find_tag: $env_code/$env_id invalid blk_len $blk_len\r\n");

		$env_blk = substr($envs, $offset, $blk_len);

		$text_len = bin2int($env_blk, $blk_len - 4, 1) * 4;

		if($text_len)
		{
			$opt_len  = bin2int($env_blk, $blk_len - 3, 1) * 4;
			$data_len = $blk_len - (4 + $text_len + $opt_len + 4);

			$text_offset = 4 + $data_len;

			$tag_len  = bin2int($env_blk, $text_offset + $text_len - 1, 1) * 4;

			if($tag_len)
			{
				$name_len = bin2int($env_blk, $text_offset + $text_len - 4, 1) * 4;
				$list_len = bin2int($env_blk, $text_offset + $text_len - 3, 1) * 4;

				$tag = substr($env_blk, $text_offset + $name_len + $list_len, $tag_len);
				$tag = trim($tag);

				if($tag == $find_tag)
				{
					$max_len = sc_envs_max_len($env_blk);

					if($max_len <= 0)
						exit("envs_find: $env_code/$env_id invalid max_len $max_len\r\n");

					$env_crc = bin2int($env_blk, $blk_len - 2, 2);

					if($env_crc != (int)system("crc 16 %1", substr($env_blk, 0, $blk_len - 2)))
						exit("envs_find: $env_code/$env_id crc error\r\n");

					return substr($env_blk, 4, $max_len);
				}
			}
		}

		if($env_code == 0xff)
			break;

		$offset += $blk_len;
	}

	return "";
}

function envs_update(&$envs, $req_code, $req_id, $env_data)
{
	$offset_end = strlen($envs);
	$offset = 0;

	while($offset < $offset_end)
	{
		$env_code = bin2int($envs, $offset + 0, 1);
		$env_id   = bin2int($envs, $offset + 1, 1);
		$blk_len  = bin2int($envs, $offset + 2, 2);

		if(($env_code == $req_code) && ($env_id == $req_id))
		{
			if(($blk_len <= 8) || ($blk_len & 3))
				exit("envs_update: $req_code/$req_id invalid blk_len $blk_len\r\n");

			$env_blk = substr($envs, $offset, $blk_len);

			$max_len = sc_envs_max_len($env_blk);

			if($max_len <= 0)
				exit("envs_update: $req_code/$req_id invalid max_len $max_len\r\n");

			if(strlen($env_data) > $max_len)
				exit("envs_update: $req_code/$req_id env data too big\r\n");

			switch($env_code)
			{
				case ENV_CODE_APP_ASC_STR:
				case ENV_CODE_APP_BIN_STR:
				case ENV_CODE_APP_CSV_STR:
				case ENV_CODE_APP_GROUP:
				case ENV_CODE_APP_ENV_DESC:
					$env_blk = substr_replace($env_blk, int2bin(strlen($env_data), 2), 4 + $max_len + 2, 2);
					break;
			}

			if(strlen($env_data) < $max_len)
				$env_data .= str_repeat("\x00", $max_len - strlen($env_data));

			$env_blk = substr_replace($env_blk, $env_data, 4, $max_len);

			$env_crc = (int)system("crc 16 %1", substr($env_blk, 0, $blk_len - 2));
			$env_blk = substr_replace($env_blk, int2bin($env_crc, 2), $blk_len - 2, 2);

			$envs = substr_replace($envs, $env_blk, $offset, $blk_len);

			return $blk_len;
		}

		if($env_code == 0xff)
			break;

		$offset += $blk_len;
	}

	exit("envs_update: $req_code/$req_id not found\r\n");

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

function envs_echo($path = "/mmap/envs")
{
	$envs_pid = pid_open($path);

	$env_code = 0;
	$env_id = 0;
	$blk_len = 0;
	$crc = 0;

	$env_head = "";
	$env_blk = "";
	$env_len = 0;
	$env_offset = 0;

	pid_read($envs_pid, $env_head, 4);

	if($env_head == "eVnS")
		$env_offset = 16;

	$file_len = pid_lseek($envs_pid, 0, SEEK_END) - $env_offset;

	pid_lseek($envs_pid, $env_offset, SEEK_SET);

	while($file_len > 0)
	{
		pid_read($envs_pid, $env_head, 4);

		$env_code = bin2int($env_head, 0, 1);
		$env_id = bin2int($env_head, 1, 1);
		$blk_len = bin2int($env_head, 2, 2);

		if(($blk_len < 8) || ($blk_len & 3) || ($blk_len > $file_len))
			exit("envs_echo: invalid blk_len $blk_len\r\n");

		pid_lseek($envs_pid, -4, SEEK_CUR);

		if(pid_read($envs_pid, $env_blk, $blk_len - 2/*CRC*/) != ($blk_len - 2))
			break;

		if(pid_read($envs_pid, $crc, 2) != 2)
			break;

		if($crc != (int)system("crc 16 %1", $env_blk))
			exit("envs_echo: $env_code $env_id crc error\r\n");

		$env_len += $blk_len;

		if($env_code == 0xff)
			break;
	}

	pid_lseek($envs_pid, $env_offset, SEEK_SET);

	$echo_count = 0;

	flush();

	while($env_len > 0)
	{
		if($env_len >= 256)
			$blk_len = 256;
		else
			$blk_len = $env_len;

		pid_read($envs_pid, $env_blk, $blk_len);

		//hexdump($env_blk);
		echo bin2hex($env_blk);

		$echo_count += $blk_len;
		$env_len -= $blk_len;

		if($echo_count >= MAX_STRING_LEN)
		{
			flush();
			$echo_count = 0;
		}
	}
}

function sc_envs_dump_app_uart($env_blk)
{
	printf("%d", bin2int($env_blk, 4 + 0, 4));

	$opt0 = bin2int($env_blk, 4 + 4, 1);
	$opt1 = bin2int($env_blk, 4 + 5, 1);
	$opt2 = bin2int($env_blk, 4 + 6, 1);
	$opt3 = bin2int($env_blk, 4 + 7, 1);

	switch($opt0 >> 5)
	{
		case 0:
			printf("N");
			break;
		case 1:
			printf("E");
			break;
		case 2:
			printf("O");
			break;
		case 3:
			printf("M");
			break;
		case 4:
			printf("S");
			break;
	}

	printf("%d", (($opt0 >> 2) & 0x07) + 5);

	if($opt1 & 0x03)
		printf("2");
	else
		printf("1");

	switch($opt1 >> 5)
	{
		case 0:
			printf("N");
			break;
		case 1:
			printf("H");
			break;
		case 2:
			printf("S");
			break;
		case 3:
			printf("X");
			break;
	}

	printf(", ");

	switch(($opt1 >> 2) & 0x07)
	{
		case 0:
			printf("ttl");
			break;
		case 1:
			printf("RS232");
			break;
		case 2:
			printf("RS422");
			break;
		case 3:
			printf("RS485");
			break;
	}

	printf(", ");

	if($opt2 & 0x40)
		printf("S");
	if($opt2 & 0x20)
		printf("D");
	if($opt2 & 0x10)
		printf("H");
	if($opt2 & 0x08)
		printf("8");
	if($opt2 & 0x04)
		printf("4");
	if($opt2 & 0x02)
		printf("2");
	if($opt2 & 0x01)
		printf("T");

	printf(", ");
}

function sc_envs_dump_blk($name, $env_blk)
{
	$env_code = bin2int($env_blk, 0, 1);
	$env_id   = bin2int($env_blk, 1, 1);
	$blk_len  = bin2int($env_blk, 2, 2);

	printf("%8s %02x : ", $name, $env_id);

	if(($env_code < ENV_CODE_APP_UINT16) || ($env_code == 0xff))
	{
		if($env_code == ENV_CODE_PNAME)
		{
			$name = substr($env_blk, 4, $blk_len - 8);
			$name = trim($name);
			printf("%s\r\n", $name);
		}
		else
		{
			if($blk_len > 24)
				$dump_len = 16;
			else
				$dump_len = $blk_len - 8;
	
			for($i = 0; $i < $dump_len; $i++)
				printf("%02x ", bin2int($env_blk, 4 + $i, 1));

			if($env_code == ENV_CODE_IP6)
				printf("/ %d ", bin2int($env_blk, 4 + $dump_len, 2));

			if($blk_len > 24)
				echo("...\r\n");
			else
				echo("\r\n");
		}

		return;
	}

	$text_len = bin2int($env_blk, $blk_len - 4, 1) * 4;
	$opt_len  = bin2int($env_blk, $blk_len - 3, 1) * 4;
	$data_len = $blk_len - (4 + $text_len + $opt_len + 4);

	if($text_len)
	{
		$text_offset = 4 + $data_len;

		$name_len = bin2int($env_blk, $text_offset + $text_len - 4, 1) * 4;
		$list_len = bin2int($env_blk, $text_offset + $text_len - 3, 1) * 4;
		$tag_len  = bin2int($env_blk, $text_offset + $text_len - 1, 1) * 4;
	}
	else
	{
		$name_len = 0;
		$list_len = 0;
		$tag_len  = 0;
	}

	if($name_len)
	{
		$name = substr($env_blk, $text_offset, $name_len);
		$name = trim($name);
	}
	else
		$name = "";

	if($list_len)
	{
		$list = substr($env_blk, $text_offset + $name_len, $list_len);
		$list = trim($list);
	}
	else
		$list = "";

	if($tag_len)
	{
		$tag = substr($env_blk, $text_offset + $name_len + $list_len, $tag_len);
		$tag = trim($tag);
	}
	else
		$tag = "";

	switch($env_code)
	{
		case ENV_CODE_APP_UINT16:
			printf("%d, ", bin2int($env_blk, 4 + 0, 2));
			printf("%d, ", bin2int($env_blk, 4 + 2, 2));
			printf("%d, ", bin2int($env_blk, 4 + 4, 2));
			break;

		case ENV_CODE_APP_BOOL:
			$bool = bin2int($env_blk, 4, 2);
			$bit_len = bin2int($env_blk, 4 + 2, 1);
			$mask = 0x0001;
			for($i = 0; $i < $bit_len; $i++)
			{
				if($mask & $bool)
					printf("1");
				else
					printf("0");
				$mask <<= 1;
			}
			printf(", ");
			break;

		case ENV_CODE_APP_ASC_STR:
			$asc_len = bin2int($env_blk, 4 + $data_len - 2, 2);
			$str = substr($env_blk, 4, $asc_len);
			$str = trim($str);
			printf("'%s', ", $str);
			break;

		case ENV_CODE_APP_BIN_STR:
			$bin_len = bin2int($env_blk, 4 + $data_len - 2, 2);
			$str = substr($env_blk, 4, $bin_len);
			printf("%s, ", bin2hex($str));
			break;

		case ENV_CODE_APP_IP4:
			printf("%s, ", inet_ntop(substr($env_blk, 4, 4)));
			break;

		case ENV_CODE_APP_IP6:
			printf("%s, ", inet_ntop(substr($env_blk, 4, 16)));
			break;

		case ENV_CODE_APP_MAC48:
			printf("%s:", bin2hex(substr($env_blk, 4 + 0, 1)));
			printf("%s:", bin2hex(substr($env_blk, 4 + 1, 1)));
			printf("%s:", bin2hex(substr($env_blk, 4 + 2, 1)));
			printf("%s:", bin2hex(substr($env_blk, 4 + 5, 1)));
			printf("%s:", bin2hex(substr($env_blk, 4 + 6, 1)));
			printf("%s, ", bin2hex(substr($env_blk, 4 + 7, 1)));
			break;

		case ENV_CODE_APP_INT32:
			printf("%d, ", bin2int($env_blk, 4 + 0, 4));
			printf("%d, ", bin2int($env_blk, 4 + 4, 4));
			printf("%d, ", bin2int($env_blk, 4 + 8, 4));
			break;

		case ENV_CODE_APP_INT64:
			printf("%d, ", bin2int($env_blk, 4 + 0, 8));
			printf("%d, ", bin2int($env_blk, 4 + 8, 8));
			printf("%d, ", bin2int($env_blk, 4 + 16, 8));
			break;

		case ENV_CODE_APP_FP32:
			printf("%g, ", bin2float($env_blk, 4 + 0));
			printf("%g, ", bin2float($env_blk, 4 + 4));
			printf("%g, ", bin2float($env_blk, 4 + 8));
			break;

		case ENV_CODE_APP_FP64:
			break;

		case ENV_CODE_APP_CSV_STR:
			$csv_len = bin2int($env_blk, 4 + $data_len - 2, 2);
			$str = substr($env_blk, 4, $csv_len);
			$str = trim($str);
			printf("'%s', ", $str);
			break;

		case ENV_CODE_APP_UART:
			sc_envs_dump_app_uart($env_blk);
			break;

		case ENV_CODE_APP_GROUP:
			$map_len = bin2int($env_blk, 4 + $data_len - 2, 2);
			for($i = 0; $i < $map_len; $i += 2)
				printf("%04x, ", bin2int($env_blk, 4 + $i, 2, true));
			break;

		case ENV_CODE_APP_ENV_DESC:
			if($env_id == ENV_DESC_ID_UI_MAP)
			{
				$map_len = bin2int($env_blk, 4 + $data_len - 2, 2);
				for($i = 0; $i < $map_len; $i += 2)
					printf("%04x, ", bin2int($env_blk, 4 + $i, 2, true));
			}
			else
			if(($env_id == ENV_DESC_ID_INI_FILE) || ($env_id == ENV_DESC_ID_POC_FILE))
			{
				$path = substr($env_blk, 4, $data_len - 4);
				$path = trim($path);
				$src_len = bin2int($env_blk, 4 + $data_len - 4, 2);
				$src_crc16 = bin2int($env_blk, 4 + $data_len - 2, 2);
				printf("'%s', %d, 0x%04x", $path, $src_len, $src_crc16);
			}
			break;

		default:
			break;
	}

	if($name)
		printf("'%s' ", $name);

	if($list)
		printf("'%s' ", $list);

	if($tag)
		printf("'%s' ", $tag);

	echo("\r\n");
}

function envs_dump(&$envs)
{
	$offset = 0;

	while($offset < strlen($envs))
	{
		$env_code = bin2int($envs, $offset, 1);
		$env_id   = bin2int($envs, $offset + 1, 1);
		$blk_len  = bin2int($envs, $offset + 2, 2);

		$env_blk = substr($envs, $offset, $blk_len);

		switch($env_code)
		{
			case ENV_CODE_IP4:
				sc_envs_dump_blk("IP4", $env_blk);
				break;
			case ENV_CODE_IP6:
				sc_envs_dump_blk("IP6", $env_blk);
				break;
			case ENV_CODE_NETID:
				sc_envs_dump_blk("NETID", $env_blk);
				break;
			case ENV_CODE_WLAN:
				sc_envs_dump_blk("WLAN", $env_blk);
				break;
			case ENV_CODE_PNAME:
				sc_envs_dump_blk("PNAME", $env_blk);
				break;
			case ENV_CODE_PHP:
				sc_envs_dump_blk("PHP", $env_blk);
				break;
			case ENV_CODE_APP_UINT16:
				sc_envs_dump_blk("UINT16", $env_blk);
				break;
			case ENV_CODE_APP_BOOL:
				sc_envs_dump_blk("BOOL", $env_blk);
				break;
			case ENV_CODE_APP_ASC_STR:
				sc_envs_dump_blk("ASC_STR", $env_blk);
				break;
			case ENV_CODE_APP_BIN_STR:
				sc_envs_dump_blk("BIN_STR", $env_blk);
				break;
			case ENV_CODE_APP_IP4:
				sc_envs_dump_blk("APP/IP4", $env_blk);
				break;
			case ENV_CODE_APP_IP6:
				sc_envs_dump_blk("APP/IP6", $env_blk);
				break;
			case ENV_CODE_APP_MAC48:
				sc_envs_dump_blk("MAC48", $env_blk);
				break;
			case ENV_CODE_APP_INT32:
				sc_envs_dump_blk("INT32", $env_blk);
				break;
			case ENV_CODE_APP_INT64:
				sc_envs_dump_blk("INT64", $env_blk);
				break;
			case ENV_CODE_APP_FP32:
				sc_envs_dump_blk("FP32", $env_blk);
				break;
			case ENV_CODE_APP_FP64:
				sc_envs_dump_blk("FP64", $env_blk);
				break;
			case ENV_CODE_APP_CSV_STR:
				sc_envs_dump_blk("CSV_STR", $env_blk);
				break;
			case ENV_CODE_APP_UART:
				sc_envs_dump_blk("UART", $env_blk);
				break;
			case ENV_CODE_APP_GROUP:
				sc_envs_dump_blk("GROUP", $env_blk);
				break;
			case ENV_CODE_APP_ENV_DESC:
				sc_envs_dump_blk("ENV_DESC", $env_blk);
				break;
			case 0xff:
				sc_envs_dump_blk("EOE", $env_blk);
				break;
			default:
				sc_envs_dump_blk("UNKNOWN", $env_blk);
				break;
		}

		$offset += $blk_len;
	}
}

?>
