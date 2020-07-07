<html>
<head>
<title>PHPoC / <?echo system("uname -i")?></title>
<meta name="viewport" content="width=device-width, initial-scale=0.7">
<style> body { text-align: center; } </style>
</head>
<body>

<h2>

ADC / Catalex Rotary Angle Sensor<br>

<br>

<?php 

include "/lib/sd_340.php";

adc_setup(0, 0); // adc0, channel 0

$adc_in = adc_in(0, 30);

printf("Voltage : %.2fV<br>\r\n", $adc_in / 4095.0 * 3.3);

?>

<br><a href="index.php">reload</a><br>

</h2>

</body>
</html>

