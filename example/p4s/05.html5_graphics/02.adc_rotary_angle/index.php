<!DOCTYPE html>
<html>
<head>
<title>PHPoC / <?echo system("uname -i")?></title>
<meta name="viewport" content="width=device-width, initial-scale=0.7">
<style> body { text-align: center; } </style>
<script>
var canvas_width = 400, canvas_height = 400;
var pivot_x = 200, pivot_y = 200;
var pivot_radius = 7;
var hand_radius = 90, hand_max_angle = 270;
var ws;

function init()
{
	var gage = document.getElementById("gage_01");
	var ctx = gage.getContext("2d");

	gage.width = canvas_width;
	gage.height = canvas_height;
	gage.style.backgroundImage = "url('/gage_01.png')";

	ctx.translate(pivot_x, pivot_y);

	gage_01_rotate_hand(0);

	ws = new WebSocket("ws://<?echo _SERVER("HTTP_HOST")?>/rotary_angle", "csv.phpoc");
	document.getElementById("ws_state").innerHTML = "CONNECTING";

	ws.onopen  = function(){ document.getElementById("ws_state").innerHTML = "OPEN" };
	ws.onclose = function(){ document.getElementById("ws_state").innerHTML = "CLOSED"};
	ws.onerror = function(){ alert("websocket error " + this.url) };

	ws.onmessage = ws_onmessage;
}
function ws_onmessage(e_msg)
{
	e_msg = e_msg || window.event; // MessageEvent

	var angle = Number(e_msg.data) / 1000.0 * hand_max_angle;

	gage_01_rotate_hand(angle);
}
function gage_01_rotate_hand(angle)
{
	var gage = document.getElementById("gage_01");
	var ctx = gage.getContext("2d");
	var text;

	if((angle < 0) || (angle > hand_max_angle))
		return;

	ctx.clearRect(-pivot_x, -pivot_y, canvas_width, canvas_height);
	ctx.rotate((angle - 45) / 180 * Math.PI);

	ctx.strokeStyle = "#f0f0f0";
	ctx.fillStyle = "#f0f0f0";
	ctx.beginPath();
	ctx.arc(0, 0, pivot_radius, Math.PI - Math.PI / 10, Math.PI + Math.PI / 10);
	ctx.lineTo(-hand_radius + 1, -3);
	ctx.lineTo(-hand_radius, -2);
	ctx.lineTo(-hand_radius, 2);
	ctx.lineTo(-hand_radius + 1, 3);
	ctx.lineTo(-pivot_radius, 3);
	ctx.fill();
	ctx.stroke();

	ctx.rotate(-(angle - 45) / 180 * Math.PI);

	voltage = angle / hand_max_angle * 3.3;

	ctx.font = "24px Arial";
	ctx.fillText(voltage.toFixed(2), -24, 50);
}
window.onload = init;
</script>
</head>
<body>

<h2>
ADC / Catalex Rotary Angle Sensor<br>

<canvas id="gage_01"></canvas>

<p>
WebSocket : <span id="ws_state">CLOSED</span><br>
<!--
ADC : <span id="debug"></span><br>
-->
</p>

</h2>

</body>
</html>

