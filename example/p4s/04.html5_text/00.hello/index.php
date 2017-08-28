<html>
<head>
<title>PHPoC / <?echo system("uname -i")?></title>
<meta name="viewport" content="width=device-width, initial-scale=0.7">
<style>
body { text-align:center; }
textarea { width:400px; height:400px; padding:10px; font-family:courier; font-size:14px; }
 </style>
<script>
var ws;
var wc_max_len = 32768;
function ws_onopen()
{
	document.getElementById("ws_state").innerHTML = "OPEN";
	document.getElementById("wc_conn").innerHTML = "Disconnect";
}
function ws_onclose()
{
	document.getElementById("ws_state").innerHTML = "CLOSED";
	document.getElementById("wc_conn").innerHTML = "Connect";

	ws.onopen = null;
	ws.onclose = null;
	ws.onmessage = null;
	ws = null;
}
function wc_onclick()
{
	if(ws == null)
	{
		ws = new WebSocket("ws://<?echo _SERVER("HTTP_HOST")?>/WebConsole", "text.phpoc");
		document.getElementById("ws_state").innerHTML = "CONNECTING";

		ws.onopen = ws_onopen;
		ws.onclose = ws_onclose;
		ws.onmessage = ws_onmessage;
	}
	else
		ws.close();
}
function ws_onmessage(e_msg)
{
	e_msg = e_msg || window.event; // MessageEvent

	var wc_text = document.getElementById("wc_text");
	var len = wc_text.value.length;

	if(len > (wc_max_len + wc_max_len / 10))
		wc_text.innerHTML = wc_text.value.substring(wc_max_len / 10);

	wc_text.scrollTop = wc_text.scrollHeight;
	wc_text.innerHTML += e_msg.data;
}
function wc_clear()
{
	document.getElementById("wc_text").innerHTML = "";
}
</script>
</head>
<body>

<h2>
<p>
Web Console : <span id="ws_state">CLOSED</span><br>
</p>
<textarea id="wc_text" readonly="readonly"></textarea><br>
<button id="wc_conn" type="button" onclick="wc_onclick();">Connect</button>
<button id="wc_clear" type="button" onclick="wc_clear();">Clear</button>
</h2>

</body>
</html>

