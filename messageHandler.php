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
		removeNewLine($buyer); removeNewLine($cost); removeNewLine($date); removeNewLine($receiver);

		/* Verify the sent date */
		if($date != $today)
		{
			sendMessage($client, "It is only allowed to send transactions from the current day");
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
			else
			{
				sendMessage($client, $inserted);
				return;
			}
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
				if($inserted != true)
				{
					sendMessage($client, "$inserted");
					return;
				}
				sendMessage($client, "$buyer has sent $cost to $receiver");
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
		//do things
	}
	else if($msg === "remove consumer\n")
	{
		//do things
	}
	else if($msg === "get summary\n")
	{
		echo "The message was recognised as \"get summary\"\n";
		sendMessage($client, "ack");
		$summary = getSummary($pdo);
		echo "DEBUG: get summary was ok... \n length of array:".sizeof($summary)."\n";
		for($i = 0; $i<12; $i++)
		{
			usleep(30000);
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
