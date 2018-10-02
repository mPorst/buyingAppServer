#!/usr/bin/php
<?php
include_once "mysqlBackend.php";
include_once "socketHandling.php";
include_once "messageHandler.php";
include_once "confParser.php";
	
/* Set IP and Port for sockets */
$strIPorDOMAIN = "DOMAIN";
$host = "palaven.de";
$port = 39978;

$params = parseConfig("server.conf");
$blockAllTraffic = $params[0];
echo "Block All Traffic: $blockAllTraffic\n";
$onlyCurrentDay = $params[1];


$pdo = init_mysqlServer();
$sock = init_socket($strIPorDOMAIN, $host, $port);
do{
	$client = acceptClient($sock);
	$msg = receiveMessage($client);
	handleMessage($msg, $client, $blockAllTraffic, $onlyCurrentDay, $pdo);
	//sendMessage($client, "Hey there");
} while(true);

?>
