<?php

include_once "/lib/sc_envs.php";

$envs = envs_read();

if(envs_get_net_opt($envs, NET_OPT_DHCP))
	$ip_type = 1;
else
	$ip_type = 0;

if(envs_get_net_opt($envs, NET_OPT_WLAN))
	$net_type = 1;
else
	$net_type = 0;

$wmode    = envs_get_net_opt($envs, NET_OPT_TSF);
$channel  = envs_get_net_opt($envs, NET_OPT_CH);
$eap_type = envs_get_net_opt($envs, NET_OPT_WPA);

$ipaddr  = inet_ntop(substr(envs_find($envs, ENV_CODE_IP4, 0x00), 0, 4));
$netmask = inet_ntop(substr(envs_find($envs, ENV_CODE_IP4, 0x01), 0, 4));
$gwaddr  = inet_ntop(substr(envs_find($envs, ENV_CODE_IP4, 0x02), 0, 4));
$nsaddr  = inet_ntop(substr(envs_find($envs, ENV_CODE_IP4, 0x03), 0, 4));
	
$ssid_env       = envs_find($envs, ENV_CODE_WLAN, 0x01);
$ssid_pos 		= strpos($ssid_env, int2bin(0x00, 1));
$ssid		 	= substr($ssid_env, 0, (int)$ssid_pos);

$shared_key_env = envs_find($envs, ENV_CODE_WLAN, 0x08);	
$shared_key_pos = strpos($shared_key_env, int2bin(0x00, 1));
$shared_key		= substr($shared_key_env, 0, (int)$shared_key_pos);

