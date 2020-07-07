<html>
<head>
<title>PHPoC / <?echo system("uname -i")?></title>
<meta name="viewport" content="width=device-width, initial-scale=0.7">
<style> body { text-align: center; } </style>
</head>
<body>

<h2>

UIO / Catalex Touch Sensor<br>

<br>

<?php

include_once "/lib/sd_340.php";

define("IN_PIN", 0);

uio_setup(0, IN_PIN, "in");

$touch = uio_in(0, IN_PIN);

if($touch)
	echo "touch ON<br>\r\n";
else
	echo "touch OFF<br>\r\n";

?>

<br><a href="index.php">reload</a><br>

</h2>

</body>
</html>
