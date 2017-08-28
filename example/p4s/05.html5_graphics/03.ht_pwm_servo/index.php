<!DOCTYPE html>
<html>
<head>
<title>PHPoC / <?echo system("uname -i")?></title>
<meta name="viewport" content="width=device-width, initial-scale=0.7">
<style>
body { text-align: center; }
canvas { background-color: #f0f0f0; }
</style>
<script>
var canvas_width = 401, canvas_height = 466;
var pivot_x = 200, pivot_y = 200;
var bracket_radius = 160, bracket_angle = 0;
var bracket_img = new Image();
var click_state = 0;
var last_angle = 0;
var mouse_xyra = {x:0, y:0, r:0.0, a:0.0};
var ws;

bracket_img.src = "servo_bracket.png";

function init()
{
	var servo = document.getElementById("servo");

	servo.width = canvas_width;
	servo.height = canvas_height;
	servo.style.backgroundImage = "url('/servo_body.png')";

	servo.addEventListener("touchstart", mouse_down);
	servo.addEventListener("touchend", mouse_up);
	servo.addEventListener("touchmove", mouse_move);
	servo.addEventListener("mousedown", mouse_down);
	servo.addEventListener("mouseup", mouse_up);
	servo.addEventListener("mousemove", mouse_move);

	var ctx = servo.getContext("2d");

	ctx.translate(pivot_x, pivot_y);

	rotate_bracket(0);

	ws = new WebSocket("ws://<?echo _SERVER("HTTP_HOST")?>/ht_pwm_servo", "csv.phpoc");
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
function rotate_bracket(angle)
{
	var servo = document.getElementById("servo");
	var ctx = servo.getContext("2d");

	ctx.clearRect(-pivot_x, -pivot_y, canvas_width, canvas_height);
	ctx.rotate(angle / 180 * Math.PI);

	ctx.drawImage(bracket_img, -pivot_x, -pivot_y);

	ctx.rotate(-angle / 180 * Math.PI);
}
function check_range_xyra(event, mouse_xyra)
{
	var x, y, r, a, rc_x, rc_y, radian;
	var min_r, max_r, width;

	if(event.touches)
	{
		var touches = event.touches;

		x = (touches[0].pageX - touches[0].target.offsetLeft) - pivot_x;
		y = pivot_y - (touches[0].pageY - touches[0].target.offsetTop);
		min_r = 60;
		max_r = pivot_x;
		width = 40;
	}
	else
	{
		x = event.offsetX - pivot_x;
		y = pivot_y - event.offsetY;
		min_r = 60;
		max_r = bracket_radius;
		width = 20;
	}

	/* cartesian to polar coordinate conversion */
	r = Math.sqrt(x * x + y * y);
	a = Math.atan2(y, x);

	mouse_xyra.x = x;
	mouse_xyra.y = y;
	mouse_xyra.r = r;
	mouse_xyra.a = a;

	radian = bracket_angle / 180 * Math.PI;

	/* rotate coordinate */
	rc_x = x * Math.cos(radian) - y * Math.sin(radian);
	rc_y = x * Math.sin(radian) + y * Math.cos(radian);

	if((r < min_r) || (r > max_r))
		return false;

	if((rc_y < -width) || (rc_y > width))
		return false;

	return true;
}
function mouse_down()
{
	if(event.touches && (event.touches.length > 1))
		click_state = event.touches.length;

	if(click_state > 1)
		return;

	if(check_range_xyra(event, mouse_xyra))
	{
		click_state = 1;
		last_angle = mouse_xyra.a / Math.PI * 180.0;
	}
}
function mouse_up()
{
	click_state = 0;
}
function mouse_move()
{
	var angle;

	if(event.touches && (event.touches.length > 1))
		click_state = event.touches.length;

	if(click_state > 1)
		return;

	if(!click_state)
		return;

	if(!check_range_xyra(event, mouse_xyra))
	{
		click_state = 0;
		return;
	}

	angle = mouse_xyra.a / Math.PI * 180.0;

	if((Math.abs(angle) > 90) && (angle * last_angle < 0))
	{
		if(last_angle > 0)
			last_angle = -180;
		else
			last_angle = 180;
	}

	bracket_angle += (last_angle - angle);
	last_angle = angle;

	if(bracket_angle > 90)
		bracket_angle = 90;

	if(bracket_angle < -90)
		bracket_angle = -90;

	rotate_bracket(bracket_angle);

	if(ws.readyState == 1)
		ws.send(Math.floor(bracket_angle) + "\r\n");

	debug = document.getElementById("debug");
	debug.innerHTML = Math.floor(bracket_angle);

	event.preventDefault();
}
window.onload = init;
</script>
</head>

<body>

<h2>
HT / Tower Pro SG92R Micro Servo<br>

<br>

<canvas id="servo"></canvas>

<p>
WebSocket : <span id="ws_state">null</span><br>
Angle : <span id="debug">0</span>
</p>
</h2>

</body>
</html>
