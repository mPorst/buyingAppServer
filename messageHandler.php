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
		$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		echo "The message was recognised as \"send purchase\"\n";
		sendMessage($client, "ack");
		$buyer = receiveMessage($client);	
		$date = receiveMessage($client);
		$cost = receiveMessage($client);
		$receiver = receiveMessage($client);
		removeNewLine($buyer); removeNewLine($cost); removeNewLine($date); removeNewLine($receiver);
		try
		{
			$sql = "INSERT INTO purchases VALUES (?, ?, ?, ?)";
			$sth = $pdo->prepare($sql);
			print_r($pdo->errorInfo());
			$sth->execute([$buyer, $date, $cost, $receiver]);
			sendMessage($client, "$buyer has paid $cost");
		}
		catch(PDOException $except)
		{
			$except->getMessage;
			sendMessage($client, "ERROR: $except");
		}
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
	else if($msg === "get summary\n")
	{
		//do things
	}
}

?>
