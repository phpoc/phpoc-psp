<!DOCTYPE html>
<html>
<head>
<title>PHPoC / <?echo system("uname -i")?></title>
<meta name="viewport" content="width=device-width, initial-scale=0.7">
<style> body { text-align: center; } </style>
<script>
var canvas_width = 95;
var canvas_height = 95;
var ws;
function init()
{
	var button = document.getElementById("button");

	button.width = canvas_width;
	button.height = canvas_height;

	button.addEventListener("touchstart", mouse_down);
	button.addEventListener("touchend", mouse_up);
	button.addEventListener("mousedown", mouse_down);
	button.addEventListener("mouseup", mouse_up);
	button.addEventListener("mouseout", mouse_up);

	update_button(0);

	ws = new WebSocket("ws://<?echo _SERVER("HTTP_HOST")?>/buzzer", "csv.phpoc");
	document.getElementById("ws_state").innerHTML = "CONNECTING";

	ws.onopen  = function(){ document.getElementById("ws_state").innerHTML = "OPEN" };
	ws.onclose = function(){ document.getElementById("ws_state").innerHTML = "CLOSED"};
	ws.onerror = function(){ alert("websocket error " + this.url) };

	ws.onmessage = ws_onmessage;
}
function ws_onmessage(e_msg)
{
	e_msg = e_msg || window.event; // MessageEvent

	alert("msg : " + e_msg.data);
}
function update_button(state)
{
	var button = document.getElementById("button");

	if(state)
		button.style.backgroundImage = "url('/button_push.png')";
	else
		button.style.backgroundImage = "url('/button_pop.png')";
}
function mouse_down()
{
	if(ws.readyState == 1)
		ws.send("1\r\n");

	update_button(1);

	event.preventDefault();
}
function mouse_up()
{
	if(ws.readyState == 1)
		ws.send("0\r\n");

	update_button(0);
}
window.onload = init;
</script>
</head>
<body>

<h2>
UIO / Catalex Buzzer<br>

<br>

<canvas id="button"></canvas>

<p>
WebSocket : <span id="ws_state">CLOSED</span><br>
<!--
ADC : <span id="debug"></span><br>
-->
</p>

</h2>

</body>
</html>

