<?php

// $psp_id sn_mqtt_b1.php date 20200703

/**
PHPoC MQTT Client library

*2020-01-03
	- adds mqtt_tcp_ioctl($mqtt_id, $cmd) function
	- modifies mqtt_connect() function

*2019-12-18
	- fixed some minor bugs during this time

*2019-07-05
	- adding the QoS validation

*2018-07-18
	- Support IPv6

*2017-02-03
	- Support MQTT over TLS/SSL

*2016-09-01.
	- Support MQTT Version 3.1 and 3.1.1
	- Document Reference:
		+ MQTT Version 3.1:   http://public.dhe.ibm.com/software/dw/webservices/ws-mqtt/mqtt-v3r1.html
		+ MQTT Version 3.1.1: http://docs.oasis-open.org/mqtt/mqtt/v3.1.1/mqtt-v3.1.1.html
	- History:
		+ 2016-09-01: Support MQTT Version 3.1 and 3.1.1
	- Testing succesufully with:
		+ Mosquitto Broker installed in my computer
		+ iot.eclipse.org
		+ broker.hivemq.com
		+ test.mosquitto.org
		+ broker.mqttdashboard.com
		+ m11.cloudmqtt.com
		In case clean session is set to false, it does work well with some servers due to sever send a lot of packet continously, PHPoC has the limit of embedded system.
		Clean session false is not recommended to use.
	- QoS Level: 0, 1, 2.
	- Note:
		+ Message delivery retry:  http://public.dhe.ibm.com/software/dw/webservices/ws-mqtt/mqtt-v3r1.html#retry
			* This is optional, default is disable. User can enable it by using mqtt_setup() function.
			* If retry option is anable, the max time of retry is 10. User can change this value by changing MQTT_RESEND_MAX_NUM.
			Note: Resend process is performed in a blocking loop, be careful when use this option.

**/

// Constants

define("MQTT_VER_3_1",   3);
define("MQTT_VER_3_1_1", 4);
define("MQTT_PROTOCOL_NAME_3_1",   "MQIsdp");
define("MQTT_PROTOCOL_NAME_3_1_1", "MQTT");

// Message type
define("MQTT_CTRL_CONNECT",     0x1);
define("MQTT_CTRL_CONNECTACK",  0x2);
define("MQTT_CTRL_PUBLISH",     0x3);
define("MQTT_CTRL_PUBACK",      0x4);
define("MQTT_CTRL_PUBREC",      0x5);
define("MQTT_CTRL_PUBREL",      0x6);
define("MQTT_CTRL_PUBCOMP",     0x7);
define("MQTT_CTRL_SUBSCRIBE",   0x8);
define("MQTT_CTRL_SUBACK",      0x9);
define("MQTT_CTRL_UNSUBSCRIBE", 0xA);
define("MQTT_CTRL_UNSUBACK",    0xB);
define("MQTT_CTRL_PINGREQ",     0xC);
define("MQTT_CTRL_PINGRESP",    0xD);
define("MQTT_CTRL_DISCONNECT",  0xE);

// MQTT state
define("MQTT_DISCONNECTED", 0);
define("MQTT_CONNECTED",    1);
define("MQTT_PINGING",      2);

// MQTT security
define("MQTT_PLAIN", 		    0);
define("MQTT_SSL",    		    1);
define("MQTT_WEBSOCKET",        2);
define("MQTT_WEBSOCKET_SSL",    3);

// Message flags
define("MQTT_HEAD_FLAG_RETAIN", 0x01);
define("MQTT_HEAD_FLAG_QOS_1",  0x02);
define("MQTT_HEAD_FLAG_QOS_2",  0x04);
define("MQTT_HEAD_FLAG_DUP",    0x08);

define("MQTT_CONN_FLAG_CLEAN",		0x02);
define("MQTT_CONN_FLAG_WILL",		0x04);
define("MQTT_CONN_FLAG_WILL_QOS1",	0x08);
define("MQTT_CONN_FLAG_WILL_QOS2",	0x10);
define("MQTT_CONN_FLAG_WILL_RETAIN",0x20);
define("MQTT_CONN_FLAG_PASSWORD",    0x40);
define("MQTT_CONN_FLAG_USERNAME",    0x80);

/* User-settable flags */

define("MQTT_QOS0",	0);
define("MQTT_QOS1",	1);
define("MQTT_QOS2",	2);
define("MQTT_QOS3",	3);

define("MQTT_CONN_SSL",		0x01);
define("MQTT_CONN_WS",		0x02);
define("MQTT_CONN_WSS",		0x04);
define("MQTT_CONN_CLEAN",	0x08);
define("MQTT_WILL_RETAIN",	0x10);
define("MQTT_PUB_RETAIN",	0x20);

/*
Keep Alive timer.
	-Adjust as necessary, in seconds. Default to 1 minute.
	-See http://public.dhe.ibm.com/software/dw/webservices/ws-mqtt/mqtt-v3r1.html#keep-alive-timer
*/
define("MQTT_CONN_KEEPALIVE",  60);

/*
These values are timeout for wating reponse from broker.
	-Adjust as necessary according to network latency, in milliseconds.
*/
define("MQTT_TIMEOUT_CONNECT_MS",     10000); // between CONNECT and CONNECTACK.
define("MQTT_TIMEOUT_PUBLISH_MS",     500); // between PUBLISH and PUBACK/PUBREC. between PUBREC and PUBREL. between PUBREL and PUBCOMP.
define("MQTT_TIMEOUT_SUBSCRIBE_MS",   500); // between SUBSCRIBE and SUBACK.
define("MQTT_TIMEOUT_UNSUBSCRIBE_MS", 500); // between UNSUBSCRIBE and UNSUBACK.
define("MQTT_TIMEOUT_PING_MS",        500); // between PINGREQ and PINGRESP.

define("MQTT_RETURN_CONN_LOST",		-3);
define("MQTT_RETURN_TIMEOUT",		-2);
define("MQTT_RETURN_NOT_FOUND",		-1);
define("MQTT_RETURN_SUCCESS",		1);
define("MQTT_RETURN_MSG_DUPLICATE",	0);
define("MQTT_RETURN_MSG_NEW",		2);

define("MQTT_RESEND_MAX_NUM", 0);

//Global variables
$sn_mqtt_default_flag = 0;
$sn_mqtt_ipv6 = false;

$sn_mqtt_tcp_id = array(0);
$sn_mqtt_tcp_pid = array(0);
$sn_mqtt_broker_hostname = array("");
$sn_mqtt_broker_port = array(1883);

$sn_mqtt_client_id = array("");

$sn_mqtt_version = array(MQTT_VER_3_1);
$sn_mqtt_alive_start = array(0);
$sn_mqtt_msg_id = array(1); // Do not use Message ID 0. It is reserved as an invalid Message ID.

$sn_mqtt_msg_flag = array(0);
$sn_mqtt_state = array(MQTT_DISCONNECTED);

$sn_mqtt_will_topic = array("");
$sn_mqtt_will_message = array("");
$sn_mqtt_will_qos = array(0);

$sn_mqtt_username = array("");
$sn_mqtt_password = array("");

$sn_mqtt_recv_buffer = "";
$sn_mqtt_packet_manager = "";
$sn_mqtt_unack_list = array("");

//To store subsription list
$sn_mqtt_subs_list = array("");

/*
This function is to get value of timer.
*/
function sn_mqtt_get_tick()
{
	while(($pid = pid_open("/mmap/st9", O_NODIE)) == -EBUSY)
		usleep(500);

	if(!pid_ioctl($pid, "get state"))
		pid_ioctl($pid, "start");

	$tick = pid_ioctl($pid, "get count");
	pid_close($pid);

	return $tick;
}

/*
Encode a length of a message.
Parameters:
	-$len: length to be encoded.
Return: The encoded data.
*/
function sn_mqtt_encode_length($length)
{
	$ret = "";

	do
	{
		$digit = $length % 128;
		$length = $length >> 7;
		// If there are more digits to encode, set the top bit of this digit
		if($length > 0)
			$digit = ($digit | 0x80);

		$ret .= sprintf("%c", $digit);
	} while($length > 0);

	return $ret;
}

/*
Decode a length of a message (Remaining Length field).
Parameters:
	-$pkt: message to be decoded.
Return: the length of message( excluding size of fixed header).
*/
function sn_mqtt_decode_length($pkt)
{
	$multiplier = 1;
	$value = 0 ;
	$i = 1;

	do
	{
		$digit = bin2int($pkt[$i], 0, 1);
		$value += ($digit & 127) * $multiplier;
		$multiplier *= 128;
		$i++;
	} while(($digit & 128) != 0);

	return $value;
}

