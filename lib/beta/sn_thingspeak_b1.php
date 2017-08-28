<?php

// $psp_id sn_thingspeak.php date 20170720
// thingspeak communication helper

include_once "/lib/sn_http_b1.php";
include_once "/lib/sn_json_b1.php";

// ThingSpeak REST API
//
// * Channels
// - Create a Channel
//   POST https://api.thingspeak.com/channels
//   POST https://api.thingspeak.com/channels.json
//   POST https://api.thingspeak.com/channels.xml
// - Get Status Updates
//   GET https://api.thingspeak.com/channels/CHANNEL_ID/status.json
//   GET https://api.thingspeak.com/channels/CHANNEL_ID/status.xml
// - List Public Channels
//   GET https://api.thingspeak.com/channels/public.json
//   GET https://api.thingspeak.com/channels/public.xml
// - List All Channels of a User
//   GET https://api.thingspeak.com/channels.json?api_key=XXXXXXXXXXXXXXXX
//   GET https://api.thingspeak.com/channels.xml?api_key=XXXXXXXXXXXXXXXX
// - List All Your Public Channels
//   GET https://api.thingspeak.com/users/USER_ID/channels
//   GET https://api.thingspeak.com/users/USER_ID/channels.json
//   GET https://api.thingspeak.com/users/USER_ID/channels.xml
// - View a Channel
//   GET https://api.thingspeak.com/channels/CHANNEL_ID
//   GET https://api.thingspeak.com/channels/CHANNEL_ID.json
//   GET https://api.thingspeak.com/channels/CHANNEL_ID.xml
// - Update a Channel
//   PUT https://api.thingspeak.com/channels/CHANNEL_ID
//   PUT https://api.thingspeak.com/channels/CHANNEL_ID.json
//   PUT https://api.thingspeak.com/channels/CHANNEL_ID.xml
// - Clear a Channel
//   DELETE https://api.thingspeak.com/channels/CHANNEL_ID/feeds
//   DELETE https://api.thingspeak.com/channels/CHANNEL_ID/feeds.json
//   DELETE https://api.thingspeak.com/channels/CHANNEL_ID/feeds.xml
// - Delete a Channel
//   DELETE https://api.thingspeak.com/channels/CHANNEL_ID
//   DELETE https://api.thingspeak.com/channels/CHANNEL_ID.json
//   DELETE https://api.thingspeak.com/channels/CHANNEL_ID.xml
//
// * Channel Feeds
// - Bulk-Update a Channel Feed
//   POST https://api.thingspeak.com/channels/CHANNEL_ID/bulk_update.json
// - Update a Channel Feed
//   POST https://api.thingspeak.com/update
//   POST https://api.thingspeak.com/update.json
//   POST https://api.thingspeak.com/update.xml
// - Get a Channel Feed
//   GET https://api.thingspeak.com/channels/CHANNEL_ID/feeds
//   GET https://api.thingspeak.com/channels/CHANNEL_ID/feeds.json
//   GET https://api.thingspeak.com/channels/CHANNEL_ID/feeds.xml
// - Get a Channel Field Feed
//   GET https://api.thingspeak.com/channels/CHANNEL_ID/FIELD_ID
//   GET https://api.thingspeak.com/channels/CHANNEL_ID/FIELD_ID.json
//   GET https://api.thingspeak.com/channels/CHANNEL_ID/FIELD_ID.xml
//

define("THINGSPEAK_PROTOCOL", "http");
//define("THINGSPEAK_PROTOCOL", "https");

$sn_ts_channel_id = 0;
$sn_ts_write_key = "";
$sn_ts_read_key = "";
$sn_ts_http_status = 0;

function thingspeak_setup($udp_id, $tcp_id, $dns_server = "", $ip6 = false)
{
	http_setup($udp_id, $tcp_id, $dns_server, $ip6);
}

function thingspeak_channel($channel_id, $write_key = "", $read_key = "")
{
	global $sn_ts_channel_id;
	global $sn_ts_write_key, $sn_ts_read_key;

	$sn_ts_channel_id = $channel_id;
	$sn_ts_write_key = $write_key;
	$sn_ts_read_key = $read_key;
}

function thingspeak_post($post_api, $post_body)
{
	global $sn_ts_write_key;
	global $sn_ts_http_status;

	$post_api = ltrim($post_api, "/");
	$post_len = strlen($post_body);

	http_req_header("Content-Type: application/x-www-form-urlencoded\r\n");
	http_req_header("Content-Length: $post_len\r\n");
	http_req_header("X-THINGSPEAKAPIKEY: $sn_ts_write_key\r\n");

	$resp_head = http_request("post", THINGSPEAK_PROTOCOL . "://api.thingspeak.com/$post_api", $post_body);

	if(!$resp_head)
	{
		echo "sn_thingspeak: connection failed\r\n";
		return 0;
	}

	$sn_ts_http_status = (int)http_find_header($resp_head, "Status-Code");

	if($sn_ts_http_status != 200)
		echo "sn_thingspeak: unexpected status $sn_ts_http_status\r\n";

	$resp_body = "";

	http_read_sync($resp_body);
	http_close();

	return $resp_body;
}

function thingspeak_update($post_body)
{
	return thingspeak_post("update", $post_body);
}

function thingspeak_get_channel($channel_api)
{
	global $sn_ts_channel_id;
	global $sn_ts_read_key;
	global $sn_ts_http_status;

	$channel_api = ltrim($channel_api, "/");

	if($sn_ts_read_key)
		http_req_header("X-THINGSPEAKAPIKEY: $sn_ts_read_key\r\n");

	$resp_head = http_request("get", THINGSPEAK_PROTOCOL . "://api.thingspeak.com/channels/$sn_ts_channel_id/$channel_api");

	if(!$resp_head)
	{
		echo "sn_thingspeak: connection failed\r\n";
		return 0;
	}

	$sn_ts_http_status = (int)http_find_header($resp_head, "Status-Code");

	if($sn_ts_http_status != 200)
		echo "sn_thingspeak: unexpected status $sn_ts_http_status\r\n";

	$resp_body = "";

	http_read_sync($resp_body);
	http_close();

	return $resp_body;
}

function thingspeak_status()
{
	global $sn_ts_http_status;

	return $sn_ts_http_status;
}

?>
