<!DOCTYPE html>
<html>
<head>
<title>PHPoC / <?echo system("uname -i")?></title>
<meta name="viewport" content="width=device-width, initial-scale=0.7">
<style> body { text-align: center; } </style>
<script>
var canvas_width = 400, canvas_height = 400;
var pivot_x = 200, pivot_y = 230;
var pivot_radius = 20, hand_radius = 180;
var ws;

function init()
{
	var gage = document.getElementById("gage_02");
	var ctx = gage.getContext("2d");

	gage.width = canvas_width;
	gage.height = canvas_height;
	gage.style.backgroundImage = "url('/gage_02.png')";

	ctx.translate(pivot_x, pivot_y);

	gage_02_rotate_hand(0);

	ws = new WebSocket("ws://<?echo _SERVER("HTTP_HOST")?>/light_sensor", "csv.phpoc");
	document.getElementById("ws_state").innerHTML = "CONNECTING";

	ws.onopen  = function(){ document.getElementById("ws_state").innerHTML = "OPEN" };
	ws.onclose = function(){ document.getElementById("ws_state").innerHTML = "CLOSED"};
	ws.onerror = function(){ alert("websocket error " + this.url) };

	ws.onmessage = ws_onmessage;
}
function ws_onmessage(e_msg)
{
	e_msg = e_msg || window.event; // MessageEvent

	var angle = Number(e_msg.data) / 1000.0 * 90.0;

	gage_02_rotate_hand(angle);
}
function gage_02_rotate_hand(angle)
{
	var gage = document.getElementById("gage_02");
	var ctx = gage.getContext("2d");
	var text;

	if((angle < 0) || (angle > 90))
		return;

	ctx.clearRect(-pivot_x, -pivot_y, canvas_width, canvas_height);
	ctx.rotate((angle + 45) / 180 * Math.PI);

	ctx.strokeStyle = "#000000";
	ctx.beginPath();
	ctx.moveTo(-pivot_radius, 0);
	ctx.lineTo(-hand_radius, 0);
	ctx.stroke();

	ctx.rotate(-(angle + 45) / 180 * Math.PI);

	angle = Math.floor(angle / 90 * 100);

	if(angle < 10)
		text = "00" + angle.toString();
	else
	if(angle < 100)
		text = "0" + angle.toString();
	else
		text = angle.toString();

	ctx.font = "24px Arial";
	ctx.fillText(text, -20, 50);
}
window.onload = init;
</script>
</head>
<body>

<h2>
ADC / Catalex Light Sensor<br>

<br>

<canvas id="gage_02"></canvas>

<p>
WebSocket : <span id="ws_state">CLOSED</span><br>
</p>

</h2>

</body>
</html>

