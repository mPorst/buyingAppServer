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

/* Functions Added for Modularity and Readability */
function removeNewline(&$inputString ) {
	$inputString = strtok($inputString, "\n");
}

function pdoDbSetup($account, $host, $password, $db) {
	/* Required Variables */
	$tables = array("purchases", "consumers", "employees", "summary");
	$tableParameters = array("purchases" => "(buyers VARCHAR(255), dates DATE, prices DECIMAL(6,2), receivers VARCHAR(255))",
				"consumers" => "(consumers VARCHAR(255), date DATE, hasEaten BIT(1))",
				"employees" => "(employees VARCHAR(255), active VARCHAR(255), debts DECIMAL(6,2))",
				"summary" => "(months TINYINT, years YEAR, cost DECIMAL(6,2), costPerMeal DECIMAL(6,2))"
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
				echo "values: name:".$employees[$i]." - active:".$active[$i]." - debts:".$debts[$i]."\n";
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

/* The "Main" Function: */

function init_mysqlServer() {
	$configFileName = "mysql.conf";
	$employeeFileName = "employees.conf";

	/* Read the Config File for Mysql Table Config */
	$configFile = fopen($configFileName, "r") or die("Could not open $configFileName. Does it exist and can this script read it ?");
	// The script assumes that there are 3 lines present in exactly this order: username \n servername \n password \n dbName
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
?>
