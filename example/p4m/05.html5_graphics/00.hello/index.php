<!DOCTYPE html>
<html>
<head>
<title>PHPoC / <?echo system("uname -i")?></title>
<meta name="viewport" content="width=device-width, initial-scale=0.7">
<style> body { text-align: center; } </style>
</head>
<body>

<canvas id="hello" width="400" height="200"></canvas>

<script>
var c = document.getElementById("hello");
var ctx = c.getContext("2d");

ctx.font = "40px Arial";
ctx.strokeStyle = "#000000";
ctx.strokeText("hello, world!", 100, 50);
</script>

</body>
</html>