/*
Attach two-byte length before a string.
Parameters:
	-$str: string to be encoded.
Return: new string which is attached the length.
*/
function sn_mqtt_encode_string($str)
{
	$len = strlen($str);
	$msb = $len >> 8;
	$lsb = $len & 0xff;
	$ret = sprintf("%c", $msb);
	$ret .= sprintf("%c", $lsb);
	$ret .= $str;

	return $ret;
}

/*
Get messsage quality of service.
Parameters:
	-$pkt: message to get QoS.
Return:	QoS of the message.
*/
function sn_mqtt_get_message_qos($pkt)
{
	$qos = (bin2int($pkt[0], 0, 1) & (MQTT_HEAD_FLAG_QOS_1 | MQTT_HEAD_FLAG_QOS_2)) >> 1;

	return $qos;
}

/*
Get retain flag of message.
Parameters:
	-$pkt: message to get retain.
Return:	retain status of the message.
*/
function sn_mqtt_get_message_retain($pkt)
{
	$retain = bin2int($pkt[0], 0, 1) & MQTT_HEAD_FLAG_RETAIN ;

	return $retain;
}

/*
Get DUP flag of message.
Parameters:
	-$pkt: message to get DUP flag.
Return:	DUP flag status of the message.
*/
function sn_mqtt_get_message_dup($pkt)
{
	$dup = (bin2int($pkt[0], 0, 1) & MQTT_HEAD_FLAG_DUP) >> 3 ;

	return $dup;
}

/*
Get messsage identifier.
Parameters:
	-$pkt: message to get id.
Return:
	- Identifier number of the message.
	- 0 if message does not have ID.
*/
function sn_mqtt_get_message_id($pkt)
{
	$msg_type = bin2int($pkt[0], 0, 1) >> 4;

	$remain_length  = sn_mqtt_decode_length($pkt);
	$var_head_pos = strlen($pkt) - $remain_length;

	$msg_id = 0;

	switch($msg_type)
	{
		case MQTT_CTRL_PUBLISH:
			$qos = (bin2int($pkt[0], 0, 1) & (MQTT_HEAD_FLAG_QOS_1 | MQTT_HEAD_FLAG_QOS_2)) >> 1;

			if($qos)
			{
				$msb = bin2int($pkt[$var_head_pos], 0, 1);
				$lsb = bin2int($pkt[$var_head_pos + 1], 0, 1);
				$topic_length = ($msb << 8) + $lsb;

				$msb_pos = $var_head_pos + 2 + $topic_length;

				$msb = bin2int($pkt[$msb_pos], 0, 1);
				$lsb = bin2int($pkt[$msb_pos + 1], 0, 1);
				$msg_id = ($msb << 8) + $lsb;
			}
			break;

		case MQTT_CTRL_PUBACK:
		case MQTT_CTRL_PUBREC:
		case MQTT_CTRL_PUBREL:
		case MQTT_CTRL_PUBCOMP:
		case MQTT_CTRL_SUBSCRIBE:
		case MQTT_CTRL_SUBACK:
		case MQTT_CTRL_UNSUBSCRIBE:
		case MQTT_CTRL_UNSUBACK:
			$msb = bin2int($pkt[$var_head_pos], 0, 1);
			$lsb = bin2int($pkt[$var_head_pos + 1], 0, 1);
			$msg_id = ($msb << 8) + $lsb;
			break;

		default:
			$msg_id = 0;
	}

	return $msg_id;
}

/*
Get messsage payload.
Parameters:
	-$pkt: message to get payload.
Return: payload of the message
*/
function sn_mqtt_get_message_payload($pkt)
{
	$msg_type = bin2int($pkt[0], 0, 1) >> 4;

	$remain_length  = sn_mqtt_decode_length($pkt);
	$var_head_pos = strlen($pkt) - $remain_length;

	// types of message have a payload: CONNECT, SUBSCRIBE, SUBACK, PUBLISH.
	switch($msg_type)
	{
		case MQTT_CTRL_SUBSCRIBE:
		case MQTT_CTRL_SUBACK:
			$payload_pos = $var_head_pos + 2; // two bytes of message identifier
			$payload_length = $remain_length - 2;
			$payload =  substr($pkt, $payload_pos, $payload_length);
			break;

		case MQTT_CTRL_CONNECT:
			// Protocol Name
			$pointer = $var_head_pos;
			$msb = bin2int($pkt[$pointer++], 0, 1);
			$lsb = bin2int($pkt[$pointer++], 0, 1);
			$length = ($msb << 8) + $lsb;
			$pointer +=$length;
			$pointer += 4; // 1 byte version number, 1 byte connect flag,  byte keep-alive-timer.
			$payload_length = strlen($pkt) - $pointer;
			$payload =  substr($pkt, $pointer, $payload_length);
			break;

		case MQTT_CTRL_PUBLISH:
			$pointer = $var_head_pos;
			$msb = bin2int($pkt[$pointer++], 0, 1);
			$lsb = bin2int($pkt[$pointer++], 0, 1);
			$topic_length = ($msb << 8) + $lsb;
			$pointer += $topic_length;

			$qos = (bin2int($pkt[0], 0, 1) & (MQTT_HEAD_FLAG_QOS_1 | MQTT_HEAD_FLAG_QOS_2)) >> 1;

			if($qos)
				$pointer += 2; // message identifier.

			$payload_length = strlen($pkt) - $pointer;

			if($payload_length <= 0)
				$payload = "";
			else
				$payload =  substr($pkt, $pointer, $payload_length);

			break;

		default:
			$payload = "";
	}

	return $payload;
}

/*
Get topic of publish packet.
Parameters:
	-$pkt: publish packet .
Return: topic
*/
function sn_mqtt_get_topic($pkt)
{
	$msg_type = bin2int($pkt[0], 0, 1) >> 4;

	if($msg_type != MQTT_CTRL_PUBLISH)
		return "";

	$remain_length  = sn_mqtt_decode_length($pkt);
	$var_head_pos = strlen($pkt) - $remain_length;
	$pointer = $var_head_pos;
	$msb = bin2int($pkt[$pointer++], 0, 1);
	$lsb = bin2int($pkt[$pointer++], 0, 1);
	$topic_length = ($msb << 8) + $lsb;

	$topic = substr($pkt, $pointer, $topic_length);

	return $topic;
}

/*
Get content of publish packet.
Parameters:
	-$pkt: publish packet .
Return: content
*/
function sn_mqtt_get_content($pkt)
{
	return sn_mqtt_get_message_payload($pkt);
}

/*
Create a connect packet.
Parameters:
	- $clean_flag: Clean Session flag. Default: true.
	- $will:
		+ if set to "", the will flag is unset.
		+ if set to an array($will_qos, $will_retain, $will_topic, $will_message) which contains Will QoS, Will Retain flag, Will Topic and Will Message respectively,
		  the will flag is set.
		+ Default: "".
	- $username:
		+ if set to "", the username flag is unset.
		+ otherwise, the username flag is set and username is $username.
		+ Default: "".
	- $password:
		+ if set to "", the password flag is unset.
		+ otherwise, the password flag is set and password is $password.
		+ Default: "".
Return: The created packet.
*/
function sn_mqtt_create_connect_packet($mqtt_id)
{
	global $sn_mqtt_version;
	global $sn_mqtt_msg_flag;
	global $sn_mqtt_client_id;
	global $sn_mqtt_username, $sn_mqtt_password;
	global $sn_mqtt_will_topic, $sn_mqtt_will_message, $sn_mqtt_will_qos;

	$conn_flag = 0;

	if($sn_mqtt_msg_flag[$mqtt_id] & MQTT_CONN_CLEAN)
		$conn_flag |= MQTT_CONN_FLAG_CLEAN;

	if($sn_mqtt_will_topic[$mqtt_id])
	{
		$conn_flag |= MQTT_CONN_FLAG_WILL;

		if($sn_mqtt_will_qos[$mqtt_id] == 2)
			$conn_flag |= MQTT_CONN_FLAG_WILL_QOS2;
		else
		if($sn_mqtt_will_qos[$mqtt_id] == 1)
			$conn_flag |= MQTT_CONN_FLAG_WILL_QOS1;

		if($sn_mqtt_msg_flag[$mqtt_id] & MQTT_WILL_RETAIN)
			$conn_flag |= MQTT_CONN_FLAG_WILL_RETAIN;
	}

	if($sn_mqtt_username[$mqtt_id])
	{
		$conn_flag |= MQTT_CONN_FLAG_USERNAME;

		if($sn_mqtt_password[$mqtt_id])
			$conn_flag |= MQTT_CONN_FLAG_PASSWORD;
	}

	if($sn_mqtt_version[$mqtt_id] == MQTT_VER_3_1)
		$variable_header = sn_mqtt_encode_string(MQTT_PROTOCOL_NAME_3_1); // Protocol name
	else
	if($sn_mqtt_version[$mqtt_id] == MQTT_VER_3_1_1)
		$variable_header = sn_mqtt_encode_string(MQTT_PROTOCOL_NAME_3_1_1); // Protocol name

	/* Variable header */

	$variable_header .= sprintf("%c", $sn_mqtt_version[$mqtt_id]); // Protocol Version Number
	$variable_header .= sprintf("%c", $conn_flag); // Connect Flags
	$variable_header .= sprintf("%c", MQTT_CONN_KEEPALIVE >> 8);
	$variable_header .= sprintf("%c", MQTT_CONN_KEEPALIVE & 0xff);

	/* Payload */
	$payload = sn_mqtt_encode_string($sn_mqtt_client_id[$mqtt_id]); // Client Identifier

	if($sn_mqtt_will_topic[$mqtt_id])
	{
		$payload .= sn_mqtt_encode_string($sn_mqtt_will_topic[$mqtt_id]); // Will Topic
		$payload .= sn_mqtt_encode_string($sn_mqtt_will_message[$mqtt_id]); // Will Message
	}

	if($sn_mqtt_username[$mqtt_id])
	{
		$payload .= sn_mqtt_encode_string($sn_mqtt_username[$mqtt_id]); // User Name

		if($sn_mqtt_password[$mqtt_id])
			$payload .= sn_mqtt_encode_string($sn_mqtt_password[$mqtt_id]); // Password
	}

	$remain_length = strlen($variable_header) + strlen($payload);

	/* Fixed Header: The DUP, QoS, and RETAIN flags are not used in the CONNECT message */
	$fixed_header = sprintf("%c", MQTT_CTRL_CONNECT << 4);
	$fixed_header .= sn_mqtt_encode_length($remain_length);

	$pkt = $fixed_header.$variable_header.$payload;

	return $pkt;
}

