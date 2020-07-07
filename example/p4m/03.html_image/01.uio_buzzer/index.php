<html>
<head>
<title>PHPoC / <?echo system("uname -i")?></title>
<meta name="viewport" content="width=device-width, initial-scale=0.5">
<style> body { text-align: center; } </style>
</head>
<body>

<h2>

UIO / Catalex Buzzer<br>

<img src="uio_buzzer.jpg"><br>

<?php

include_once "/lib/sd_340.php";

define("OUT_PIN", 0);

uio_setup(0, OUT_PIN, "out");

if(($led0 = _GET("led0")))
{
	if($led0 == "low")
		uio_out(0, OUT_PIN, LOW);
	else
		uio_out(0, OUT_PIN, HIGH);
}

if(uio_in(0, OUT_PIN) == LOW)
{
	echo "Buzzer Status : OFF<br>\r\n";
	echo "<br><a href='index.php?led0=high'>Toggle</a><br>\r\n";
}
else
{
	echo "Buzzer Status : ON<br>\r\n";
	echo "<br><a href='index.php?led0=low'>Toggle</a><br>\r\n";
}

?>

</h2>

</body>
</html>
