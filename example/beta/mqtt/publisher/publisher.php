<?php

include_once "/lib/sn_dns.php";
include_once "/lib/sn_mqtt_b1.php";

$host_name = "xxxxxxxxxxxxxx.iot.ap-northeast-2.amazonaws.com";
//$host_name = "iot.eclipse.org";
//$host_name = "broker.hivemq.com";
//$host_name = "broker.mqttdashboard.com";
//$host_name = "[192.168.0.3]";

//$port = 1883;
$port = 8883;

mqtt_setup(0, 0, $host_name, $port);

//mqtt_auth(0, "your_username", "your_password");

mqtt_client(0, "my_client_id_pub", MQTT_VER_3_1_1);

//mqtt_will(0, "will_topic", "i'll be back", MQTT_QOS1);
//mqtt_will(0, "will_topic", "i'll be back", MQTT_QOS2, MQTT_WILL_RETAIN);

//mqtt_connect(0);
//mqtt_connect(0, MQTT_CONN_SSL);
//mqtt_connect(0, MQTT_CONN_CLEAN);
mqtt_connect(0, MQTT_CONN_SSL | MQTT_CONN_CLEAN);

$recv_topic = "";
$recv_content = "";
$recv_retain = 0;

while(1)
{
	if(mqtt_state(0) == MQTT_DISCONNECTED)
	{
		while(mqtt_reconnect(0) == false)
			sleep(2);
	}

	if(mqtt_state(0) == MQTT_CONNECTED)
	{
		mqtt_publish(0, '$aws/things/myled/shadow/update', "Message From PHPoC: Hello World!", MQTT_QOS0);
		mqtt_publish(0, '$aws/things/mydoor/shadow/update', "Message From PHPoC: Hello World!", MQTT_QOS1);
		//mqtt_publish(0, '$aws/things/mydoor/shadow/update', "Message From PHPoC: Hello World!", MQTT_QOS1, MQTT_PUB_RETAIN);
		sleep(1);
	}

	if(mqtt_loop(0))
	{
		mqtt_recv(0, $recv_topic, $recv_content, $recv_retain);
		
		//TODO , procees the received publish packet here
		if($recv_retain == 1)
			echo "<<a stale message\r\n";

		echo "<<topic:$recv_topic\r\n";
		echo "<<content: $recv_content\r\n";
	}
}

mqtt_disconnect(0);

?>