/*
Create a publish message
Parameters:
	- $topic: name of a topic. This must not contain Topic wildcard characters.
	- $msg: a message to be publish.
	- $msg_id: message identifier in case of qos > 0.
	- $dup_flag: dup flag. This value should be set to 0.
	- $qos: quality of service of message. valid from 0 to 2.
	        If it is set over 2, it will be downgraded to 2.
			It is is set lower than 0, it will be upgraded to 0.
			Default = 0.
	- $retain_flag: $retain flag. Default = 0.
Return: The created packet.
*/
function sn_mqtt_create_pulish_packet(&$topic, &$msg, $qos, $msg_id, $flags)
{
	// Variable header
	$variable_header = sn_mqtt_encode_string($topic); // Topic name

	if($qos)
	{
		$variable_header .= sprintf("%c", $msg_id >> 8);
		$variable_header .= sprintf("%c", $msg_id & 0xff);
	}

	$remain_length = strlen($variable_header) + strlen($msg);

	// Fixed Header
	$byte1 = (MQTT_CTRL_PUBLISH << 4);

	if($qos == MQTT_QOS1)
		$byte1 |= MQTT_HEAD_FLAG_QOS_1;
	else
	if($qos == MQTT_QOS2)
		$byte1 |= MQTT_HEAD_FLAG_QOS_2;

	if($flags & MQTT_PUB_RETAIN)
		$byte1 |= MQTT_HEAD_FLAG_RETAIN;

	$fixed_header = sprintf("%c", $byte1);
	$fixed_header .= sn_mqtt_encode_length($remain_length);

	$pkt = $fixed_header.$variable_header.$msg;

	return $pkt;
}

/*
The common function to create packets for:
PUBACK, PUBREC, PUBREL, PUBCOMP.
*/
function sn_mqtt_create_common_pub($msg_type, $msg_id, $flags = 0)
{
	// Fixed Header
	$byte1 = ($msg_type << 4) | $flags;
	$pkt = sprintf("%c", $byte1);
	$pkt .= sprintf("%c", 2);

	// Variable header
	$pkt .= sprintf("%c", $msg_id >> 8);
	$pkt .= sprintf("%c", $msg_id & 0xff);

	return $pkt;
}

/*
Create publish acknowledgment packet.
Parameters: $msg_id: message identifier.
Return: The created packet.
*/
function sn_mqtt_create_puback_packet($msg_id)
{
	// $dup_flag = 0; $qos = 0; $retain_flag = 0;
	return sn_mqtt_create_common_pub(MQTT_CTRL_PUBACK, $msg_id);
}

/*
Create publish received packet.
Parameters: $msg_id: message identifier.
Return: The created packet.
*/
function sn_mqtt_create_pubrec_packet($msg_id)
{
	// $dup_flag = 0; $qos = 0; $retain_flag = 0;
	return sn_mqtt_create_common_pub(MQTT_CTRL_PUBREC, $msg_id);
}

/*
Create publish release packet.
Parameters: $msg_id: message identifier.
Return: The created packet.
*/
function sn_mqtt_create_pubrel_packet($msg_id)
{
	// $dup_flag = 0; $qos = 1; $retain_flag = 0;
	$flags = 0x02;
	return sn_mqtt_create_common_pub(MQTT_CTRL_PUBREL, $msg_id, $flags);
}

/*
Create publish complete packet.
Parameters: $msg_id: message identifier.
Return: The created packet.
*/
function sn_mqtt_create_pubcomp_packet($msg_id)
{
	// $dup_flag = 0; $qos = 0; $retain_flag = 0;
	return sn_mqtt_create_common_pub(MQTT_CTRL_PUBCOMP, $msg_id);
}

/*
Create a Subscribe packet.
Parameters:
	- $topic: a string topic name.
	- $qos: quality of service.
	- $msg_id: message identifier
Return: The created packet.
Note:  The topic strings may contain special Topic wildcard characters to represent a set of topics as necessary.
	   see http://public.dhe.ibm.com/software/dw/webservices/ws-mqtt/mqtt-v3r1.html#appendix-a
*/
function sn_mqtt_create_subscribe_packet($topic, $qos, $msg_id)
{
	// Variable header
	$variable_header = sprintf("%c", $msg_id >> 8);
	$variable_header .= sprintf("%c", $msg_id & 0xff);

	// Payload
	$payload = "";

	$payload .= sn_mqtt_encode_string($topic); // Topic name.
	$payload .= sprintf("%c", $qos);

	$remain_length = strlen($variable_header) + strlen($payload);

	// Fixed Header
	$flags = 0x02; // $dup_flag = 0; $qos = 1; $retain_flag = 0;
	$byte1 = (MQTT_CTRL_SUBSCRIBE << 4) | $flags;
	$fixed_header = sprintf("%c", $byte1);
	$fixed_header .= sn_mqtt_encode_length($remain_length);

	$pkt = $fixed_header.$variable_header.$payload;

	return $pkt;
}

/*
Create unsubscribe packet.
Parameters:
	- $topic: a string topic name.
	  Examples: "topic1_name"
Return: The created packet.
*/
function sn_mqtt_create_unsubscribe_packet($topic, $msg_id)
{
	// Variable header
	$variable_header = sprintf("%c", $msg_id >> 8);
	$variable_header .= sprintf("%c", $msg_id & 0xff);

	// Payload
	$payload = "";

	$payload .= sn_mqtt_encode_string($topic); // Topic name.

	$remain_length = strlen($variable_header) + strlen($payload);

	// Fixed Header
	$flags = 0x02; // $qos = 1; $dup_flag = $retain_flag = 0;
	$byte1 = (MQTT_CTRL_UNSUBSCRIBE << 4) | $flags;
	$fixed_header = sprintf("%c", $byte1);
	$fixed_header .= sn_mqtt_encode_length($remain_length);

	$pkt = $fixed_header.$variable_header.$payload;

	return $pkt;
}

/*
Create ping request packet.
Parameters: None.
Return: The created packet.
*/
function sn_mqtt_create_pingreq_packet()
{
	// The DUP, QoS, and RETAIN flags are not used.
	$fixed_header = sprintf("%c", MQTT_CTRL_PINGREQ << 4);
	$fixed_header .= sprintf("%c", 0);
	// There is no payload.
	// There is no variable header.

	return $fixed_header;
}

/*
Create disconnect packet.
Parameters: None.
Return: The created packet.
*/
function sn_mqtt_create_disconnect_packet()
{
	// The DUP, QoS, and RETAIN flags are not used.
	$fixed_header = sprintf("%c", MQTT_CTRL_DISCONNECT << 4);
	$fixed_header .= sprintf("%c", 0);
	// There is no payload.
	// There is no variable header.

	return $fixed_header;
}

/*
Find packet in receiving buffer by message type.
Parameters:
	- $msg_type: type of message to find.
Return:
	- index of  the first packet in buffer if existed.
	- MQTT_RETURN_NOT_FOUND: if not existed.
*/
function sn_mqtt_find_packet($mqtt_id, $msg_type)
{
	global $sn_mqtt_packet_manager;

	if($sn_mqtt_packet_manager != "")
	{
		$infos = explode(",", $sn_mqtt_packet_manager);
		$count = count($infos);
		for($i = 0; $i < $count; $i += 2)
		{
			if($msg_type == (int)$infos[$i])
				return ($i/2);
		}
	}

	return MQTT_RETURN_NOT_FOUND;
}

