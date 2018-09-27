<?php
/*================================
/ The message handler is supposed to handle all types of messages that can be sent:
/ "send purchase", "remove purchase", "send consumer", "remove consumer"
/ it also handles database interaction, thus it requires the $pdo object
================================*/
#include_once "socketHandling.php"
#include_once "mysqlBackend.php"

//functions to outsource:
//checkEmployeeExists($employee, $pdo)
//check
function handleMessage($msg, $client, $pdo)
{
	$today = date('Y-m-d');
	$msgTypes = array("send purchase", "remove purchase", "send consumer", "remove consumer");
	
	if($msg === "send purchase\n")
	{
		echo "The message was recognised as \"send purchase\"\n";
		sendMessage($client, "ack");
		$buyer = receiveMessage($client);	
		$date = receiveMessage($client);
		$cost = receiveMessage($client);
		$receiver = receiveMessage($client);
		if($buyer == "readingError" or $date == "readingError" or $cost == "readingError" or $receiver == "readingError")
		{
			sendMessage($client, "There was an error while transmitting your data");
			return;
		}
		removeNewLine($buyer); removeNewLine($cost); removeNewLine($date); removeNewLine($receiver);
		$cost = str_replace(",", ".", $cost);

		$isNumeric = is_numeric($cost);
		if(!$isNumeric)
		{
			sendMessage($client, "Please only send numbers");
			return;
		}

		$formattedCost = number_format($cost, 2, ".","");
		if($formattedCost != $cost)
		{
			sendMessage ($client, "Please only use prices with 2 digits after decimal point");
			return;
		}

		/* Verify the sent date */
		if($date != $today)
		{
			sendMessage($client, "It is only allowed to send transactions from the current day");
			return;
		}

		/* Check If Buyer Exists */
		if(!checkEmployeeExists($buyer,$pdo))
		{
			sendMessage($client, "Could not find name \"$buyer\" in employees.");
			return;
		}

		/* Check Amount of Request Already Sent (Overall / By Buyer) Today */
		$maxTransactionsOverall = 6;
		$maxTransactionsBySingleUser = 3;
		if(!checkOverallAmountRequests($maxTransactionsOverall, $pdo))
		{
			sendMessage($client, "There are only $maxTransactionsOverall transactions allowed per day. Please try again tomorrow");
			return;
		}
		if(!checkAmountRequestsByUser($buyer, $maxTransactionsBySingleUser, $pdo))
		{
			sendMessage($client, "You have already issued your $maxTransactionsBySingleUser allowed transactions per day");
			return;
		}

		/* Check Receiver Field to choose proper method to cal */
		if($receiver == "none")
		{
			$inserted = insertPurchase($buyer, $date, $cost, $pdo);
			// $inserted can either be "true" or contains the error message
			if($inserted === true)
			{
				sendMessage($client, "$buyer has paid $cost. inserted: $inserted");
			}
			sendMessage($client, $inserted);
			return;
		}
		else
		{
			// if receiver is not "none": check if receiver exists!
			if(!checkEmployeeExists($receiver, $pdo))
			{
				sendMessage($client, "The specified receiver $receiver does not exist. If you want to send a receipt, please specify \"none\"");
				return;
			}
			else
			{
				$inserted = insertTransaction($buyer, $date, $cost, $receiver, $pdo);
				if($inserted === true)
				{
					sendMessage($client, "$buyer has sent $cost to $receiver");
					return;
				}
				sendMessage($client, "$inserted");
				return;
			}
		}
	}
	else if($msg === "remove purchase\n")
	{
		echo "The message was recognised as \"remove purchase\"\n";
		sendMessage($client, "ack");
		$buyer = receiveMessage($client);	
		$date = receiveMessage($client);
		$receiver = receiveMessage($client);
		removeNewLine($buyer); removeNewLine($date); removeNewLine($receiver);

		/* Verify The Date */
		if($date != $today)
		{
			sendMessage($client, "It is only allowed to remove transactions from the current day");
		}

		/* Check If Buyer Exists */
		if(!checkEmployeeExists($buyer,$pdo))
		{
			sendMessage($client, "Could not find name \"$buyer\" in employees.");
			return;
		}

		if($receiver == "none")
		{
			$deleted = deletePurchases($buyer, $date, $pdo);
			// $inserted can either be "true" or contains the error message
			if($deleted === true)
			{
				sendMessage($client, "Removed all purchases from $buyer at $date");
			}
			else
			{
				sendMessage($client, $deleted);
				return;
			}
		}
		else
		{
			// if receiver is not "none": check if receiver exists!
			if(!checkEmployeeExists($receiver, $pdo))
			{
				sendMessage($client, "The specified receiver $receiver does not exist. If you want to delete a receipt, please specify \"none\"");
				return;
			}
			else
			{
				$deleted = deleteTransactions($buyer, $date, $receiver, $pdo);
				if($deleted != true)
				{
					sendMessage($client, "$deleted");
					return;
				}
				sendMessage($client, "Deleted all transactions from $buyer to $receiver at $date");
			}
		}
		
		
	}
	else if($msg === "send consumer\n")
	{
		echo "The message was recognised as \"send consumer\"\n";
		sendMessage($client, "ack");
		$consumer = receiveMessage($client);
		$date = receiveMessage($client);
		$hasEaten = receiveMessage($client);
		removeNewLine($consumer);removeNewLine($date);removeNewLine($hasEaten);
		if(!checkEmployeeExists($consumer, $pdo))
		{
			sendMessage($client, "$consumer does not exist in employees");
			return;
		}
		if($date != $today)
		{
			sendMessage($client, "Please only send queries for this day. You sent: $date. Today is: $today");
			return;
		}
		if(!($hasEaten == "true" or $hasEaten == "false"))
		{
			sendMessage($client, "Either send \"true\" or \"false\" in this query, you sent $hasEaten");
			return;
		}
		
		$insertedConsumer = insertConsumer($consumer, $date, $hasEaten, $pdo);
		if($insertedConsumer !== true)
		{
			sendMessage($client, $insertedConsumer);
			return;
		}	
		else
		{
			sendMessage($client, "$consumer has eaten today, $date : $hasEaten");
		}
			
	}
	else if($msg === "get consumer\n")
	{
		echo "The message was recognised as \"get consumer\"\n";
		sendMessage($client, "ack");
		$consumer = receiveMessage($client);
		$date = receiveMessage($client);
		removeNewLine($consumer);removeNewLine($date);
		if(!checkEmployeeExists($consumer, $pdo))
		{
			sendMessage($client, "$consumer does not exist in employees");
			return;
		}
		if($date != $today)
		{
			sendMessage($client, "Please only send queries for this day. You sent: $date. Today is: $today");
			return;
		}
		$consumerEaten = hasConsumerEaten($consumer, $date, $pdo);
		sendMessage($client, $consumerEaten);
	}
	else if($msg === "get summary\n")
	{
		echo "The message was recognised as \"get summary\"\n";
		sendMessage($client, "ack");
		$summary = getSummary($pdo);
		echo "DEBUG: get summary was ok... \n length of array:".sizeof($summary)."\n";
		for($i = 0; $i<12; $i++)
		{
			usleep(50000);
			$year = $summary[$i]['years'];
			$month = $summary[$i]['months'];
			sendMessage($client, date('F, Y', strtotime(date("$year-$month")))." - cost: ".$summary[$i]['cost']." cost per meal: ".$summary[$i]['costPerMeal'] );
		}
	}
	else if($msg === "get balance\n")
	{
		echo "The message was recognised as \"get balance\"\n";
		sendMessage($client, "ack");
		$buyer = receiveMessage($client);	
		removeNewLine($buyer);
		if(!checkEmployeeExists($buyer, $pdo))
		{
			sendMessage($client, "Could not find $buyer in employees list");
			return;
		}
		sendMessage($client, getBalance($buyer, $pdo));
	}
	else
	{
		sendMessage($client, "Sorry, I did not understand your query");	
	}
}

?>
