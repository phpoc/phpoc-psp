<!DOCTYPE html>
<?php  
if((int)ini_get("init_net0"))
	$pid_net = pid_open("/mmap/net0");
else
	$pid_net = pid_open("/mmap/net1");
$hwaddr = pid_ioctl($pid_net, "get hwaddr");
$ipaddr = pid_ioctl($pid_net, "get ipaddr");
$netmask = pid_ioctl($pid_net, "get netmask");
$gwaddr = pid_ioctl($pid_net, "get gwaddr");
$nsaddr = pid_ioctl($pid_net, "get nsaddr");
pid_close($pid_net);

$pid_net1 = pid_open("/mmap/net1");
$wmode = pid_ioctl($pid_net1, "get mode");
$ssid = pid_ioctl($pid_net1, "get ssid");
$rssi = pid_ioctl($pid_net1, "get rssi");
$rsna = pid_ioctl($pid_net1, "get rsna");
$akm = pid_ioctl($pid_net1, "get akm");
$cipher = pid_ioctl($pid_net1, "get cipher");
pid_close($pid_net1);
?>
					
<html>
<head>
	<title>PHPoC</title>
	<meta content="initial-scale=0.7, maximum-scale=1.0, minimum-scale=0.5, width=device-width, user-scalable=yes" name="viewport">
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /> 
	<style type="text/css">
		body { font-family: verdana, Helvetica, Arial, sans-serif, gulim; }
		h1 { font-weight: bold; font-family : verdana, Helvetica, Arial, verdana, sans-serif, gulim; font-size:15pt; padding-bottom:10px;}
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
</head>
<body>
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
					</div>
				</div>
			</center>
		</div>
		
		<div class="subHeader">
		</div>		
	</div>	
	<br /><br /><br /><br />
	<form name="pbh_104_ss4">
	<input type="hidden" name="mode" value="">		
	<center>	
		<hr style="margin:50px 0 -10px 0; width:430px;" size="6" noshade>
		<h1>System Information</h1>
		<table>
			<tr class="zebra">
				<td width="190px" class="theader"><?php echo "Product name";?></td>	
				<td>
					<?php echo system("uname -i") . "\r\n";?>
				</td>
			</tr>
			<tr>
				<td width="190px" class="theader"><?php echo "MAC address";?></td>	
				<td>
					<?php echo $hwaddr;?>
				</td>
			</tr>
			<tr class="zebra">
				<td width="190px" class="theader"><?php echo "Firmware name";?></td>	
				<td>
					<?php echo system("uname -f") . "\r\n";?>
				</td>
			</tr>
			<tr>
				<td width="190px" class="theader"><?php echo "Firmware version";?></td>	
				<td>
					<?php echo system("uname -v") . "\r\n";?>
				</td>
			</tr>
		</table>
		
		<hr style="margin:40px 0 -10px 0; width:430px;" size="6" noshade>
		<h1>Network Information</h1>
		<table>
			<tr class="zebra">
				<td width="190px" class="theader"><?php echo "IP address";?></td>	
				<td><?php echo $ipaddr;?></td>
			</tr>
			<tr>
				<td class="theader"><?php echo "Subnet mask";?></td>	
				<td><?php echo $netmask;?></td>
			</tr>
			<tr class="zebra">
				<td class="theader"><?php echo "Gateway IP address";?></td>	
				<td><?php echo $gwaddr;?></td>
			</tr>
			<tr>
				<td class="theader"><?php echo "DNS IP address";?></td>
				<td><?php echo $nsaddr;?></td>
			</tr>
		</table>
		
		<?php  	
		if ($wmode != "")
		{
		?>
		<hr style="margin:40px 0 -10px 0; width:430px;" size="6" noshade>
		<h1>Wireless LAN Information</h1>
		<table>
			<tr class="zebra">
				<td width="190px" class="theader"><?php echo "WLAN mode";?></td>	
				<td>
					<?php  					
					switch($wmode)
					{
						case "INFRA":
							$wmode = "Infrastructure";
							break;
						case "IBSS":
							$wmode = "Ad-hoc";
							break;
						case "AP":
							$wmode = "Soft AP";
							break;
					}
					
					echo $wmode;
					?>
				</td>
			</tr>
			<tr>
				<td width="190px" class="theader"><?php echo "SSID";?></td>	
				<td>
					<?php echo $ssid;?>
				</td>
			</tr>
			<tr class="zebra">
				<td width="190px" class="theader"><?php echo "Signal strength";?></td>	
				<td>
					<?php echo "-",$rssi,"dbm";?>
				</td>
			</tr>
			<tr>
				<td width="190px" class="theader"><?php echo "Security";?></td>	
				<td>
					<?php  
					if($rsna == "")
						echo "NONE";
					else
						echo $rsna;			
					?>
				</td>
			</tr>
			<tr class="zebra">
				<td width="190px" class="theader"><?php echo "Key Management";?></td>	
				<td>
					<?php  
					if($akm == "")
						echo "-";
					else
						echo $akm;	
					?>
				</td>
			</tr>
			<tr>
				<td width="190px" class="theader"><?php echo "Encryption";?></td>	
				<td>
					<?php  
					if($cipher == "")
						echo "-";
					else
						echo $cipher;	
					?>
				</td>
			</tr>
		</table>
		<?php 
		}
		?>
	</center>	
	</form>
	<br /><br />
	<div id="footer">
		<div class="superFooter">
		
		</div>
	</div>	
</body>
</html>
