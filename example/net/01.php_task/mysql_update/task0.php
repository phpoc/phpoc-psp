<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sn_dns.php";
include_once "/lib/sn_mysql.php";

//Check and print error messages from DB server
function chk_error($result)
{
	if($result !== false)
	{
		$error = mysql_error($result);
		if(strlen($error) != 0)
			echo "Error: $error\r\n";
	}
}

echo "PHPoC example: update data to MYSQL DB\r\n\r\n";

//Enter your DB Server's hostname or IP address!
$server_addr = "192.168.0.100";

//Enter your account information!
$user_name = "user_id";
$password = "password";

//mysql_setup(0, 0, "", true); // enable IPv6

//Connect to DB Server
if(mysql_connect($server_addr, $user_name, $password))
{
	//Create a database named student
	$result = mysql_query("CREATE DATABASE student;");
	chk_error($result);
	$result = mysql_select_db("student");
	chk_error($result);
	//Create a table named student
	$result = mysql_query("CREATE TABLE tbl_student (id INTEGER NOT NULL PRIMARY KEY, name VARCHAR(20) NOT NULL);");
	chk_error($result);
	//Insert a record
	$result = mysql_query("INSERT INTO tbl_student (id, name) VALUES (1, 'John');");
	chk_error($result);
	//Update the record (John -> Roy)
	$result = mysql_query("UPDATE tbl_student SET name='Roy' where id=1;");
	chk_error($result);
	//Inquiry all record
	$result = mysql_query("SELECT * FROM tbl_student;");
	chk_error($result);
	//Get a result of the inquiry
	$result_arr = mysql_fetch_row($result);
	//Print the result
	printf("%s -> %s\r\n", $result_arr[0], $result_arr[1]);
	//Delete the table
	$result = mysql_query("DROP TABLE tbl_student;");
	chk_error($result);
	//Delete the database
	$result = mysql_query("DROP DATABASE student;");
	chk_error($result);
	echo "example has been finished!\r\n";
	mysql_close();
}
else
	echo "example has been failed\r\n";

?>

