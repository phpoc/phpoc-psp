<?php

if(_SERVER("REQUEST_METHOD"))
	exit; // avoid php execution via http request

include_once "/lib/sn_dns.php";
include_once "/lib/sn_mysql.php";

echo "PHPoC example: insert data to MYSQL DB\r\n\r\n";

//Enter your DB Server's hostname or IP address!
$server_addr = "192.168.0.100";

//Enter your account information!
$user_name = "user_id";
$password = "password";

//Connect to DB Server
if(mysql_connect($server_addr, $user_name, $password) === false)
	exit(mysql_error());

//Create a database named student
if(mysql_query("CREATE DATABASE student;") === false)
	exit(mysql_error());

if(mysql_select_db("student") === false)
	exit(mysql_error());

//Create a table named student
if(mysql_query("CREATE TABLE tbl_student (id INTEGER NOT NULL PRIMARY KEY, name VARCHAR(20) NOT NULL);") === false)
	exit(mysql_error());

//Insert a record
if(mysql_query("INSERT INTO tbl_student (id, name) VALUES (1, 'John');") === false)
	exit(mysql_error());

//Inquiry all record
$result = mysql_query("SELECT * FROM tbl_student;");
if($result === false)
	exit(mysql_error());
else
{
	$result_arr = mysql_fetch_row($result);
	//Print the result
	printf("%s -> %s\r\n", $result_arr[0], $result_arr[1]);
}

//Delete the table
if(mysql_query("DROP TABLE tbl_student;") === false)
	exit(mysql_error());

//Delete the database
if(mysql_query("DROP DATABASE student;") === false)
	exit(mysql_error());

mysql_close();
echo "example has been finished!\r\n";

?>