?>
<!DOCTYPE html>
<html>
<head>
	<title>PHPoC</title>
	<meta content="initial-scale=0.7, maximum-scale=1.0, minimum-scale=0.5, width=device-width, user-scalable=yes" name="viewport">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /> 
	<style type="text/css">
		body { font-family: verdana, Helvetica, Arial, sans-serif, gulim; }
		h1 { font-weight: bold; font-family : verdana, Helvetica, Arial, verdana, sans-serif, gulim; font-size:15pt; padding-bottom:5px;}
		table {border-collapse:collapse; width:430px;  font-size:10pt;}
		.theader { font-weight: bold;}
		tr {height :28px;}
		td { padding-left: 10px; text-align: left;}
		.superHeader {height: 2em; color: white; background-color: rgb(0,153,153); font-size:9pt; position:fixed; left:0; right:0; top:0; z-index:5;  }		
		.right {
		  color: white;
		  position: absolute;
		  right: 1px;
		  bottom: 4px;
		  font-size:9pt;		  
		}	
		.left {
		  color: white;
		  position: absolute;
		  left: 1px;
		  bottom: 4px;
		  font-size:9pt;		  
		}
		.right a, .left a
		{
		  color: white;
		  background-color: transparent;
		  text-decoration: none;
		  margin: 0;
		  padding:0 2ex 0 2ex;
		}			
		.right a:hover, .left a:hover 
		{
		  color: white;
		  text-decoration: underline;
		 }		 
		.midHeader {color: white; background-color: rgb(6, 38, 111);  position:fixed; left:0; right:0; top:1.5em;  z-index:3;}
		.headerTitle {
		  font-size: 250%;
		  font-weight: normal;
		  margin: 0 0 0 4mm;
		  padding: 0.25ex 0 1ex 0;
		  font-family: impact;
		}
		.headerMenu{
			position:relative;
			width: 430px;
			padding: 5px;
		}
		#footer{margin:0 auto; height:auto !important; height:100%; margin-bottom:-100px;  }
		.superFooter {
			height: 2em; color: white; background-color: rgb(6, 38, 111); font-size:9pt; position:fixed; left:0; right:0; bottom:0; z-index:4; 
		}				
		.zebra {background-color : #ECECEC;}
	</style>
	<script type="text/javascript">

	function chkUI()
	{
		chkWlan();
		chkIPGetType();	
		chkHideKey();
	}
	
	function chkWlan()
	{
		var pbh_setup = document.pbh_setup;
		if(pbh_setup.wlan_opt[1].checked) // WLAN disable
		{
			pbh_setup.ip_type[0].disabled = "";
			pbh_setup.ip_type[1].disabled = "";
			
			pbh_setup.tsf[0].disabled = "true";
			pbh_setup.tsf[1].disabled = "true";
			pbh_setup.tsf[2].disabled = "true";
			pbh_setup.channel.disabled = "true";
			pbh_setup.ssid.disabled = "true";
			pbh_setup.shared_key.disabled = "true";
			pbh_setup.hide_key.disabled = "true";	
		}
		else // WLAN enable
		{
			pbh_setup.tsf[0].disabled = "";
			pbh_setup.tsf[1].disabled = "";
			pbh_setup.tsf[2].disabled = "";
					
			pbh_setup.ssid.disabled = "";
			pbh_setup.shared_key.disabled = "";
			pbh_setup.hide_key.disabled = "";	

			chkChannel();			
		}
	}

	function chkChannel() {
		var pbh_setup = document.pbh_setup;		
		
		if(pbh_setup.tsf[1].checked) //infrastructure
		{	
			pbh_setup.channel.disabled = "true";						
			pbh_setup.channel.value = "0";	
			pbh_setup.ip_type[0].disabled = "";
			pbh_setup.ip_type[1].disabled = "";	
			
			if(pbh_setup.ip_type[1].checked)	//DHCP
			{
				pbh_setup.ipaddr.disabled = "true";
				pbh_setup.netmask.disabled = "true";
				pbh_setup.gwaddr.disabled = "true";
				pbh_setup.nsaddr.disabled = "true";
			}	
			else
			{
				pbh_setup.ipaddr.disabled = "";
				pbh_setup.netmask.disabled = "";
				pbh_setup.gwaddr.disabled = "";
				pbh_setup.nsaddr.disabled = "";
			}
		} 
		else if(pbh_setup.tsf[2].checked) 	//softap
		{
			pbh_setup.channel.disabled = "";
			pbh_setup.ip_type[1].checked = true;
			pbh_setup.ip_type[0].disabled = "true";
			//pbh_setup.ip_type[1].disabled = "true";
			chkIPGetType();
		}
		else 
		{
			pbh_setup.channel.disabled = "";	
			pbh_setup.ip_type[0].disabled = "";
			pbh_setup.ip_type[1].disabled = "";
		}
	}
	
	function chkIPGetType()
	{
		var pbh_setup = document.pbh_setup;
		if(pbh_setup.ip_type[1].checked)
		{
			pbh_setup.ipaddr.disabled = "true";
			pbh_setup.netmask.disabled = "true";
			pbh_setup.gwaddr.disabled = "true";
			pbh_setup.nsaddr.disabled = "true";
		}
		else
		{
			pbh_setup.ipaddr.disabled = "";
			pbh_setup.netmask.disabled = "";
			pbh_setup.gwaddr.disabled = "";
			pbh_setup.nsaddr.disabled = "";
		}
	}
	
	function chkHideKey() {
		var pbh_setup = document.pbh_setup;
		if(pbh_setup.hide_key.checked == true)
		{	
			pbh_setup.shared_key.type = "password";
		}else {
			pbh_setup.shared_key.type = "text";
		}
	}	

	function checkIpForm(ip_addr)
	{
		var filter =  /^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/;
	
		if (ip_addr.match(filter)) 
			result = true;
		else 	
			result = false;		
		
		return result;	
	}	
	
	function excSubmit()
	{			
		var pbh_setup = document.pbh_setup;	

		//IP check
		var ip_result = checkIpForm(pbh_setup.ipaddr.value);
		if(ip_result == false)
		{
			alert("Please check your IP address.");	
			pbh_setup.ipaddr.focus();
			return;
		}
		
		//subnet check
		var subnet_result = checkIpForm(pbh_setup.netmask.value);
		if(subnet_result == false)
		{
			alert("Please check your Subnet mask.");	
			pbh_setup.netmask.focus();
			return;
		}
		
		//Gateway check
		var gwaddr_result = checkIpForm(pbh_setup.gwaddr.value);
		if(gwaddr_result == false)
		{
			alert("Please check your Gateway IP address.");	
			pbh_setup.gwaddr.focus();
			return;
		}
		
		//DNS check
		var nsaddr_result = checkIpForm(pbh_setup.nsaddr.value);
		if(nsaddr_result == false)
		{
			alert("Please check your DNS IP address.");	
			pbh_setup.nsaddr.focus();
			return;
		}

		if (pbh_setup.wlan_opt[0].checked)
		{
			//SSID check
			if ( pbh_setup.ssid.value.length > 32) // MAX 32 bytes
			{
				alert("Please check the SSID.");
				pbh_setup.ssid.focus();
				return;
			}			
			
			//shared key check
			if(pbh_setup.tsf[2].checked)
			{
				if(pbh_setup.shared_key.value.length && (pbh_setup.shared_key.value.length < 8))
				{
					alert("Please check the length of shared key.");
					pbh_setup.shared_key.focus();
					return;
				}	
			}

		}
		
		pbh_setup.submit();
			 
		var ipaddr = pbh_setup.ipaddr.value;
	    var ip_type;
		
		if (pbh_setup.ip_type[0].checked == true) 
			ip_type = 0; //static IP address
		else
			ip_type = 1; //DHCP

		if (((ipaddr != '<?php echo $ipaddr;?>' && pbh_setup.ip_type[0].checked)) || (ip_type != <?php echo $ip_type;?> && pbh_setup.ip_type[0].checked)) 
		{
			var url = pbh_setup.ipaddr.value;
			url = "http://" + url;
			alert("Redirect to : " + url);

			var win = window.open(url); 
		}

	}
	</script>
</head>
<body onload="chkUI();">
    <div id="header">
		<div class="superHeader">		
			<div class="left">
			</div>	
			<div class="right">
				<a href="http://www.sollae.co.kr" target="_blank">SOLLAE SYSTEMS</a>
			</div>
		</div>

		<div class="midHeader">
			<center>
				<h1 class="headerTitle"><?php echo system("uname -i");?></h1>
				<div class="headerMenu">
					<div class="left">
						<a href="index.php">INFO</a>  | 
						<a href="setup.php">SETUP</a>		
					</div>
					<div class="right">
						<a href="javascript:excSubmit();">SAVE</a>			
					</div>
				</div>
			</center>
		</div>
		
		<div class="subHeader">
		</div>		
	</div>	
	<br /><br /><br /><br />
	<form name="pbh_setup" action="setup_ok.php" method="post">		
	<center>	
		<hr style="margin:50px 0 -10px 0; width:430px;" size="6" noshade>
		<h1>Network</h1>
		
		<table>
			<tr class="zebra">
				<td width="170px" class="theader">IP address Type</td>	
				<td>
					<input type="radio" value="0" name="ip_type" onclick="chkIPGetType();" <? if($ip_type == 0) echo "checked" ?> /> Static IP address<br />
					<input type="radio" value="1" name="ip_type" onclick="chkIPGetType();" <? if($ip_type == 1) echo "checked" ?> /> DHCP (Auto DNS server) 
				</td>
			</tr>
			<tr>
				<td class="theader">IP address</td>	
				<td>
					<input type="text" name="ipaddr" value="<? echo $ipaddr ?>">
					<input type="hidden" name="old_ipaddr" value="<? echo $ipaddr ?>">
				</td>
			</tr>
			<tr class="zebra"> 
				<td class="theader">Subnet mask</td>	
				<td>
					<input type="text" name="netmask" value="<? echo $netmask ?>">
				</td>
			</tr>
			<tr> 
				<td class="theader">Gateway IP address</td>	
				<td>
					<input type="text" name="gwaddr" value="<? echo $gwaddr ?>">
				</td>
			</tr>
			<tr class="zebra"> 
				<td class="theader">DNS IP address</td>	
				<td>
					<input type="text" name="nsaddr" value="<? echo $nsaddr ?>"> 
				</td>
			</tr>
		</table>

		<hr style="margin:50px 0 -10px 0; width:430px;" size="6" noshade>
		<h1>Wireless LAN</h1>
		<table>
			<tr class="zebra">
				<td width="170px" class="theader">WLAN</td>
				<td>
					<input type="radio" value="1" name="wlan_opt" onclick="chkWlan();" <? if($net_type == 1) echo "checked" ?> /> Enable<br />
					<input type="radio" value="0" name="wlan_opt" onclick="chkWlan();" <? if($net_type == 0) echo "checked" ?> /> Disable
				</td>
			</tr>
			<tr>
				<td class="theader">WLAN mode</td>
				<td>
					<input type="radio" value="0" name="tsf" onclick="chkChannel();" <? if($wmode == 0) echo "checked" ?> /> Ad-hoc<br />
					<input type="radio" value="1" name="tsf" onclick="chkChannel();" <? if($wmode == 1) echo "checked" ?> /> Infrastructure<br />
					<input type="radio" value="2" name="tsf" onclick="chkChannel();" <? if($wmode == 2) echo "checked" ?> /> Soft AP
				</td>
			</tr>
			<tr class="zebra">
				<td class="theader">Channel</td>	
				<td>
					<select name="channel">
						<option value="0" <? if($channel == 0) echo "selected" ?>>Auto</option>
						<option value="1" <? if($channel == 1) echo "selected" ?>>1</option>
						<option value="2" <? if($channel == 2) echo "selected" ?>>2</option>
						<option value="3" <? if($channel == 3) echo "selected" ?>>3</option>
						<option value="4" <? if($channel == 4) echo "selected" ?>>4</option>
						<option value="5" <? if($channel == 5) echo "selected" ?>>5</option>
						<option value="6" <? if($channel == 6) echo "selected" ?>>6</option>
						<option value="7" <? if($channel == 7) echo "selected" ?>>7</option>
						<option value="8" <? if($channel == 8) echo "selected" ?>>8</option>
						<option value="9" <? if($channel == 9) echo "selected" ?>>9</option>
						<option value="10" <? if($channel == 10) echo "selected" ?>>10</option>
						<option value="11" <? if($channel == 11) echo "selected" ?>>11</option>						
						<option value="12" <? if($channel == 12) echo "selected" ?>>12</option>
						<option value="13" <? if($channel == 13) echo "selected" ?>>13</option>						
					</select>

				</td>
			</tr>
			<tr>
				<td class="theader">SSID</td>	
				<td>
					<input type="text" name="ssid" size="20" maxlength="31" value="<? echo $ssid ?>">  
				</td>
			</tr>
			<tr class="zebra">
				<td class="theader">Shared Key</td>
				<td>
					<input type="text" name="shared_key" size="20" maxlength="63" value="<? echo $shared_key ?>"><br /> 
					(<input type="checkbox" name="hide_key" onclick="chkHideKey()" checked />hide key)
				</td>
			</tr>
			<tr>
				<td class="theader">802.1x</td>
				<td>	
					<input type="hidden" name="eap_type" value="<? echo $eap_type ?>">  				
					<select name="eap" disabled>
						<option value="0" <? if($eap_type == 0) echo "selected" ?>>None</option>
						<option value="1" <? if($eap_type == 1) echo "selected" ?>>EAP-TLS</option>
						<option value="2" <? if($eap_type == 2) echo "selected" ?>>EAP-TTLS</option>
						<option value="3" <? if($eap_type == 3) echo "selected" ?>>PEAP</option>
					</select>
				</td>
			</tr>
		</table>	
	</center>	
	</form>
	<br /><br /><br /><br />
	<div id="footer">
		<div class="superFooter">
		</div>
	</div>	
</body>
</html>
