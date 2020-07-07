<html>
<head>
<title>PHPoC / <?echo system("uname -i")?></title>
<meta name="viewport" content="width=device-width, initial-scale=0.5">
<style> body { text-align: center; } </style>
</head>
<body>

<h2>

Smart Expansion / Relay Output<br>

<br>

<?php

include_once "/lib/sd_spc.php";

define("IO_OUT_SID", 1);

for($port = 0; $port < 4; $port++)
{
	if(($state = _GET("led$port")))
	{
		if($state == "low")
			spc_request_dev(IO_OUT_SID, "set $port output low");
		else
			spc_request_dev(IO_OUT_SID, "set $port output high");
	}

	if(spc_request_dev(IO_OUT_SID, "get $port output") == "1")
		echo "<a href='index.php?led$port=low'><img src='button_push.png'></a>\r\n";
	else
		echo "<a href='index.php?led$port=high'><img src='button_pop.png'></a>\r\n";
}

?>

</h2>

</body>
</html>
