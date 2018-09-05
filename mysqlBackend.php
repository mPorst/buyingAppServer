<?php
/*===============================
/ The "main" function of this file is init_server()
/ It's purpose is to connect to an existing(!) db and check whether all required tables are existing
/ Or to create the missing tables
/ Other functions are added for readability and modularity
/ Note that if you have checked out the git repo you STILL NEED to manually create the mysql.conf file !
/ It has to contain the following exact syntax: "username \n servername \n password \n dbName"
/ (Without quotes and substituting each value with your own configuration. servername refers to domain or ip (probably localhost)
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
				"employees" => "(employees VARCHAR(255), active BIT(1), debts DECIMAL(6,2))",
				"summary" => "(months TINYINT, years YEAR, cost DECIMAL(6,2), costPerMeal DECIMAL(6,2))"
				);

	/* Set Up the PDO Connection to the Database */
	$dsn = "mysql:host=$host;dbname=$db";
	try{
		$pdo = new PDO($dsn, $account, $password);
	}
	catch (\PDOException $e){
		throw new \PDOException($e->getMessage(), (int)$e->getCode());
	}

	/* Now Check For The Existing Tables */
		
	echo "Checking database \"$db\" for tables:\n \n";
	foreach ($tables as $singleTable)
	{
		// no prepared statement required as no dynamic elements are contained within these queries!
		// Also holds true for $singleTable is these values are from a predefined array.
		$tableExists = $pdo->query("SELECT 1 FROM $singleTable LIMIT 1");
		if(!$tableExists)
		{
			echo "Creating table $singleTable \n";
			$pdo->query("CREATE TABLE $singleTable $tableParameters[$singleTable]");
		}
		echo "table \"$singleTable\" exists \n";
	}
	echo "\n";
	return $pdo;
}

/* The "Main" Function: */

function init_mysqlServer() {
	$configFileName = "mysql.conf";
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
	$pdo = pdoDbSetup($user, $server, $password, $dbName);
}

// this is supposed to be called in the main script -> remove after testing
?>
