<?php
/*================================
/ The message handler is supposed to handle all types of messages that can be sent:
/ "send purchase", "remove purchase", "send consumer", "remove consumer", "get summary", "get balance"
/ it also handles database interaction, thus it requires the $pdo object
================================*/
#include_once "socketHandling.php"
#include_once "mysqlBackend.php"

function checkDateValid($client, $onlyCurrentDay, $date)
{
	$temp1 = array();
	$today = date('Y-m-d');
	$temp1 = explode("-", $today);
	$yearToday = $temp1[0];
	$monthToday = $temp1[1];
	$dayToday = $temp1[2];

	$temp2 = array();
	$temp2 = explode("-", $date);

	// check for 3 elements within $temp
	if(sizeof($temp2) != 3)
	{
		sendMessage($client, "Please use the following date format: YYYY-MM-DD. You sent $date");
		return false;
	}

	$yearSent = $temp2[0];
	$monthSent = $temp2[1];
	$daySent = $temp2[2];
	if(!checkdate($monthSent, $daySent, $yearSent))
	{
		sendMessage($client, "Please use the following date format: YYYY-MM-DD. You sent $date");
		return false;
	}

	// check for onlyCurrentDay
	if($date != $today && $onlyCurrentDay == "true")
	{
		sendMessage($client, "Please only send queries for this day. You sent: $date. Today is: $today");
		return false;
	}

	
	if($yearSent != $yearToday || $monthSent != $monthToday)
	{
		sendMessage($client, "Please only send queries for the current month. you sent $date. Today is: $today");
		return false;
	}

	if(intval($daySent) > intval($dayToday))
	{
		sendMessage($client, "You sent a date in the future: $date. Today is $today");
		return false;
	}

	return true;
}

//This function is supposed to be called only AFTER checkDateValid as it does no verify the date
function checkDateToday($date)
{	
	$today = date('Y-m-d');
	if($today == $date)
	{
		return true;
	}	
	else
	{
		return false;
	}
}


function handleMessage($msg, $client, $blockAllTraffic, $onlyCurrentDay, $pdo)
{
	if($blockAllTraffic == "true")
	{
		sendMessage($client, "Server is configured to block all traffic");
		return;
	}
	
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
		if(!checkDateValid($client, $onlyCurrentDay, $date))
		{
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

		/* Check Receiver Field to choose proper method to call */
		if($receiver == "none")
		{
			$inserted = insertPurchase($buyer, $date, $cost, $pdo);
			// $inserted can either be "true" or contains the error message
			if($inserted === true)
			{
				sendMessage($client, "$buyer has paid $cost on $date.");
				// log the message if the send date was not today
				if(!checkDateToday($date))
				{
					$today = date("Y-m-d");
					mkdir("log");
					mkdir("sentForDifferentDay");
					$fp = fopen("log/sentForDifferentDay/$today.log", 'w');
					fwrite("$buyer has paid $cost on $date.");
					fclose($fp);
				}
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
				$today = date('Y-m-d');
				if($date != $today)
				{
					sendMessage($client, "Please send transactions only for the current date");
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
		if(!checkDateValid($client, $onlyCurrentDay, $date))
		{
			return;
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
				if(!checkDateToday($date))
				{
					$fp = fopen("log/sentForDifferentDay/$today.log", 'w');
					fwrite($fp, "Removed all purchases from $buyer at $date. This deletion requests was logged because it was not for today");
					fclose($fp);
					sendMessageClient($client, "Removed all purchases from $buyer at $date. This deletion requests was logged because it was not for today");
				}
				else
				{
					sendMessage($client, "Removed all purchases from $buyer at $date");
				}
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
				if(!checkDateToday($date))
				{
					sendMessage($client, "Please only send deletion requests for transactions from the same day. If you want to delete a receipt, please specify \"none\" for receiver");
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
		if(!checkDateValid($client, $onlyCurrentDay, $date))
		{
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
		if(!checkDateValid($client, $onlyCurrentDay, $date))
		{
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
	else if($msg === "get eaters\n")
	{
		echo "The message was recognised as \"get balance\"\n";
		sendMessage($client, "ack");
		$date = receiveMessage($client);
		$eatenToday = haveEatenToday($today, $pdo);
		foreach($eatenToday as $consumer)
		{
			sendMessage($client, $consumer);
			return;
		}
		return;
	}
	else
	{
		sendMessage($client, "Sorry, I did not understand your query");	
	}
}

?>