/*
Get a packet in receiving buffer by index.
Parameters:
	- $pkt_id: index of packet in buffer.
	- $is_delete: option to delete packet from buffer after getting
Return:
	- a packet if existed.
	- an empty string: if not existed.
*/
function sn_mqtt_get_packet($mqtt_id, $pkt_id, $is_delete = true)
{
	global $sn_mqtt_recv_buffer, $sn_mqtt_packet_manager;

	$pkt = "";

	if($sn_mqtt_packet_manager != "")
	{
		$infos = explode(",", $sn_mqtt_packet_manager);
		$count = count($infos);
		$pkt_count = $count/2;

		if ($pkt_id < $pkt_count)
		{
			$pkt_offset = 0;

			for($i = 1; $i < ($pkt_id*2); $i += 2)
			{
				$pkt_offset += (int)$infos[$i];
			}

			$pkt_len = (int)$infos[$i];

			$pkt = substr($sn_mqtt_recv_buffer, $pkt_offset, $pkt_len);

			if($is_delete)
			{
				// delete from buffer.
				$sn_mqtt_recv_buffer = substr_replace($sn_mqtt_recv_buffer, "", $pkt_offset, $pkt_len);

				// update buffer manager.
				$sn_mqtt_packet_manager = "";

				for($i = 0; $i < $pkt_count; $i++)
				{
					if($i != $pkt_id)
					{
						$pnt = 2*$i;
						$pkt_type = $infos[$pnt];
						$pkt_lengh = $infos[$pnt+1];

						$sn_mqtt_packet_manager .= "$pkt_type,$pkt_lengh,";
					}
				}

				$sn_mqtt_packet_manager = rtrim($sn_mqtt_packet_manager, ",");
			}
		}
		// else
			// echo "mqtt$mqtt_id: invalid packet id\r\n";
	}
	// else
		// echo "mqtt$mqtt_id: no packet in buffer now\r\n";

	return $pkt;
}

/*
For debugging buffer.
*/
function sn_mqtt_show_packet_list($mqtt_id)
{
	global $sn_mqtt_packet_manager;

	$pkt = "";
	$msg_id = 0;

	if($sn_mqtt_packet_manager != "")
	{
		$infos = explode(",", $sn_mqtt_packet_manager);
		$count = count($infos);
		$pkt_count = $count/2;

		for($i = 0; $i < $count; $i += 2)
		{
			$pkt_id = (int)$infos[$i];
			$pkt = sn_mqtt_get_packet($mqtt_id, $i/2, false);

			if($pkt !== "")
				$msg_id = sn_mqtt_get_message_id($pkt);

			echo "mqtt$mqtt_id: packet $pkt_id in buffer with message id: $msg_id\r\n";
		}
	}
	else
		echo "mqtt$mqtt_id: no packet in buffer now\r\n";

	return $pkt;
}

/*
Delete a packet in receiving buffer by index.
Parameters:
	- $pkt_id: index of packet in buffer.
Return:
	- true on success.
	- false otherwise.
*/
function sn_mqtt_delete_packet($mqtt_id, $pkt_id)
{
	global $sn_mqtt_recv_buffer, $sn_mqtt_packet_manager;

	if($sn_mqtt_packet_manager != "")
	{
		$infos = explode(",", $sn_mqtt_packet_manager);
		$count = count($infos);
		$pkt_count = $count/2;

		if ($pkt_id < $pkt_count)
		{
			$pkt_offset = 0;

			for($i = 1; $i < ($pkt_id*2); $i += 2)
			{
				$pkt_offset += (int)$infos[$i];
			}

			$pkt_len = (int)$infos[$i];

			// delete from buffer.
			$sn_mqtt_recv_buffer = substr_replace($sn_mqtt_recv_buffer, "", $pkt_offset, $pkt_len);

			// update buffer manager.
			$sn_mqtt_packet_manager = "";

			for($i = 0; $i < $pkt_count; $i++)
			{
				if($i != $pkt_id)
				{
					$pnt = 2*$i;
					$pkt_type = $infos[$pnt];
					$pkt_lengh = $infos[$pnt+1];

					$sn_mqtt_packet_manager .= "$pkt_type,$pkt_lengh,";
				}
			}

			$sn_mqtt_packet_manager = rtrim($sn_mqtt_packet_manager, ",");

			return MQTT_RETURN_SUCCESS;
		}
		// else
			// echo "mqtt$mqtt_id: invalid packet id\r\n";
	}
	// else
		// echo "mqtt$mqtt_id: no packet in buffer now\r\n";

	return MQTT_RETURN_NOT_FOUND;
}

/*
Check whether incomming packets are available.
Parameters: None
Return:
	- A number of packets available.
	- MQTT_RETURN_CONN_LOST
*/
function sn_mqtt_packet_available($mqtt_id)
{
	global $sn_mqtt_tcp_pid;
	global $sn_mqtt_recv_buffer, $sn_mqtt_packet_manager;

	if(!$sn_mqtt_tcp_pid[$mqtt_id])
		exit("mqtt$mqtt_id: tcp not initialized\r\n");

	$rbuf = "";
	$pkt_count = 0;
	$infos = array();
	$count = 0;

	if($sn_mqtt_packet_manager != "")
	{
		$infos = explode(",", $sn_mqtt_packet_manager);
		$count = count($infos);
		$pkt_count = $count/2;
	}

	$tcp_state = pid_ioctl($sn_mqtt_tcp_pid[$mqtt_id], "get state");

	if(($tcp_state == TCP_CLOSED) || $tcp_state == SSL_CLOSED)
		return MQTT_RETURN_CONN_LOST;

	if(pid_ioctl($sn_mqtt_tcp_pid[$mqtt_id], "get rxlen"))
	{
		$max_len = MAX_STRING_LEN - strlen($sn_mqtt_recv_buffer);

		if($max_len > 10)
			$max_len = 10;

		pid_recv($sn_mqtt_tcp_pid[$mqtt_id], $rbuf, $max_len);

		// update buffer
		$sn_mqtt_recv_buffer .= $rbuf;

		$buf_len = strlen($sn_mqtt_recv_buffer);

		$pkt_offset = 0;

		for($i = 1; $i < $count; $i += 2)
		{
			$pkt_offset += (int)$infos[$i];
		}

		if($pkt_offset > $buf_len)
			exit("mqtt$mqtt_id: error on memory management");

		// update new packet.
		while(1)
		{
			if($buf_len >= ($pkt_offset + 2)) // miminum packet length is 2;
			{
				$pnt = $pkt_offset; // pointer

				$pkt_type = bin2int($sn_mqtt_recv_buffer[$pnt++], 0, 1) >> 4;

				$multiplier = 1;
				$value = 0; // the remaining length

				do
				{
					$digit = bin2int($sn_mqtt_recv_buffer[$pnt++], 0, 1);
					$value += ($digit & 127) * $multiplier;
					$multiplier *= 128;

				} while(($digit & 128) && ($pnt < $buf_len));

				if(!($digit & 128) && ( ($pnt + $value) <= $buf_len))
				{
					// update $sn_mqtt_packet_manager
					$pkt_lengh = $pnt + $value - $pkt_offset;

					if($sn_mqtt_packet_manager == "")
						$sn_mqtt_packet_manager = "$pkt_type,$pkt_lengh";
					else
						$sn_mqtt_packet_manager .= ",$pkt_type,$pkt_lengh";

					$pkt_offset = $pnt + $value;
					$pkt_count++;
					continue;
				}
			}

			break;
		}
	}

	return $pkt_count;
}

/*
Check whether a PUBLISH packet is acknowledged or not.
Parameters:
	- $msg_id: message identifier.
Return:
	- true if packet is unacknowledged.
	- false otherwise.
*/
function sn_mqtt_unack_list_exist($mqtt_id, $msg_id)
{
	global $sn_mqtt_unack_list;

	if($sn_mqtt_unack_list[$mqtt_id] != "")
	{
		$infos = explode(",", $sn_mqtt_unack_list[$mqtt_id]);
		$count = count($infos);

		for($i = 0; $i < $count; $i++)
		{
			if($msg_id == (int)$infos[$i])
				return true;
		}
	}

	return false;
}

function sn_mqtt_unack_list_add($mqtt_id, $rcv_msg_id)
{
	global $sn_mqtt_unack_list;

	if($sn_mqtt_unack_list[$mqtt_id] == "")
		$sn_mqtt_unack_list[$mqtt_id] = "$rcv_msg_id";
	else
		$sn_mqtt_unack_list[$mqtt_id] .= ",$rcv_msg_id";
}

