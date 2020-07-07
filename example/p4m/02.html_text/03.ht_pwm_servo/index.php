<?php

include_once "/lib/sd_340.php";

define("PWM_PERIOD", 20000); // 20000us (20ms)
define("WIDTH_MIN", 600);
define("WIDTH_MAX", 2450);

$flags = 0;
$angle = 0;

um_read(0, 0, $flags, 4); // read flags (offset 0, 32bit integer)
um_read(0, 4, $angle, 4); // read angle (offset 4, 32bit integer)

if(!$flags)
{
	ht_pwm_setup(0, (WIDTH_MIN + WIDTH_MAX) / 2, PWM_PERIOD, "us");

	$flags |= 0x00000001; // set init flag
	um_write(0, 0, int2bin($flags, 4)); // write flags (offset 0, 32bit integer)

	$angle = 90;
	um_write(0, 4, int2bin($angle, 4)); // write angle (offset 4, 32bit integer)
}

if(($cw = _GET("cw")))
	$delta = -(int)$cw; // clock wise
else
if(($ccw = _GET("ccw")))
	$delta = (int)$ccw; // counter clock wise
else
	$delta = 0;
	
if($delta)
{
	um_read(0, 4, $angle, 4); // read angle (offset 4, 32bit integer)

	$angle += $delta;

	if($angle > 180)
		$angle = 180;

	if($angle < 0)
		$angle = 0;

	um_write(0, 4, int2bin($angle, 4)); // write angle (offset 4, 32bit integer)

	$width = WIDTH_MIN + (int)round($angle / 180.0 * (WIDTH_MAX - WIDTH_MIN));

	ht_pwm_width(0, $width, PWM_PERIOD);
}

?>
<html>
<head>
<title>PHPoC / <?echo system("uname -i")?></title>
<meta name="viewport" content="width=device-width, initial-scale=0.7">
<style> body { text-align: center; } </style>
</head>
<body>

<h2>

HT / Tower Pro SG92R Micro Servo<br>

<br>

<a href="index.php?cw=45">-45'</a>&nbsp;
<a href="index.php?cw=15">-15'</a>
&nbsp;
<?php printf("CW&nbsp;&nbsp;%d'&nbsp;&nbsp;CCW", $angle); ?>
&nbsp;
<a href="index.php?ccw=15">+15'</a>&nbsp;
<a href="index.php?ccw=45">+45'</a>&nbsp;

</h2>

</body>
</html>
