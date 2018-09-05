#!/usr/bin/php
<?php
include_once "mysqlBackend.php";
include_once "socketHandling.php";
	
/* Set IP and Port for sockets */
$strIPorDOMAIN = "DOMAIN";
$host = "palaven.de";
$port = 39978;

init_mysqlServer();
$sock = init_socket($strIPorDOMAIN, $host, $port);
do{
	$client = acceptClient($sock);
	$msg = receiveMessage($client);
	sendMessage($client, "Hey there");
} while(true);
?>