/*
Remove the message identifier of a PUBLISH packet from unacknowledged list if existed.
Parameters:
	- $msg_id: message identifier.
Return: none.
*/
function sn_mqtt_unack_list_remove($mqtt_id, $msg_id)
{
	global $sn_mqtt_unack_list;

	if($sn_mqtt_unack_list[$mqtt_id] != "")
	{
		$infos = explode(",", $sn_mqtt_unack_list[$mqtt_id]);
		$count = count($infos);

		$sn_mqtt_unack_list[$mqtt_id] = "";

		for($i = 0; $i < $count; $i++)
		{
			$id = (int)$infos[$i];

			if($msg_id != $id)
				$sn_mqtt_unack_list[$mqtt_id] .= "$id,";
		}

		$sn_mqtt_unack_list[$mqtt_id] = rtrim($sn_mqtt_unack_list[$mqtt_id], ","); // remove the last comma
	}
}

/*
Waiting for a message response.
Parameters:
	- $msg_type: type of message is waiting.
	- $msg_id: Message Identifier. In case of the waiting message doesn't have message identifier, set this field to 0.
	- $timeout: timeout.
Return:
	- index of first packet in buffer if existed.
	- MQTT_RETURN_TIMEOUT: if not not received the expected message after timeout.
	- MQTT_RETURN_CONN_LOST: if TCP connection was closed
*/
function sn_mqtt_wait_response($mqtt_id, $msg_type, $msg_id, $timeout)
{
	$time_expire = sn_mqtt_get_tick() + $timeout;

	while($time_expire > sn_mqtt_get_tick())
	{
		$return_val = sn_mqtt_packet_available($mqtt_id);

		if($return_val == MQTT_RETURN_CONN_LOST)
			return MQTT_RETURN_CONN_LOST; // TCP connection was closed

		if($return_val > 0)
		{
			$pkt_id = sn_mqtt_find_packet($mqtt_id, $msg_type);

			if($pkt_id >= 0)
			{
				if($msg_id == 0) // no need to check message identifier
					return $pkt_id;

				$pkt = sn_mqtt_get_packet($mqtt_id, $pkt_id, false);
				$rcv_msg_id = sn_mqtt_get_message_id($pkt);

				if($rcv_msg_id == $msg_id)
					return $pkt_id;
			}
		}
	}

	return MQTT_RETURN_TIMEOUT; // timeout
}

/*
Send a message to broker.
Parameters:
	- $msg: a message to be sent.
Return:
	- MQTT_RETURN_SUCCESS or MQTT_RETURN_CONN_LOST
*/
function sn_mqtt_send($mqtt_id, $pkt)
{
	global $sn_mqtt_tcp_pid;
	global $sn_mqtt_alive_start;

	if(!$sn_mqtt_tcp_pid[$mqtt_id])
		exit("mqtt$mqtt_id: tcp not initialized\r\n");

	$tcp_state = pid_ioctl($sn_mqtt_tcp_pid[$mqtt_id], "get state");

	if(($tcp_state == TCP_CLOSED) || $tcp_state == SSL_CLOSED)
		return MQTT_RETURN_CONN_LOST;

	pid_send($sn_mqtt_tcp_pid[$mqtt_id], $pkt, strlen($pkt));
	$sn_mqtt_alive_start[$mqtt_id] = time();
	return MQTT_RETURN_SUCCESS;
}

/*
Send a message and the waiting for a message response within timeout.
After time out, if not received the reponse, the function may resend message, depending on setting.
Parameters:
	- $send_pkt: message to send.
	- $wait_msg_type: type of message is waiting.
	- $msg_id: Message Identifier.
	- $timeout: timeout.
Return:
	- on success: index of the received response message in receving buffer.
	- on failure:
		+ MQTT_RETURN_CONN_LOST, if TCP connection is lost.
		+ MQTT_RETURN_TIMEOUT, if not received the expected message after retry and timeout.
*/
function sn_mqtt_send_wait($mqtt_id, $send_pkt, $wait_msg_type, $msg_id, $timeout)
{
	$sent_count = 0;

	while($sent_count <= MQTT_RESEND_MAX_NUM)
	{
		// send packet.
		if(sn_mqtt_send($mqtt_id, $send_pkt) == MQTT_RETURN_CONN_LOST)
			return MQTT_RETURN_CONN_LOST;

		$sent_count++;

		// waiting for message
		$pkt_id = sn_mqtt_wait_response($mqtt_id, $wait_msg_type, $msg_id, $timeout);

		if($pkt_id >= 0)
		{ // received the expected message
			return $pkt_id;
		}
		else
		{ // not received expected message
			$send_pkt_type = bin2int($send_pkt[0], 0, 1) >> 4;

			if($send_pkt_type == MQTT_CTRL_PUBLISH)
			{
				// retry with DUP flag is set.
				$byte1 = bin2int($send_pkt[0], 0, 1);
				$byte1 |= MQTT_HEAD_FLAG_DUP;
				$send_pkt[0] = sprintf("%c", $byte1);
			}
		}
	}

	return MQTT_RETURN_TIMEOUT;
}

/*
Process a incoming PUBLISH RELEASE packet.
*/
function sn_mqtt_process_pubrel_packet($mqtt_id, $pkt)
{
	$rcv_msg_id = sn_mqtt_get_message_id($pkt);

	// send PUBCOMP
	$send_pkt = sn_mqtt_create_pubcomp_packet($rcv_msg_id);

	if(sn_mqtt_send($mqtt_id, $send_pkt) == MQTT_RETURN_CONN_LOST)
		return MQTT_RETURN_CONN_LOST; // socket is closed

	// remove message id from unacknowledged list;
	sn_mqtt_unack_list_remove($mqtt_id, $rcv_msg_id);

	return MQTT_RETURN_SUCCESS;
}

/*
Process a incoming PUBLISH RECEIVE packet
Return:
	- on success: MQTT_RETURN_SUCCESS.
	- on failure:
		+ MQTT_RETURN_CONN_LOST, if TCP connection is lost.
		+ MQTT_RETURN_TIMEOUT, if not received the expected message after retry and timeout.
*/
function sn_mqtt_process_pubrec_packet($mqtt_id, $pkt)
{
	$rcv_msg_id = sn_mqtt_get_message_id($pkt);

	// send PUBREL
	$send_pkt = sn_mqtt_create_pubrel_packet($rcv_msg_id);
	$return_val = sn_mqtt_send_wait($mqtt_id, $send_pkt, MQTT_CTRL_PUBCOMP, $rcv_msg_id, MQTT_TIMEOUT_PUBLISH_MS);

	if($return_val >= 0)
	{
		sn_mqtt_delete_packet($mqtt_id, $return_val); // delete response message from received buffer.
		return MQTT_RETURN_SUCCESS;
	}

	return $return_val;
}

/*
Response to a received PUBLISH message with QoS level 1 and 2.
Return:
	- false if it dectect the packet is duplicated
	- true if otherwise
Note: in case of QoS = 2, after retry and timeout, if PUBLISH RELEASE is not received,
this PUBLIC packet will still be delivered to application but message id is stored to unacknowledged list.
*/
function sn_mqtt_process_publish_packet($mqtt_id, $pkt)
{
	$qos = sn_mqtt_get_message_qos($pkt);
	$rcv_msg_id = sn_mqtt_get_message_id($pkt);

	if($qos == 2)
	{
		$return_val = MQTT_RETURN_MSG_NEW;

		$dup_flag = sn_mqtt_get_message_dup($pkt);

		if($dup_flag && sn_mqtt_unack_list_exist($mqtt_id, $rcv_msg_id)) // This is an duplicated packet
			$return_val = MQTT_RETURN_MSG_DUPLICATE;
		else
			sn_mqtt_unack_list_add($mqtt_id, $rcv_msg_id); // Put packet ID into unacknowledged list

		$send_pkt = sn_mqtt_create_pubrec_packet($rcv_msg_id);
		$pkt_id = sn_mqtt_send_wait($mqtt_id, $send_pkt, MQTT_CTRL_PUBREL, $rcv_msg_id, MQTT_TIMEOUT_PUBLISH_MS);

		if($pkt_id >= 0)
		{
			// get response message from received buffer.
			$pubrel_pkt = sn_mqtt_get_packet($mqtt_id, $pkt_id);
			sn_mqtt_process_pubrel_packet($mqtt_id, $pubrel_pkt);
		}

		return $return_val;
	}
	else
	if($qos == 1)
	{
		// In case of QoS = 1, when DUP flag is 1, we cann't sure that packet is duplicate
		$send_pkt = sn_mqtt_create_puback_packet($rcv_msg_id);

		sn_mqtt_send($mqtt_id, $send_pkt);

		return MQTT_RETURN_MSG_NEW;
	}
	else
	if($qos == 0)
		return MQTT_RETURN_MSG_NEW;
}

