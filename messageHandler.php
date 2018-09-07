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
		echo "after first exception handling DEBUG \n";
		removeNewLine($buyer); removeNewLine($cost); removeNewLine($date); removeNewLine($receiver);
		/* Check If Buyer Exists */
		try
		{
			$sql = "SELECT * FROM employees WHERE employees = ?";
			$personInfoSql = $pdo->prepare($sql);
			$personInfoSql->execute([$buyer]);
			if(!($personInfoSql->rowCount()>0))
			{
				sendMessage($client, "Could not find name \"$buyer\" in employees.");
				return;
			}
			
		} 
		catch(PDOException $except)
		{
			sendMessage($client, "ERROR: $except");
			return;
		}

		/* Check Amount of Request Already Sent By Buyer Today */
		$sql = "SELECT COUNT(prices) as count FROM purchases WHERE dates = ?";
		$today = date('Y-m-d');
		$countRequestsSql = $pdo->prepare($sql);
		$countRequestsSql->execute([$today]);
		$countRequests = $countRequestsSql->fetch();
		// force $countRequest to int
		$countRequests = $countRequests['count'];
		//$countRequests=$countRequests+1-1;
		echo $countRequests." is the number of requests done today, the $today \n";
		if($countRequests >= 7)
		{
			sendMessage($client, "ERROR: Only 7 purchases/transactions allowed per day");
			return;
		}

		//$sql = "SELECT COUNT(prices) FROM purchases WHERE buyer = ? AND date = ?";
		/* Check Receiver Field */
		if($receiver == "none")
		{
			try
			{
				$sql = "INSERT INTO purchases VALUES (?, ?, ?, ?)";
				$insertSql = $pdo->prepare($sql);
				print_r($pdo->errorInfo());
				$insertSql->execute([$buyer, $date, $cost, $receiver]);
				sendMessage($client, "$buyer has paid $cost");
			}
			catch(PDOException $except)
			{
				$except->getMessage;
				sendMessage($client, "ERROR: $except");
			}
		}
		else
		{
			/* Check If Receiver Exists */
			try
			{
				$sql = "SELECT * FROM employees WHERE employees = ?";
				$personInfoSql = $pdo->prepare($sql);
				$personInfoSql->execute([$receiver]);
				if(!($personInfoSql->rowCount()>0))
				{
					sendMessage($client, "Could not find the receiver \"$receiver\" in employees.");
					return;
				}
			}	
			catch(PDOException $except)
			{
				sendMessage($client, "ERROR: $except");
			}
			/* Try To Push Balance From Buyer To Receiver */
			try
			{
				// + for receiver
				$sql = "UPDATE employees SET balance = balance + ? WHERE employees = ?";
				$newBalanceSql = $pdo->prepare($sql);
				$newBalanceSql->execute([$cost, $receiver]);
				// - for buyer
				$sql = "UPDATE employees SET balance = balance - ? WHERE employees = ?";
				$newBalanceSql = $pdo->prepare($sql);
				$newBalanceSql->execute([$cost, $buyer]);
				// lastly add new entry to table purchases
				$sql = "INSERT INTO purchases VALUES (?, ?, ?, ?)";
				$insertSql = $pdo->prepare($sql);
				$insertSql->execute([$buyer, $date, $cost, $receiver]);
				sendMessage($client, "$buyer has sent $cost to $receiver");
			}	
			catch(PDOException $except)
			{
				sendMessage($client, "ERROR: $except");
			}
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
