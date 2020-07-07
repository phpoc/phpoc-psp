<?php

include_once "/lib/sd_340.php";

// setup trigger pulse timer
ht_ioctl(0, "set mode output pulse");
ht_ioctl(0, "set div us");
ht_ioctl(0, "set repc 1");
ht_ioctl(0, "set count 5 10"); // 10us pulse width
 
// setup echo capture timer
ht_ioctl(1, "reset");
ht_ioctl(1, "set div us");
ht_ioctl(1, "set mode capture toggle");
ht_ioctl(1, "set trigger from pin rise");
ht_ioctl(1, "set repc 4");
 
ht_ioctl(1, "start"); // we should start capture timer first
ht_ioctl(0, "start"); // start trigger pulse
 
usleep(100000); // sleep 100ms
 
// 1st capture value ("get count 0") is always zero.
// we should get 2nd capture value;
$us = ht_ioctl(1, "get count 1");

$dist = $us * 340.0 / 2; // us to meter conversion
$dist = $dist / 10000; // meter to centimeter conversion

?>
<html>
<head>
<title>PHPoC / <?echo system("uname -i")?></title>
<meta name="viewport" content="width=device-width, initial-scale=0.7">
<style> body { text-align: center; } </style>
</head>
<body>

<h2>

HT / HC-SR04 Ultrasonic Module<br>

<br>

<?php
printf("Pulse Width : %d us<br>\r\n", $us);
printf("Distance : %.1f cm<br>\r\n", $dist);
?>

<br><a href="index.php">reload</a><br>

</h2>

</body>
</html>