function sn_mqtt_clean($mqtt_id)
{
	global $sn_mqtt_tcp_pid, $sn_mqtt_state;
	global $sn_mqtt_recv_buffer, $sn_mqtt_packet_manager;

	if($sn_mqtt_tcp_pid[$mqtt_id])
	{
		pid_close($sn_mqtt_tcp_pid[$mqtt_id]);
		$sn_mqtt_tcp_pid[$mqtt_id] = 0;
	}

	$sn_mqtt_recv_buffer = "";
	$sn_mqtt_packet_manager = "";

	$sn_mqtt_state[$mqtt_id] = MQTT_DISCONNECTED;
}

function mqtt_tcp_ioctl($mqtt_id, $cmd)
{
	global $sn_mqtt_tcp_id, $sn_mqtt_tcp_pid;

	if($sn_mqtt_tcp_pid[$mqtt_id])
		$close_pid = false;
	else
		$close_pid = true;

	if(!$sn_mqtt_tcp_pid[$mqtt_id])
	{
		$tcp_id = $sn_mqtt_tcp_id[$mqtt_id];
		$sn_mqtt_tcp_pid[$mqtt_id] = pid_open("/mmap/tcp$tcp_id");
	}

	$args = explode(" ", $cmd);

	if(($args[1] == "ssl") || ($args[1] == "tls"))
		pid_ioctl($sn_mqtt_tcp_pid[$mqtt_id], "set api ssl");

	$return_val = pid_ioctl($sn_mqtt_tcp_pid[$mqtt_id], $cmd);

	if($close_pid)
	{
		pid_close($sn_mqtt_tcp_pid[$mqtt_id]);
		$sn_mqtt_tcp_pid[$mqtt_id] = 0;
	}

	return $return_val;
}

/*
Return state of MQTT connection
*/
function mqtt_state($mqtt_id)
{
	global $sn_mqtt_tcp_pid, $sn_mqtt_state;

	if($sn_mqtt_tcp_pid[$mqtt_id])
	{
		$tcp_state = pid_ioctl($sn_mqtt_tcp_pid[$mqtt_id], "get state");

		if(($tcp_state == TCP_CLOSED) || $tcp_state == SSL_CLOSED)
			sn_mqtt_clean($mqtt_id);
	}
	else
		sn_mqtt_clean($mqtt_id);

	return $sn_mqtt_state[$mqtt_id];
}

/*
Set basic information
Parameters:
	- $tcp_id: tcp id. depending on devices and your choice.
	- $client_id: Client identifier.
	- $hostname:
		+ Domainame
		+ Or Broker's IP inside square brackets. Ex "[192.168.0.5]"
	- $port: Broker's port. Default: 1883
	- $version: Protocol version. Default: version 3.1
	- $resend: if this option is set to true, mqtt client will resend the message if not received the expected message within timeout.
*/
function mqtt_setup($mqtt_id, $tcp_id, $hostname, $port = 1883, $ip6 = false)
{
	global $sn_mqtt_tcp_id;
	global $sn_mqtt_broker_hostname, $sn_mqtt_broker_port;
	global $sn_mqtt_ipv6;

	$sn_mqtt_tcp_id[$mqtt_id] = $tcp_id;
	$sn_mqtt_broker_hostname[$mqtt_id] = $hostname;
	$sn_mqtt_broker_port[$mqtt_id] = $port;
	$sn_mqtt_ipv6 = $ip6;
}

function mqtt_auth($mqtt_id, $auth_id, $auth_pwd)
{
	global $sn_mqtt_msg_flag;
	global $sn_mqtt_username, $sn_mqtt_password;

	if(is_string($auth_id) && $auth_id) 
	{
		$sn_mqtt_username[$mqtt_id] = $auth_id;

		if(is_string($auth_pwd) && $auth_pwd)
			$sn_mqtt_password[$mqtt_id] = $auth_pwd;
	}
}

function mqtt_client($mqtt_id, $client_id, $version = MQTT_VER_3_1, $flags = 0)
{
	global $sn_mqtt_default_flag;
	global $sn_mqtt_msg_flag;
	global $sn_mqtt_client_id;
	global $sn_mqtt_version;

	$flags &= (MQTT_CONN_SSL | MQTT_CONN_CLEAN | MQTT_WILL_RETAIN | MQTT_PUB_RETAIN);  /* supported flag */

	if((($version == MQTT_VER_3_1) && (strlen($client_id) > 23)) || (($version == MQTT_VER_3_1_1) && (strlen($client_id) > 65535)) )
		exit("Client Identifier exceeds the limited length\r\n");

	$sn_mqtt_client_id[$mqtt_id] = $client_id;
	$sn_mqtt_version[$mqtt_id] = $version;

	if($sn_mqtt_version[$mqtt_id] != MQTT_VER_3_1 && $sn_mqtt_version[$mqtt_id] != MQTT_VER_3_1_1)
		exit("mqtt$mqtt_id: not support protocol version\r\n");

	//$sn_mqtt_msg_flag[$mqtt_id] |= $flags;
	$sn_mqtt_default_flag = $flags;
}

function mqtt_will($mqtt_id, $topic, $msg, $qos, $flags = 0)
{
	global $sn_mqtt_default_flag;
	global $sn_mqtt_msg_flag;
	global $sn_mqtt_will_topic, $sn_mqtt_will_message, $sn_mqtt_will_qos;

	$mask = MQTT_WILL_RETAIN;  /* supported flag */

	if($flags)
		$flags &= $mask;
	else
		$flags = $sn_mqtt_default_flag & $mask; /* default flag */

	if((is_string($topic) && $topic) && (is_string($msg) && $msg))
	{
		$sn_mqtt_msg_flag[$mqtt_id] &= ~$mask;
		$sn_mqtt_msg_flag[$mqtt_id] |= $flags;
		$sn_mqtt_will_topic[$mqtt_id] = $topic;
		$sn_mqtt_will_message[$mqtt_id] = $msg;
		$sn_mqtt_will_qos[$mqtt_id] = $qos;
	}
}

/*
Send a ping request packet and wait for respond within a timeout
Parameters: none
*/
function mqtt_ping($mqtt_id)
{
	global $sn_mqtt_state;

	$sn_mqtt_state[$mqtt_id] = MQTT_PINGING;
	$send_pkt = sn_mqtt_create_pingreq_packet();
	echo "mqtt$mqtt_id: send a PINGREQ message\r\n";
	$pkt_id = sn_mqtt_send_wait($mqtt_id, $send_pkt, MQTT_CTRL_PINGRESP, 0, MQTT_TIMEOUT_PING_MS);

	if($pkt_id >= 0)
	{
		// delete response message from received buffer.
		sn_mqtt_delete_packet($mqtt_id, $pkt_id);
		$sn_mqtt_state[$mqtt_id] = MQTT_CONNECTED;
	}
}

