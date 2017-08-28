<!DOCTYPE html>
<html>
<head>
<title>PHPoC / <?echo system("uname -i")?></title>
<meta name="viewport" content="width=device-width, initial-scale=0.7">
<style>
body { text-align: center; background-color: whiite;}
canvas { background-color: white; }
</style>
<script>
var MIN_TOUCH_RADIUS = 20;
var MAX_TOUCH_RADIUS = 200;
var CANVAS_WIDTH = 402, CANVAS_HEIGHT = 402;
var PIVOT_X = 201, PIVOT_Y = 201;
var plate_angle = 0;
var plate_img = new Image();
var click_state = 0;
var last_angle_pos = 0;
var mouse_xyra = {x:0, y:0, r:0.0, a:0.0};
var ws;

plate_img.src = "step_plate.png";

function init()
{
	var stepper = document.getElementById("stepper");
	var ctx = stepper.getContext("2d");

	stepper.width = CANVAS_WIDTH;
	stepper.height = CANVAS_HEIGHT;

	stepper.addEventListener("touchstart", mouse_down);
	stepper.addEventListener("touchend", mouse_up);
	stepper.addEventListener("touchmove", mouse_move);
	stepper.addEventListener("mousedown", mouse_down);
	stepper.addEventListener("mouseup", mouse_up);
	stepper.addEventListener("mousemove", mouse_move);

	ctx.translate(PIVOT_X, PIVOT_Y);
	rotate_plate(plate_angle);
}
function connect_onclick()
{
	if(ws == null)
	{
		ws = new WebSocket("ws://<?echo _SERVER("HTTP_HOST")?>/pes_2405_touch_rotate", "csv.phpoc");
		document.getElementById("ws_state").innerHTML = "CONNECTING";

		ws.onopen = ws_onopen;
		ws.onclose = ws_onclose;
		ws.onmessage = ws_onmessage;

		ws.onerror = function(){ alert("websocket error " + this.url) };
	}
	else
		ws.close();
}
function ws_onopen()
{
	document.getElementById("ws_state").innerHTML = "<font color='blue'>CONNECTED</font>";
	document.getElementById("bt_connect").innerHTML = "Disconnect";

	rotate_plate(plate_angle);
}
function ws_onclose()
{
	document.getElementById("ws_state").innerHTML = "<font color='gray'>CLOSED</font>";
	document.getElementById("bt_connect").innerHTML = "Connect";

	ws.onopen = null;
	ws.onclose = null;
	ws.onmessage = null;
	ws = null;

	rotate_plate(plate_angle);
}
function ws_onmessage(e_msg)
{
	e_msg = e_msg || window.event; // MessageEvent

	plate_angle = Number(e_msg.data);

	rotate_plate(plate_angle);

	//alert("msg : " + e_msg.data);
}
function rotate_plate(angle)
{
	var stepper = document.getElementById("stepper");
	var ctx = stepper.getContext("2d");

	ctx.clearRect(-PIVOT_X, -PIVOT_Y, CANVAS_WIDTH, CANVAS_HEIGHT);
	ctx.rotate(-angle / 180 * Math.PI);

	ctx.drawImage(plate_img, -PIVOT_X, -PIVOT_Y);

	ctx.rotate(angle / 180 * Math.PI);

	if(ws && (ws.readyState == 1))
		ws.send(plate_angle.toFixed(4) + "\r\n");

	ws_angle = document.getElementById("ws_angle");
	ws_angle.innerHTML = angle.toFixed(1);
}
function check_update_xyra(event, mouse_xyra)
{
	var x, y, r, a;
	var min_r, max_r, width;

	if(event.touches)
	{
		var touches = event.touches;

		x = (touches[0].pageX - touches[0].target.offsetLeft) - PIVOT_X;
		y = PIVOT_Y - (touches[0].pageY - touches[0].target.offsetTop);
	}
	else
	{
		x = event.offsetX - PIVOT_X;
		y = PIVOT_Y - event.offsetY;
	}

	/* cartesian to polar coordinate conversion */
	r = Math.sqrt(x * x + y * y);
	a = Math.atan2(y, x);

	mouse_xyra.x = x;
	mouse_xyra.y = y;
	mouse_xyra.r = r;
	mouse_xyra.a = a;

	if((r >= MIN_TOUCH_RADIUS) && (r <= MAX_TOUCH_RADIUS))
		return true;
	else
		return false;
}
function mouse_down()
{
	if(event.touches && (event.touches.length > 1))
		click_state = event.touches.length;

	if(click_state > 1)
		return;

	if(check_update_xyra(event, mouse_xyra))
	{
		click_state = 1;
		last_angle_pos = mouse_xyra.a / Math.PI * 180.0;
	}
}
function mouse_up()
{
	click_state = 0;
}
function mouse_move()
{
	var angle_pos, angle_offset;

	if(event.touches && (event.touches.length > 1))
		click_state = event.touches.length;

	if(!click_state || (click_state > 1))
		return;

	if(!check_update_xyra(event, mouse_xyra))
	{
		click_state = 0;
		return;
	}

	event.preventDefault();

	angle_pos = mouse_xyra.a / Math.PI * 180.0;

	if(angle_pos < 0.0)
		angle_pos = angle_pos + 360.0;

	angle_offset = angle_pos - last_angle_pos;
	last_angle_pos = angle_pos;

	if(angle_offset > 180.0)
		angle_offset = -360.0 + angle_offset;
	else
	if(angle_offset < -180.0)
		angle_offset = 360 + angle_offset;

	plate_angle += angle_offset;

	rotate_plate(plate_angle);
}
window.onload = init;
</script>
</head>

<body>

<h2>
Smart Expansion / Stepper Motor<br><br>
Angle <font id="ws_angle" color="blue">0</font><br>
<br>

<canvas id="stepper"></canvas>
<br><br>

WebSocket <font id="ws_state" color="gray">CLOSED</font>
</h2>

<p>
<button id="bt_connect" type="button" onclick="connect_onclick();">Connect</button>
</p>

</body>
</html>
