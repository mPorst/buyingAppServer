<?php
/*===============================
/ This file encompasses mysql database initialisation and database interaction
/ The "main" function for initialisation is init_mysqlServer()
/ It's purpose is to connect to an existing(!) db and check whether all required tables are existing
/ Or to create the missing tables
/ Other functions are added for readability and modularity
/
/ Note that if you have checked out the git repo you STILL NEED to manually create the mysql.conf file !
/ It has to contain the following exact syntax: "username \n servername \n password \n dbName"
/ (Without quotes and substituting each value with your own configuration. servername refers to domain or ip (probably localhost)
/ additionally you STILL NEED to manually create the employees.conf file ! syntax: "employee \n active \n debts"
/ where employee is a VARCHAR(255), active is BIT(1) with 0===true, 1===false and debts is decimal(6,2)
/
/ Understand that you have to MANUALLY create the db before executing this script !
===============================*/

/*** FUNCTIONS FOR SETTING UP THE MYSQL DATABASE ***/

/* Functions Added for Modularity and Readability */
function removeNewline(&$inputString ) {
	$inputString = strtok($inputString, "\n");
}

/* Set Up The Pdo Object */
function pdoDbSetup($account, $host, $password, $db) {
	/* Required Variables */
	$tables = array("purchases", "consumers", "employees", "summary");
	$tableParameters = array("purchases" => "(buyers VARCHAR(255), dates DATE, prices DECIMAL(6,2) UNSIGNED, receivers VARCHAR(255))",
				"consumers" => "(consumers VARCHAR(255), date DATE, hasEaten VARCHAR(255))",
				"employees" => "(employees VARCHAR(255), active VARCHAR(255), balance DECIMAL(6,2))",
				"summary" => "(months TINYINT, years YEAR, cost DECIMAL(8,4), costPerMeal DECIMAL(8,4))"
				);

	/* Set Up the PDO Connection to the Database */
	$dsn = "mysql:host=$host;dbname=$db";
	try{
		$pdo = new PDO($dsn, $account, $password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}
	catch (PDOException $e){
		throw new PDOException($e->getMessage(), (int)$e->getCode());
	}

	/* Now Check For The Existing Tables */
		
	echo "Checking database \"$db\" for tables:\n \n";
	foreach ($tables as $singleTable)
	{
		// no prepared statement required as no dynamic elements are contained within these queries!
		// Also holds true for $singleTable as these values are from a predefined array.
		try
		{
			$pdo->query("SELECT 1 FROM $singleTable LIMIT 1");
			echo "table \"$singleTable\" exists \n";
		}
		catch(PDOException $except)
		{
			echo "Creating table $singleTable \n";
			$created = $pdo->query("CREATE TABLE $singleTable $tableParameters[$singleTable]");
			if($created === true)
			{
				echo "Successfully creted table $singleTable \n";
			}
		}
	}
	echo "\n";
	return $pdo;
}


/* Check For Employees */
// use of prepared statements required to catch names like O'hara
// however malicious sql injection not possible as names are read from a serverside config file
function updateEmployees($pdo, $employees, $active, $debts) {
	$length = count($employees);
	
	for($i=0; $i<$length; $i++)
	{
		try
		{	
			$sql = "SELECT * FROM employees WHERE employees = ?";
			$sth = $pdo->prepare($sql);
			$sth->execute([$employees[$i]]);
			if($sth->rowCount() === 0)
			{
				echo "Employee $employees[$i] is missing. adding... \n";
				$sql = "INSERT INTO employees VALUES(?,?,?)";
				//$sql = "INSERT INTO employees VALUES('thisWorks',1,19)";
				$sth = $pdo->prepare($sql);
				echo "values: name:".$employees[$i]." - active:".$active[$i]." - balance:".$debts[$i]."\n";
				$sth->execute([$employees[$i], $active[$i], $debts[$i]]);
				//$sth->execute(["lulzForTheLalz", 1, 19]);
			}
		}
		catch(PDOException $exception)
		{
			echo "PDO EXCEPTION OCCURRED WHEN SELECTING EMPLOYEES \n";
			echo "exception: ".$exception;
			return $exception->getMessage();
		}
	}
}


/*function insertPurchase($pdo, $price, $buyer, $date, $receiver)
{
	$pdo->	
}
*/

/* The Initialisation Function: */

function init_mysqlServer() {
	$configFileName = "mysql.conf";
	$employeeFileName = "employees.conf";

	/* Read the Config File for Mysql Table Config */
	$configFile = fopen($configFileName, "r") or die("Could not open $configFileName. Does it exist and can this script read it ?");
	// The script assumes that there are 4 lines present in exactly this order: username \n servername \n password \n dbName
	// Caution as no error checking is performed here.
	$user = fgets($configFile);
	$server = fgets($configFile);
	$password = fgets($configFile);
	$dbName = fgets($configFile);
	//fgets does not automatically remove the trailing \n... so do it by hand:
	removeNewline($user);
	removeNewline($server);
	removeNewline($password);
	removeNewLine($dbName);
	fclose($configFile);

	/* Read the Config File for Setting Up the Employee Table */	
	$employees = array();
	$active = array();
	$debts = array();	
	$employeeFile = fopen($employeeFileName, "r") or die ("Could not open $employeeFileName. Does it exist and can this script read it ?");
	
	$debug = 0;
	while(!feof($employeeFile))
	{
		$employee = fgets($employeeFile);
		$act = fgets($employeeFile);
		$debt = fgets($employeeFile);
		removeNewLine($employee);
		removeNewLine($act);
		removeNewLine($debt);
		if(strlen($employee) < 1)
		{
			echo "empty line in $employeeFileName. Closing file.\n";
			break;
		}
		
		array_push($employees, $employee);
		array_push($active, $act);
		array_push($debts, $debt);
		$debug++;
	}
	fclose($employeeFile);
	
	/* Setup Database Connection And Initial Entries Using Previously Read Configs */
	$pdo = pdoDbSetup($user, $server, $password, $dbName);
	updateEmployees($pdo, $employees, $active, $debts);
	return $pdo;
}

/*** FUNCTIONS FOR ACCESSING THE MYSQL TABLE ***/

function checkEmployeeExists($employee, $pdo)
{
	try
	{
		$sql = "SELECT * FROM employees WHERE employees = ?";
		$personInfoSql = $pdo->prepare($sql);
		$personInfoSql->execute([$employee]);
		if(!($personInfoSql->rowCount()>0))
		{
			return false;
		}
		else
		{
			return true;
		}
		
	} 
	catch(PDOException $except)
	{
		$except->getMessage();
		return $except;
	}
}

function checkOverallAmountRequests($maxTransactions, $pdo)
{
		$today = date('Y-m-d');
		$sql = "SELECT COUNT(prices) as count FROM purchases WHERE dates = ?";
		$countRequestsSql = $pdo->prepare($sql);
		$countRequestsSql->execute([$today]);
		$countRequests = $countRequestsSql->fetch();
		//the following step simply converts a one element array to a float (or whatever type the element is)
		$countRequests = $countRequests['count'];
		//$countRequests=$countRequests+1-1;
		if($countRequests > 6)
		{
			return false;
		}
		return true;
}

function checkAmountRequestsByUser($employee, $maxTransactionsPerUser, $pdo)
{
	$today = date('Y-m-d');
	$sql = "SELECT COUNT(prices) as count FROM purchases WHERE dates = ? AND buyers = ?";
	$countRequestsPerEmpSql = $pdo->prepare($sql);
	$countRequestsPerEmpSql->execute([$today, $employee]);
	$countRequestsPerEmp = $countRequestsPerEmpSql->fetch();
	//the following step simply converts a one element array to a float (or whatever type the element is)
	$countRequestsPerEmp = $countRequestsPerEmp['count'];
	echo "Checking amount of user requests... \n";
	echo "countRequestsPerEmp: $countRequestsPerEmp \n";
	if($countRequestsPerEmp > 3)
	{
		return false;
	}	
	return true;
}

function insertPurchase($buyer, $date, $cost, $pdo)
{
	try
	{
		$sql = "INSERT INTO purchases VALUES (?, ?, ?, ?)";
		$insertSql = $pdo->prepare($sql);
		//print_r($pdo->errorInfo());
		$insertSql->execute([$buyer, $date, $cost, "none"]);
		return true;
	}
	catch(PDOException $except)
	{
		$except->getMessage();
		// return sth like "only positive numbers up to 9999.99 allowed", keep for debug purposes
		return $except;
	}
}

function insertTransaction($buyer, $date, $cost, $receiver, $pdo)
{	
	try
	{
		// Add new entry to table purchases
		$sql = "INSERT INTO purchases VALUES (?, ?, ?, ?)";
		$insertSql = $pdo->prepare($sql);
		$insertSql->execute([$buyer, $date, $cost, $receiver]);
		// + for receiver
		$sql = "UPDATE employees SET balance = balance + ? WHERE employees = ?";
		$newBalanceSql = $pdo->prepare($sql);
		$newBalanceSql->execute([$cost, $receiver]);
		// - for buyer
		$sql = "UPDATE employees SET balance = balance - ? WHERE employees = ?";
		$newBalanceSql = $pdo->prepare($sql);
		$newBalanceSql->execute([$cost, $buyer]);
		return true;
	}	
	catch(PDOException $except)
	{
		$except->getMessage();
		return $except;
	}
}
			
function deletePurchases($buyer, $date, $pdo)
{
	try
	{
		$sql = "DELETE FROM purchases WHERE buyers = ? AND dates = ? AND receivers = ?";
		$deleteSql = $pdo->prepare($sql);
		$deleteSql->execute([$buyer, $date, "none"]);
		if($deleteSql->rowCount() === 0)
		{
			return "There were no purchases to be deleted";
		}
		return true;
	}
	catch(PDOException $except)
	{
		$except->getMessage();
		// return sth like "only positive numbers up to 9999.99 allowed", keep for debug purposes
		return $except;
	}
}

function deleteTransactions($buyer, $date, $receiver, $pdo)
{
	try
	{
		$sql = "DELETE FROM purchases WHERE buyers = ? AND dates = ? AND receivers = ?";
		$deleteSql = $pdo->prepare($sql);
		$deleteSql->execute([$buyer, $date, $receiver]);
		if($deleteSql->rowCount() === 0)
		{
			return "There were no transactions to be deleted";
		}
		return true;
	}
	catch(PDOException $except)
	{
		$except->getMessage();
		// return sth like "only positive numbers up to 9999.99 allowed", keep for debug purposes
		return $except;
	}
}

function getBalance($buyer, $pdo)
{
	try
	{
		$sql = "SELECT * FROM employees WHERE employees = ?";
		$selectSql = $pdo->prepare($sql);
		$selectSql->execute([$buyer]);
		if($selectSql->rowCount() === 0)
		{
			return "Something went wrong, could not find a line in employees with name $buyer";
		}
		$select = $selectSql->fetch();
		return "$buyer has currently a balance of ".$select['balance']." euros.";
	}
	catch(PDOException $except)
	{
		$except->getMessage();
		return $except;
	}
}

function getSummary($pdo)
{
	try
	{
		$summaryArray = array();
		//$summary = [];
		$thisYear = date('Y');
		$thisMonth = date('m');
		$year = $thisYear;
		$month = $thisMonth;
		for($i=1; $i<13; $i++)
		{
			$month = $month-1;
			if($month == 0) {
				$month = 12/*+$i+1*/;
				//$thisMonth = 12+$i;
				//$thisYear = $thisYear-1;
				$year = $year-1;
			}
			echo "month:  $month and year: $year\n";
			$summarySql = $pdo->query("SELECT * FROM summary WHERE years = $year AND months = $month");
			$summary = $summarySql->fetch();
			//$summary[] = $summarySql;
			try
			{
				array_push($summaryArray, $summary);
			}
			catch(Exception $e)
			{
				echo $e->getMessage();
			}
			
		}
		return $summaryArray;
	}
	catch(PDOException $except)
	{
		$except->getMessage();
		return $except;
	}
}

function setSummary($year, $month, $price, $pricePerMeal, $pdo)
{
	$pdo->query("INSERT INTO summary VALUES($year, $month, $price, $pricePerMeal);
}

function insertConsumer($consumer, $date, $hasEaten, $pdo)
{
	try
	{
		$sql = "SELECT COUNT(consumers) as consumerCount FROM consumers WHERE consumers = ? AND date = ?";
		$consumerEntrySql = $pdo->prepare($sql);
		$consumerEntrySql->execute([$consumer, $date]);
		$consumerEntry = $consumerEntrySql->fetch();
		$consumerEntry = $consumerEntry['consumerCount'];
		if($consumerEntry == 1)
		{
			$sql = "SELECT hasEaten FROM consumers WHERE consumers = ? AND date = ?";	
			$consumerHasEatenSql = $pdo->prepare($sql);
			$consumerHasEatenSql->execute([$consumer, $date]);
			$consumerHasEaten= $consumerHasEatenSql->fetch();
			$consumerHasEaten = $consumerHasEaten['hasEaten'];
			if($consumerHasEaten != $hasEaten) // update table
			{
				$sql = "UPDATE consumers SET hasEaten = ? WHERE consumers = ? AND date = ?";
				$updateSql = $pdo->prepare($sql);
				$updateSql->execute([$hasEaten, $consumer, $date]);
				return true;
			}
			else
			{
				return "Nothing to update";
			}
		}
		else if($consumerEntry == 0)
		{
			$sql = "INSERT INTO consumers VALUES(?, ?, ?)";	
			$consumerHasEatenSql = $pdo->prepare($sql);
			$consumerHasEatenSql->execute([$consumer, $date, $hasEaten]);
			return true;
		}
		else
		{
			return "Please contact your admin, there are too many entries for you in the consumers table: $consumerEntry for today";
		}
	}
	catch (PDOException $e)
	{
		$e->getMessage();
		return $e;
	}
}

function hasConsumerEaten($consumer, $date, $pdo)
{
	try
	{
		$sql = "SELECT COUNT(consumers) as consumerCount FROM consumers WHERE consumers = ? AND date = ?";
		$consumerEntrySql = $pdo->prepare($sql);
		$consumerEntrySql->execute([$consumer, $date]);
		$consumerEntry = $consumerEntrySql->fetch();
		$consumerEntry = $consumerEntry['consumerCount'];
		echo "consumer entry: $consumerEntry \n";
		if($consumerEntry == 1)
		{
			echo "Entering sql selection... \n";
			$sql = "SELECT hasEaten FROM consumers WHERE consumers = ? AND date = ?";	
			$consumerHasEatenSql = $pdo->prepare($sql);
			$consumerHasEatenSql->execute([$consumer, $date]);
			$consumerHasEaten= $consumerHasEatenSql->fetch();
			$consumerHasEaten = $consumerHasEaten['hasEaten'];
			return $consumerHasEaten; // either false or true
		}
		else if($consumerEntry == 0)
		{
			echo "Now returning false... \n";
			return "false";
		}
	}
	catch(PDOException $e)
	{
		$e->getMessage();
		return $e;
	}
	return "false";
}

function haveEatenToday($date, $pdo)
{
	try
	{
		$sql = "SELECT consumers from consumers WHERE date = ? and hasEaten = ?";
		$eatenTodaySql = $pdo->prepare($sql, 'true');
		$eatenTodaySql->execute([$date]);
		$eatenToday = $eatenTodaySql->fetch();
		$eatenToday = $eatenToday['consumers'];
		return $eatenToday;
	}
	catch(PDOException $e)
	{
		$e->getMessage();
		return $e;
	}
	return "internal server error in mysqlBackend/haveEatenToday";
}	

function 

?>