/*
Client requests a connection to a Server.
Parameters:
	- $mqtt_id:
	- $flags: MQTT_CONN_SSL, MQTT_CONN_CLEAN. If this parameter is ommited, connection flag will be set to values set by mqtt_client() function.
*/
function mqtt_connect($mqtt_id, $flags = 0)
{
	global $sn_mqtt_default_flag;
	global $sn_mqtt_tcp_id, $sn_mqtt_tcp_pid;
	global $sn_mqtt_state;
	global $sn_mqtt_broker_hostname, $sn_mqtt_broker_port;
	global $sn_mqtt_alive_start;
	global $sn_mqtt_ipv6;
	global $sn_mqtt_msg_flag;

	$mask = MQTT_CONN_SSL | MQTT_CONN_CLEAN; /* supported flag */

	if($flags)
		$flags &= $mask;
	else
		$flags = $sn_mqtt_default_flag & $mask; /* default flag */

	$sn_mqtt_msg_flag[$mqtt_id] &= ~$mask;
	$sn_mqtt_msg_flag[$mqtt_id] |= $flags;

	// connect to broker.
	$host_name = $sn_mqtt_broker_hostname[$mqtt_id];
	$hn_len = strlen($host_name);

	if($host_name[0] == "[" && $host_name[$hn_len - 1] == "]")
		$host_addr = substr($host_name, 1, $hn_len - 2);
	else
	{
		if($sn_mqtt_ipv6)
			$host_addr = dns_lookup($host_name, RR_AAAA);
		else
			$host_addr = dns_lookup($host_name, RR_A);

		if($host_addr == $host_name)
		{
			echo "$host_name : Not Found\r\n";
			return false;
		}
	}

	if($sn_mqtt_tcp_pid[$mqtt_id])
		pid_close($sn_mqtt_tcp_pid[$mqtt_id]);

	$tcp_id = $sn_mqtt_tcp_id[$mqtt_id];

	while(($sn_mqtt_tcp_pid[$mqtt_id] = pid_open("/mmap/tcp$tcp_id", O_NODIE)) < 0)
	{
		if($sn_mqtt_tcp_pid[$mqtt_id] == -EBUSY)
			usleep(500);
		else
		if($sn_mqtt_tcp_pid[$mqtt_id] == -ENOENT)
			exit("file not found\r\n");
		else
			exit("pid_open error\r\n");
	}

	if($sn_mqtt_msg_flag[$mqtt_id] & MQTT_CONN_SSL)
	{
		pid_ioctl($sn_mqtt_tcp_pid[$mqtt_id], "set api ssl"); // set api to SSL
		pid_ioctl($sn_mqtt_tcp_pid[$mqtt_id], "set ssl method client"); // set SSL client mode

		if(PHP_VERSION_ID >= 20200)
		{
			pid_ioctl($sn_mqtt_tcp_pid[$mqtt_id], "set ssl extension sni $host_name");
			pid_ioctl($sn_mqtt_tcp_pid[$mqtt_id], "set ssl vsni 1");
		}
	}

	pid_bind($sn_mqtt_tcp_pid[$mqtt_id], "", 0);

	pid_connect($sn_mqtt_tcp_pid[$mqtt_id], $host_addr, $sn_mqtt_broker_port[$mqtt_id]); // trying to TCP connect to the specified host/port
	echo "mqtt$mqtt_id: Connecting to $host_addr...\r\n";

	for(;;)
	{
		$state = pid_ioctl($sn_mqtt_tcp_pid[$mqtt_id], "get state");

		if(!($sn_mqtt_msg_flag[$mqtt_id] & MQTT_CONN_SSL))
		{
			if($state == TCP_CLOSED)
			{
				echo "mqtt$mqtt_id: TCP connection failed\r\n";
				return false;
			}
			else
			if($state == TCP_CONNECTED)
			{
				echo "mqtt$mqtt_id: TCP connected\r\n";
				break;
			}
		}
		else
		if($sn_mqtt_msg_flag[$mqtt_id] & MQTT_CONN_SSL)
		{
			if($state == SSL_CLOSED)
			{
				echo "mqtt$mqtt_id: SSL connection failed\r\n";
				return false;
			}
			else
			if($state == SSL_CONNECTED)
			{
				echo "mqtt$mqtt_id: SSL connected\r\n";
				break;
			}
		}
	}

	// create packet
	$send_pkt = sn_mqtt_create_connect_packet($mqtt_id);
	echo "mqtt$mqtt_id: sending a CONNECT message\r\n";
	$pkt_id = sn_mqtt_send_wait($mqtt_id, $send_pkt, MQTT_CTRL_CONNECTACK, 0, MQTT_TIMEOUT_CONNECT_MS);

	if($pkt_id >= 0)
	{
		$resp_pkt = sn_mqtt_get_packet($mqtt_id, $pkt_id);
		// TO DO. user can save the granted QoS here.
		$remain_length = sn_mqtt_decode_length($resp_pkt);
		$var_head_pos = strlen($resp_pkt) - $remain_length;
		$retn_code = bin2int($resp_pkt[$var_head_pos+1], 0, 1);

		switch($retn_code)
		{
			case 0x00:
				echo "<<Connection Accepted\r\n";
				$sn_mqtt_state[$mqtt_id] = MQTT_CONNECTED;
				$sn_mqtt_alive_start[$mqtt_id] = time();
				return true;
			case 0x01:
				echo "<<Connection Refused: unacceptable protocol version\r\n";
				break;
			case 0x02:
				echo "<<Connection Refused: identifier rejected\r\n";
				break;
			case 0x03:
				echo "<<Connection Refused: server unavailable\r\n";
				break;
			case 0x04:
				echo "<<Connection Refused: bad user name or password\r\n";
				break;
			case 0x05:
				echo "<<Connection Refused: not authorized\r\n";
				break;
			default:
				echo "<<Reserved for future use\r\n";
		}
	}

	// can not connect
	sn_mqtt_clean($mqtt_id);

	return false;
}

/*
Send Disconnect message to broker and close TCP connection.
Parameters: none
*/
function mqtt_disconnect($mqtt_id)
{
	global $sn_mqtt_tcp_pid;
	global $sn_mqtt_state;

	$send_pkt = sn_mqtt_create_disconnect_packet();
	echo "mqtt$mqtt_id: send a DISCONNECT message\r\n";

	if(sn_mqtt_send($mqtt_id, $send_pkt) == MQTT_RETURN_CONN_LOST)
		return MQTT_RETURN_CONN_LOST; // socket is closed

	// Close TCP connection
	echo "mqtt$mqtt_id: close TCP connection\r\n";
	sn_mqtt_clean($mqtt_id);

	return MQTT_RETURN_SUCCESS;
}

/*
Subscribe to a list of topics.
Parameters:
	- $topic: topic name
	- $qos: QoS.
Note:  The topic strings may contain special Topic wildcard characters to represent a set of topics as necessary.
	   see http://public.dhe.ibm.com/software/dw/webservices/ws-mqtt/mqtt-v3r1.html#appendix-a
*/
function mqtt_subscribe($mqtt_id, $topic, $qos)
{
	global $sn_mqtt_msg_id;
	global $sn_mqtt_subs_list;

	$sn_mqtt_msg_id[$mqtt_id]++;

	// create packet
	$send_pkt = sn_mqtt_create_subscribe_packet($topic, $qos, $sn_mqtt_msg_id[$mqtt_id]);

	echo "mqtt$mqtt_id: send a SUBSCRIBE message\r\n";
	$pkt_id = sn_mqtt_send_wait($mqtt_id, $send_pkt, MQTT_CTRL_SUBACK, $sn_mqtt_msg_id[$mqtt_id], MQTT_TIMEOUT_SUBSCRIBE_MS);

	if($pkt_id >= 0)
	{
		$resp_pkt = sn_mqtt_get_packet($mqtt_id, $pkt_id);
		$payload = sn_mqtt_get_message_payload($resp_pkt);

		$gra_qos = bin2int($payload[0], 0, 1);

		if($gra_qos == 128)
			echo "mqtt$mqtt_id: DENIED ";
		else
			echo "mqtt$mqtt_id: GRANTED ";

		echo "Topic: $topic. Requested QoS: $qos. Granted QoS: $gra_qos\r\n";

		// Add to subscription list

		$str = "$topic,$qos\r\n";
		$pos = strpos($sn_mqtt_subs_list[$mqtt_id], $str);
		if($pos === false)
			$sn_mqtt_subs_list[$mqtt_id] .= $str;

		return true;
	}

	return false;
}

/*
Unsubscribe from named topics
Parameters:
	- $topic: a string contain topic name.
*/
function mqtt_unsubscribe($mqtt_id, $topic)
{
	global $sn_mqtt_msg_id;
	global $sn_mqtt_subs_list;

	$sn_mqtt_msg_id[$mqtt_id]++;

	// create packet
	$send_pkt = sn_mqtt_create_unsubscribe_packet($topic, $sn_mqtt_msg_id[$mqtt_id]);

	echo "mqtt$mqtt_id: send an UNSUBSCRIBE message\r\n";
	$pkt_id = sn_mqtt_send_wait($mqtt_id, $send_pkt, MQTT_CTRL_UNSUBACK, $sn_mqtt_msg_id[$mqtt_id], MQTT_TIMEOUT_UNSUBSCRIBE_MS);

	if($pkt_id >= 0)
	{
		// delete response message from received buffer.
		sn_mqtt_delete_packet($mqtt_id, $pkt_id);

		// Remove from subscription list
		if($sn_mqtt_subs_list[$mqtt_id] != "")
		{
			$sn_mqtt_subs_list[$mqtt_id] = rtrim($sn_mqtt_subs_list[$mqtt_id], "\r\n"); // remove the last \r\n
			$tpc_list = explode("\r\n", $sn_mqtt_subs_list[$mqtt_id]);
			$tpc_count = count($tpc_list);
			$sn_mqtt_subs_list[$mqtt_id] = "";

			for($i = 0; $i < $tpc_count; $i++)
			{
				$is_find = false;
				$cur_topic = explode(",", $tpc_list[$i]);

				if($topic != $cur_topic[0])
				{
					$tpc_name = $cur_topic[0]; // Topic name
					$req_qos = $cur_topic[1];
					$sn_mqtt_subs_list[$mqtt_id] .= "$tpc_name,$req_qos\r\n";
				}
			}
		}

		return true;
	}

	return false;
}

