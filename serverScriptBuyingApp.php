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

$initial = true;

$sock = init_socket($strIPorDOMAIN, $host, $port);
do{
	$pdo = init_mysqlServer($initial);
	$client = acceptClient($sock);
	$msg = receiveMessage($client);
	handleMessage($msg, $client, $blockAllTraffic, $onlyCurrentDay, $pdo);
	$initial = false;
} while(true);

?>
