<html>
<head><title>PHPoC</title></head>
<body>

<center>

<br>

<?php

include_once "/lib/sd_204.php";

for($port = 0; $port < 4; $port++)
{
	$out_data = _GET("do$port");

	if($out_data != "")
	{
		if($out_data == "0")
			dio_out(DO_0 + $port, LOW);
		else
		if($out_data == "1")
			dio_out(DO_0 + $port, HIGH);
	}
}

for($port = 0; $port < 4; $port++)
	echo "DI_$port ", dio_in(DI_0 + $port) ? "HIGH" : "LOW", "<br>\r\n";

echo "<a href='/'>read</a><br><br>\r\n";

for($port = 0; $port < 4; $port++)
{
	if(dio_in(DO_0 + $port))
		echo "DO_$port <a href='index.php?do$port=0'>HIGH</a><br>\r\n";
	else
		echo "DO_$port <a href='index.php?do$port=1'>LOW</a><br>\r\n";
}

echo "click link to toggle output<br>\r\n";

?>


</center>

</body>
</html>