/*
Send a Publish message
Parameters:
	- $topic: name of a topic. This must not contain Topic wildcard characters.
	- $msg: a message to be publish.
	- $qos: quality of service of message.
	- $flags: (optional) MQTT_PUB_RETAIN. If this parameter is ommited, retain flag will be set to value set by mqtt_client() function.
Return:
	- on success: MQTT_RETURN_SUCCESS.
	- on failure:
		+ MQTT_RETURN_CONN_LOST, if TCP connection is lost.
		+ MQTT_RETURN_TIMEOUT, if not received the expected message after retry and timeout.
Note: in case of QoS > 0, after retry and timeout, if acknowledgment is not received, this packet will be discarded.
*/
function mqtt_publish($mqtt_id, $topic, $msg, $qos, $flags = 0)
{
	global $sn_mqtt_msg_id;
	global $sn_mqtt_default_flag;
	global $sn_mqtt_msg_flag;

	if(($qos != 0) && ($qos != 1) &&($qos != 2))
		exit("mqtt: invalid QoS\r\n");

	$mask = MQTT_PUB_RETAIN;  /* supported flag */

	if($flags)
		$flags &= $mask;
	else
		$flags = $sn_mqtt_default_flag & $mask; /* default flag */

	if($qos == MQTT_QOS1 || $qos == MQTT_QOS2)
		$sn_mqtt_msg_id[$mqtt_id]++;

	// Create publish packet
	$send_pkt = sn_mqtt_create_pulish_packet($topic, $msg, $qos, $sn_mqtt_msg_id[$mqtt_id], $flags);
	echo ">>topic:$topic\r\n";
	echo ">>content: $msg\r\n";

	if($qos == MQTT_QOS2)
	{
		$return_val = sn_mqtt_send_wait($mqtt_id, $send_pkt, MQTT_CTRL_PUBREC, $sn_mqtt_msg_id[$mqtt_id], MQTT_TIMEOUT_PUBLISH_MS);

		if($return_val >= 0)
		{
			// get response message from received buffer.
			$pubrec_pkt = sn_mqtt_get_packet($mqtt_id, $return_val);
			$return_val = sn_mqtt_process_pubrec_packet($mqtt_id, $pubrec_pkt);
		}

		return $return_val;
	}
	else
	if($qos == MQTT_QOS1)
	{
		$return_val = sn_mqtt_send_wait($mqtt_id, $send_pkt, MQTT_CTRL_PUBACK, $sn_mqtt_msg_id[$mqtt_id], MQTT_TIMEOUT_PUBLISH_MS);

		if($return_val >= 0)
		{
			// delete response message from received buffer.
			sn_mqtt_delete_packet($mqtt_id, $return_val);
			return MQTT_RETURN_SUCCESS;
		}

		return $return_val;
	}
	else
	{
		return sn_mqtt_send($mqtt_id, $send_pkt);
	}
}

/*
Reconnect to broker.
This function should only use when when:
	- An I/O error is encountered by the client during communication with the server
	- The client fails to communicate within the Keep Alive timer schedule
Note: should not use this function after client send DISCONECT packet, use mqtt_connect() function instead
*/
function mqtt_reconnect($mqtt_id, $resubscribe = true)
{
	global $sn_mqtt_subs_list;
	global $sn_mqtt_recv_buffer, $sn_mqtt_packet_manager;
	global $sn_mqtt_msg_flag;

	$sn_mqtt_recv_buffer = "";
	$sn_mqtt_packet_manager = "";

	$flags = $sn_mqtt_msg_flag[$mqtt_id];

	$ret_val = mqtt_connect($mqtt_id, $flags);

	if($ret_val)
	{
		if($resubscribe)
		{
			if($sn_mqtt_subs_list[$mqtt_id] != "")
			{
				$sn_mqtt_subs_list[$mqtt_id] = rtrim($sn_mqtt_subs_list[$mqtt_id], "\r\n"); // remove the last \r\n
				$tpc_list = explode("\r\n", $sn_mqtt_subs_list[$mqtt_id]);
				$sn_mqtt_subs_list[$mqtt_id] .= "\r\n";
				$tpc_count = count($tpc_list);

				for($i = 0; $i < $tpc_count; $i++)
				{
					$topic_arr = explode(",", $tpc_list[$i]);
					$topic = $topic_arr[0];
					$qos = (int)$topic_arr[1];

					mqtt_subscribe($mqtt_id, $topic, $qos);
				}
			}
		}

		return true;
	}

	return false;
}

/*
This function
	- check incoming data from socket
	- process any incoming packet
	- send ping message and wait for response if keepalive timeout is passed.
	- disconnect from broker if no ping response during a certain time
Parameters:
	-
Return:
	- true if there is a incoming PUBLISH message.
	- false if otherwise.
Note: except for PUBLISH packet:
	- Since any kinds of the response packet is waiting within a timeout, the packets detected in this funciton are in the following exception cases:
		+ Packets arrives after timeout.
		+ In case of clean session is not set, when client suddenly disconnect or restarts but the acknowledgment process does not complete.
*/
function mqtt_loop($mqtt_id)
{
	global $sn_mqtt_alive_start;
	global $sn_mqtt_state;

	// Check incoming packet
	$pkt_num = sn_mqtt_packet_available($mqtt_id);
	if($pkt_num > 0)
	{
		$pkt = sn_mqtt_get_packet($mqtt_id, 0, false);
		$msg_type = bin2int($pkt[0], 0, 1) >> 4;

		switch($msg_type)
		{
			case MQTT_CTRL_PUBLISH:
				$return_val = sn_mqtt_process_publish_packet($mqtt_id, $pkt);

				if($return_val != MQTT_RETURN_MSG_DUPLICATE)
					return true;
				else
				{
					echo "mqtt$mqtt_id: received duplicate publish message\r\n";
					sn_mqtt_delete_packet($mqtt_id, 0);
				}

				break;
			/*
			In case of PUBREC and PUBREL, Although PUBLISH packet was delivered to onward receiver,
			It must do the remaining process to avoid the sender retry
			*/
			case MQTT_CTRL_PUBREC:
				 echo "mqtt$mqtt_id: received PUBREC message\r\n";
				sn_mqtt_delete_packet($mqtt_id, 0);
				sn_mqtt_process_pubrec_packet($mqtt_id, $pkt);
				break;
			case MQTT_CTRL_PUBREL:
				 echo "mqtt$mqtt_id: received PUBREL message\r\n";
				sn_mqtt_delete_packet($mqtt_id, 0);
				sn_mqtt_process_pubrel_packet($mqtt_id, $pkt);
				break;

			/*
			This come after timeout. Application already take other actions. So, discard it.
			This kind of message is the last in acknowledgment process, broker will not resend it.
			*/
			case MQTT_CTRL_SUBACK:
			case MQTT_CTRL_UNSUBACK:
			case MQTT_CTRL_PUBACK:
			case MQTT_CTRL_PUBCOMP:
			case MQTT_CTRL_CONNECTACK:
			case MQTT_CTRL_PINGRESP:
				sn_mqtt_delete_packet($mqtt_id, 0);
				break;
			// Client cann't receive this kind packet
			case MQTT_CTRL_CONNECT:
			case MQTT_CTRL_SUBSCRIBE:
			case MQTT_CTRL_UNSUBSCRIBE:
			case MQTT_CTRL_PINGREQ:
			case MQTT_CTRL_DISCONNECT:
				exit("mqtt$mqtt_id: server error\r\n");
		}
	}

	if($sn_mqtt_state[$mqtt_id] != MQTT_DISCONNECTED)
	{
		if($sn_mqtt_alive_start[$mqtt_id] < (time() - MQTT_CONN_KEEPALIVE ))
			mqtt_ping($mqtt_id);

		if($sn_mqtt_alive_start[$mqtt_id] < (time() - 2 * MQTT_CONN_KEEPALIVE ))
		{
			echo "mqtt$mqtt_id: Cann't ping to broker\r\n";
			mqtt_disconnect($mqtt_id);
		}
	}

	return false;
}

/*
Parameters:
	- $topic: a variable to contain the topic of incoming publish message
	- $content: a variable to contain the content of incoming publish message
	- $retain: a variable to contain the retain state of incoming publish message: 1 -retain message, 0 -live message
Return:
	- true if there is a incoming PUBLISH message.
	- false if otherwise.
*/
function mqtt_recv($mqtt_id, &$topic, &$content, &$retain)
{
	$pkt_id = sn_mqtt_find_packet($mqtt_id, MQTT_CTRL_PUBLISH);

	if($pkt_id >= 0)
	{
		$pkt = sn_mqtt_get_packet($mqtt_id, $pkt_id);

		$topic = sn_mqtt_get_topic($pkt);
		$content = sn_mqtt_get_content($pkt);
		$retain = sn_mqtt_get_message_retain($pkt);

		return true;
	}
	else
	{
		$topic = "";
		$content = "";
		$retain = "";

		return false;
	}
}
?>