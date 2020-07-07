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
var SLIDE_HEIGHT = 200;
var SLIDE_LENGTH = 300;
var VALUE_MIN = -100;
var VALUE_MAX = 100;
var BUTTON_HEIGHT = parseInt(SLIDE_HEIGHT * 0.8);
var BUTTON_WIDTH = parseInt(BUTTON_HEIGHT / 2);
var SLIDE_WIDTH = parseInt(SLIDE_LENGTH + BUTTON_WIDTH * 1.1);
var slide_state;
var ws;

function init()
{
	var dc_slide = document.getElementById("dc_slide");

	dc_slide.width = SLIDE_WIDTH;
	dc_slide.height = SLIDE_HEIGHT;

	slide_state = {x:0, y:0, offset:0, click:false, identifier:null, ws_value:0};

	slide_state.x = parseInt(SLIDE_WIDTH / 2);
	slide_state.y = parseInt(SLIDE_HEIGHT / 2);

	update_slide(SLIDE_WIDTH / 2);

	dc_slide.addEventListener("touchstart", mouse_down);
	dc_slide.addEventListener("touchend", mouse_up);
	dc_slide.addEventListener("touchmove", mouse_move);

	dc_slide.addEventListener("mousedown", mouse_down);
	dc_slide.addEventListener("mouseup", mouse_up);
	dc_slide.addEventListener("mousemove", mouse_move);
	dc_slide.addEventListener("mouseout", mouse_up);
}
function connect_onclick()
{
	if(ws == null)
	{
		ws = new WebSocket("ws://<?echo _SERVER("HTTP_HOST")?>/pes_2404_pos_rpm", "csv.phpoc");
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

	update_slide(slide_state.x - slide_state.offset);
}
function ws_onclose()
{
	document.getElementById("ws_state").innerHTML = "<font color='gray'>CLOSED</font>";
	document.getElementById("bt_connect").innerHTML = "Connect";

	ws.onopen = null;
	ws.onclose = null;
	ws.onmessage = null;
	ws = null;

	update_slide(slide_state.x - slide_state.offset);
	slide_state.ws_value = 0;
}
function ws_onmessage(e_msg)
{
	var ws_pos = document.getElementById("ws_pos");
	var ws_rpm = document.getElementById("ws_rpm");

	e_msg = e_msg || window.event; // MessageEvent

	csv = e_msg.data.split(",");

	ws_pos.innerHTML = csv[0];
	ws_rpm.innerHTML = csv[1];

	//alert("msg : " + e_msg.data);
}
function update_slide(x)
{
	var debug = document.getElementById("debug");
	var dc_slide = document.getElementById("dc_slide");
	var ctx = dc_slide.getContext("2d");
	var slide_left, slide_ratio, slide_value;

	slide_left = (SLIDE_WIDTH - SLIDE_LENGTH) / 2;

	slide_state.x = x + slide_state.offset;

	if(slide_state.x < slide_left)
		slide_state.x = slide_left;

	if(slide_state.x > (slide_left + SLIDE_LENGTH))
		slide_state.x = slide_left + SLIDE_LENGTH;

	ctx.clearRect(0, 0, SLIDE_WIDTH, SLIDE_HEIGHT);

	ctx.fillStyle = "silver";
	ctx.beginPath();
	ctx.rect(slide_left, slide_state.y - 5, SLIDE_LENGTH, 10);
	ctx.fill();

	if(ws && (ws.readyState == 1))
	{
		ctx.strokeStyle = "blue";
		if(slide_state.click)
			ctx.fillStyle = "blue";
		else
			ctx.fillStyle = "skyblue";
	}
	else
	{
		ctx.strokeStyle = "gray";
		if(slide_state.click)
			ctx.fillStyle = "gray";
		else
			ctx.fillStyle = "silver";
	}

	ctx.beginPath();
	ctx.rect(slide_state.x - BUTTON_WIDTH / 2, slide_state.y - BUTTON_HEIGHT / 2, BUTTON_WIDTH, BUTTON_HEIGHT);
	ctx.fill();
	ctx.stroke();

	ctx.font = "30px Arial";
	ctx.textBaseline = "top";
	ctx.fillStyle = "white";

	slide_ratio = (slide_state.x - slide_left) / SLIDE_LENGTH;       // 0 ~ 1
	slide_value = parseInt(slide_ratio * (VALUE_MAX - VALUE_MIN) + VALUE_MIN); // VALUE_MIN ~ VALUE_MAX

	ctx.textAlign = "center";
	ctx.fillText(slide_value.toString(), slide_state.x, slide_state.y - BUTTON_HEIGHT / 2);

	if(ws && (ws.readyState == 1))
	{
		//debug.innerHTML = slide_state.ws_value + "/" + slide_value;

		if(slide_state.ws_value != slide_value)
		{
			ws.send(slide_value.toString() + "\r\n");

			slide_state.ws_value = slide_value;
		}
	}
}
function check_range_xy(x, y)
{
	var button_left, button_right, button_top, button_bottom;

	button_left = slide_state.x - BUTTON_WIDTH / 2;
	button_right = slide_state.x + BUTTON_WIDTH / 2;
	button_top = slide_state.y - BUTTON_HEIGHT / 2;
	button_bottom = slide_state.y + BUTTON_HEIGHT / 2;

	if((x > button_left) && (x < button_right) && (y > button_top) && (y < button_bottom))
		return true;
	else
		return false;
}
function mouse_down(event)
{
	var debug = document.getElementById("debug");
	var x, y;

	//debug.innerHTML = "";

	if(event.changedTouches)
	{
		for(var id = 0; id < event.changedTouches.length; id++)
		{
			var touch = event.changedTouches[id];

			x = touch.pageX - touch.target.offsetLeft;
			y = touch.pageY - touch.target.offsetTop;

			//debug.innerHTML = x + "/" + y + " ";

			if(check_range_xy(x, y))
			{
			 	if(!slide_state.click)
				{
					slide_state.offset = slide_state.x - x;
					slide_state.identifier = touch.identifier;
					slide_state.click = true;

					update_slide(x);
				}
			}
		}
	}
	else
	{
		x = event.offsetX;
		y = event.offsetY;

		//debug.innerHTML = x + "/" + y + " ";

		if(check_range_xy(x, y))
		{
			slide_state.offset = slide_state.x - x;
			slide_state.click = true;

			update_slide(x);
		}
	}

	event.preventDefault();
}
function mouse_up(event)
{
	var debug = document.getElementById("debug");

	//debug.innerHTML = "";

	if(event.changedTouches)
	{
		for(var id = 0; id < event.changedTouches.length; id++)
		{
			var touch = event.changedTouches[id];

			if(touch.identifier == slide_state.identifier)
			{
				slide_state.click = false;
				slide_state.identifier = null;

				if(document.getElementById("bt_center").checked == true)
				{
					slide_state.offset = 0;
					update_slide(SLIDE_WIDTH / 2);
				}
				else
					update_slide(slide_state.x - slide_state.offset);
			}
		}
	}
	else
	{
		if(slide_state.click)
		{
			slide_state.click = false;

			if(document.getElementById("bt_center").checked == true)
			{
				slide_state.offset = 0;
				update_slide(SLIDE_WIDTH / 2);
			}
			else
				update_slide(slide_state.x - slide_state.offset);
		}
	}

	event.preventDefault();
}
function mouse_move(event)
{
	var debug = document.getElementById("debug");
	var x, y;

	//debug.innerHTML = "";

	if(event.changedTouches)
	{
		for(var id = 0; id < event.changedTouches.length; id++)
		{
			var touch = event.changedTouches[id];

			if(touch.identifier == slide_state.identifier)
			{
				x = touch.pageX - touch.target.offsetLeft;
				y = touch.pageY - touch.target.offsetTop;

				update_slide(x);
			}
		}
	}
	else
	{
		if(slide_state.click)
		{
			x = event.offsetX;
			y = event.offsetY;

			update_slide(x);
		}
	}

	event.preventDefault();
}
function bt_center_change()
{
	if(document.getElementById("bt_center").checked == true)
	{
		slide_state.offset = 0;
		update_slide(SLIDE_WIDTH / 2);
	}
}
window.onload = init;
</script>
</head>

<body>

<h2>
Smart Expansion / DC Motor<br><br>
POS <font id="ws_pos" color="blue">0</font><br>
RPM <font id="ws_rpm" color="blue">0</font><br>

<canvas id="dc_slide"></canvas>
<br>

WebSocket <font id="ws_state" color="gray">CLOSED</font>
</h2>

<p>
<button id="bt_connect" type="button" onclick="connect_onclick();">Connect</button>
&nbsp;&nbsp;&nbsp;Return to Center<input id="bt_center" type="checkbox" onchange="bt_center_change()">
<span id="debug"></span>
</p>

</body>
</html>
