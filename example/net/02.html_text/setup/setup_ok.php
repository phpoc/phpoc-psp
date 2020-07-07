<?php
if(!_SERVER("HTTP_REFERER"))
{
	header('HTTP/1.1 403 Forbidden');

	$php_name = _SERVER("SCRIPT_NAME");

	echo "<html>\r\n",
		"<head><title>403 Forbidden</title></head>\r\n",
		"<body>\r\n",
		"<h1>Forbidden</h1>\r\n",
		"<p>You don't have permission to access /$php_name on this server.</p>\r\n",
		"</body></html>\r\n";

	return;
}
?>
<!DOCTYPE html>
<html>
<head>
	<title>PHPoC</title>
	<meta content="initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, width=device-width" name="viewport">
	<style type="text/css">
		<!--
		body { font-family: Tahoma, Helvetica, sans-serif, gulim; }
		-->
	</style>
</head>
<body>

<center>

<br>

<?php	

include_once "/lib/sc_envs.php";

$net_opt_tsf  = (int) _POST("tsf");
$net_opt_ch   = (int) _POST("channel");
$net_opt_wpa 	= (int) _POST("eap_type");
$net_opt_wlan = (int) _POST("wlan_opt");  // Enable 1 - Disbale 0
$ip_type      = (int) _POST("ip_type");
$ssid         = _POST("ssid");
$shared_key   = _POST("shared_key");

$envs = envs_read();

if($net_opt_tsf == 2)
{ // SoftAP
	envs_set_net_opt($envs, NET_OPT_DHCP, 1);
	envs_set_net_opt($envs, NET_OPT_AUTO_NS, 1);
}
else
{	// Infrastructure
	if($ip_type == 0)
	{	// Static IP Address
		envs_set_net_opt($envs, NET_OPT_DHCP, 0);
		envs_set_net_opt($envs, NET_OPT_AUTO_NS, 0);

		$ipaddr  = _POST("ipaddr");
		$netmask = _POST("netmask");
		$gwaddr  = _POST("gwaddr");
		$nsaddr  = _POST("nsaddr");
			
		envs_update($envs, ENV_CODE_IP4, 0x00, inet_pton($ipaddr));
		envs_update($envs, ENV_CODE_IP4, 0x01, inet_pton($netmask));
		envs_update($envs, ENV_CODE_IP4, 0x02, inet_pton($gwaddr));
		envs_update($envs, ENV_CODE_IP4, 0x03, inet_pton($nsaddr));
	}
	else
	{ // DHCP
		envs_set_net_opt($envs, NET_OPT_DHCP, 1);
		envs_set_net_opt($envs, NET_OPT_AUTO_NS, 1);
	}	
}
	
envs_set_net_opt($envs, NET_OPT_WLAN, $net_opt_wlan);

if($net_opt_wlan == 1) // wlan enabled
{		
	if($ssid != rtrim(envs_find($envs, ENV_CODE_WLAN, 0x01)))
		$comp_psk = true;
	else
	if($shared_key != rtrim(envs_find($envs, ENV_CODE_WLAN, 0x08)))
		$comp_psk = true;
	else
		$comp_psk = false;

	if($comp_psk)
	{
		// psk generation take 0.5 second on STM32F407 168MHz
		$wpa_psk = hash_pbkdf2("sha1", $shared_key, $ssid, 4096, 32, true);
		envs_update($envs, ENV_CODE_WLAN, 0x09, $wpa_psk);
	}

	envs_update($envs, ENV_CODE_WLAN, 0x01, $ssid);	
	envs_update($envs, ENV_CODE_WLAN, 0x08, $shared_key);
		
	envs_set_net_opt($envs, NET_OPT_TSF, $net_opt_tsf);
	envs_set_net_opt($envs, NET_OPT_CH, $net_opt_ch);
	envs_set_net_opt($envs, NET_OPT_WPA, $net_opt_wpa);
	envs_set_net_opt($envs, NET_OPT_PHY, 3);
	envs_set_net_opt($envs, NET_OPT_SHORT_PRE, 1);
	envs_set_net_opt($envs, NET_OPT_SHORT_SLOT, 1);
	envs_set_net_opt($envs, NET_OPT_CTS_PROT, 1);
}

if($ip_type == 1)
	echo "<br><br><h4>IP address may be changed. ",
		"Please check newly assigned IP address from PHPoC Debugger and ",
		"reconnect to the device.</h4><br><br>\r\n";	
else
	echo "<br><br>";

$wkey = envs_get_wkey(); 
envs_write($envs, $wkey);

echo "<h2>setup complete</h2>\r\n";

if($ip_type != 1)
	echo "<a href=index.php>Home</a>&nbsp;&nbsp;<br>\r\n";
	
system("reboot sys 1000");

?>

</center>
</body>
</html>
