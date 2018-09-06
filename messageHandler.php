<?php
/*================================
/ The message handler is supposed to handle all types of messages that can be sent:
/ "send purchase", "remove purchase", "send consumer", "remove consumer"
/ it also handles database interaction, thus it requires the $pdo object
================================*/
#include_once "socketHandling.php"
#include_once "mysqlBackend.php"

function handleMessage($msg, $client, $pdo)
{
	$msgTypes = array("send purchase", "remove purchase", "send consumer", "remove consumer");
	echo "in handleMessage \n";
	
	if($msg === "send purchase\n")
	{
		$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING );
		echo "The message was recognised as \"send purchase\"\n";
		sendMessage($client, "ack");
		$cost = receiveMessage($client);
		$buyer = receiveMessage($client);	
		$date = receiveMessage($client);
		$receiver = receiveMessage($client);
		echo "$buyer, $cost, $date, $receiver \n";
		removeNewLine($buyer); removeNewLine($cost); removeNewLine($date); removeNewLine($receiver);
		$sql = "INSERT INTO purchases VALUES (?, ?, ?, ?)";
		$sth = $pdo->prepare($sql);
		print_r($pdo->errorInfo());
		$sth->execute([$buyer, $date, $cost, $receiver]);
		echo "$buyer \n $cost \n $date \n $receiver \n";
	}
	else if($msg === "remove purchase\n")
	{
		//do things
	}
	else if($msg === "send consumer\n")
	{
		//do things
	}
	else if($msg === "remove consumer\n")
	{
		//do things
	}
}

?>
