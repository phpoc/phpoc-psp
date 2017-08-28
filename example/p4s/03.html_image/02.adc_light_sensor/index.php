<html>
<head>
<title>PHPoC / <?echo system("uname -i")?></title>
<meta name="viewport" content="width=device-width, initial-scale=0.5">
<style> body { text-align: center; } </style>
</head>
<body>

<h2>

ADC / Catalex Light Sensor<br>

<img src="adc_light_sensor.jpg"><br>

<?php 

include "/lib/sd_340.php";

define("ADC_MAX", 2520);

adc_setup(0, 0); // adc0, channel 0

$adc_in = adc_in(0, 30);

if($adc_in > ADC_MAX)
	$adc_in = ADC_MAX;

printf("Illuminance level : %d(%%)<br>\r\n", $adc_in * 100 / ADC_MAX);

?>

<br><a href="index.php">reload</a><br>

</h2>

</body>
</html>

