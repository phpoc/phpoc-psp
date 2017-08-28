<html>
<head>
<title>PHPoC / <?echo system("uname -i")?></title>
<meta name="viewport" content="width=device-width, initial-scale=0.5">
<style> body { text-align: center; } </style>
</head>
<body>

<h2>

UIO / On-Board User LED<br>

<br>

<?php

include_once "/lib/sd_340.php";

define("OUT_PIN", 30);

uio_setup(0, OUT_PIN, "out");

if(($led0 = _GET("led0")))
{
	if($led0 == "low")
		uio_out(0, OUT_PIN, LOW);
	else
		uio_out(0, OUT_PIN, HIGH);
}

if(uio_in(0, OUT_PIN) == LOW)
	echo "<a href='index.php?led0=high'><img src='button_push.png'></a><br>\r\n";
else
	echo "<a href='index.php?led0=low'><img src='button_pop.png'></a><br>\r\n";

?>

</h2>

</body>
</html>
