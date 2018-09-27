#!/usr/bin/php

<?php
/* Set Up Mandatory DB Connection */
function removeNewLine(&$inputString) {
	$inputString = strtok($inputString, "\n");
}

// check that the overall balance equals 0 or correct it to be 0
function correctOverallBalance($pdo)
{	
	$dir = getcwd();
	$dir = $dir."/log/rounding_".date("Y-m-d_H:i:s").".log";
	$fp = fopen($dir, 'w');
	if($fp == false)
	{
		die("Dying as I could not create file $dir");
	}
	fwrite($fp, "In the performed calculations rounding errors may occur. In this case random employees are chosen to take or give money, depending on the direction of rounding error. This file logs these cases\n\n");
	$overallBalanceSql = $pdo->query("SELECT SUM(balance) AS totalsum FROM employees");
	$overallBalance = $overallBalanceSql->fetch();
	$overallBalance = $overallBalance['totalsum'];
	fwrite($fp, "The overall balance is $overallBalance euros. \n\n");

	$empCountSql = $pdo->query("SELECT COUNT(employees) as totalcount FROM employees");
	$empCount = $empCountSql->fetch();
	$empCount = $empCount['totalcount'];

	if($overallBalance > 0)
	{
		//first get the amount of employees
		//$empsQuery = $empsSql->fetch();
		//$emps = $emps['employees'];
	
		//$emps = array();
		/*foreach($empsSql as $emp)
		{
			$pushMe = $emp['employees'];
			array_push( $emps, $pushMe); 
		}*/
		while($overallBalance > 0)
		{
			// get current list of employees
			$empsSql = $pdo->query("SELECT * FROM employees");
			$emps = $empsSql->fetchAll();
			// remove from each employee 1 cent until the following condition is not fulfilled any more
			fwrite($fp, "LOOP: The overall balance is $overallBalance and the number of employees is $empCount \n");
			if($empCount < $overallBalance*100)
			{
				fwrite($fp, "The rounding error was large enough to remove 1 cent from each employee. This should usually not happen! \n");
				foreach($emps as $emp)
				{
					$currentEmp = $emp['employees'];
					$currentBalance = $emp['balance'];
					// remove 1 cent
					$newBalance = $currentBalance-0.01;

					$sql = "UPDATE employees SET balance = ? WHERE employees = ?";
					$updateSql = $pdo->prepare($sql);
					$updateSql->execute([$newBalance, $currentEmp]);
					fwrite($fp, "$currentEmp now has a balance of $newBalance as compared to $currentBalance \n");
				}
			}
			// use random function
			else
			{	
				//choose random employee
				$empCountTemp = $empCount-1;
				$rndm = rand(0, $empCountTemp);
				$rndEmp = $emps[$rndm]['employees'];
				//get current balance
				$currentBalance = $emps[$rndm]['balance'];
				$newBalance = $currentBalance-0.01;
				
				$sql = "UPDATE employees SET balance = ? WHERE employees = ?";
				$updateSql = $pdo->prepare($sql);
				$updateSql->execute([$newBalance, $rndEmp]);
				fwrite($fp, "random employee chosen: $rndEmp with current balance of $currentBalance. New balance: $newBalance \n");
			}
			// get current balance
			$overallBalanceSql = $pdo->query("SELECT SUM(balance) AS totalsum FROM employees");
			$overallBalance = $overallBalanceSql->fetch();
			$overallBalance = $overallBalance['totalsum'];
		}
			// give 1 cent to each employee
		}
		else if($overallBalance < 0)
		{
			while($overallBalance < 0)
			{
				// get current list of employees
				$empsSql = $pdo->query("SELECT * FROM employees");
				$emps = $empsSql->fetchAll();
				// remove from each employee 1 cent until the following condition is not fulfilled any more
				if($empCount < -$overallBalance*100)
				{
					fwrite($fp, "The rounding error was large enough to give 1 cent to each employee. This should usually not happen! \n");
					foreach($emps as $emp)
					{
						$currentEmp = $emp['employees'];
						$currentBalance = $emp['balance'];
						// remove 1 cent
						$newBalance = $currentBalance+0.01;

						$sql = "UPDATE employees SET balance = ? WHERE employees = ?";
						$updateSql = $pdo->prepare($sql);
						$updateSql->execute([$newBalance, $currentEmp]);
						fwrite($fp, "$currentEmp now has a balance of $newBalance as compared to $currentBalance \n");
					}
				}
				// use random function
				else
				{	
					//choose random employee
					$empCountTemp = $empCount-1;
					$rndm = rand(0, $empCountTemp);
					$rndEmp = $emps[$rndm]['employees'];
					//get current balance
					$currentBalance = $emps[$rndm]['balance'];
					$newBalance = $currentBalance+0.01;
					
					$sql = "UPDATE employees SET balance = ? WHERE employees = ?";
					$updateSql = $pdo->prepare($sql);
					$updateSql->execute([$newBalance, $rndEmp]);
					fwrite($fp, "random employee chosen: $rndEmp with current balance of $currentBalance. New balance: $newBalance \n");
				}
				// get current balance
				$overallBalanceSql = $pdo->query("SELECT SUM(balance) AS totalsum FROM employees");
				$overallBalance = $overallBalanceSql->fetch();
				$overallBalance = $overallBalance['totalsum'];
			}
			fclose($fp);
		}
	return;

}
// return in form of 01 to 12
function getLastMonth(){
	if(date('m') === "01")
	{
		$lastMonth = 12;
	}
	else
	{
		$lastMonth = date('m', strtotime('-1 month'));
	}
	return $lastMonth;
}

function getProperYear($lastMonth){
	if($lastMonth == 12)
	{
		$year = date('Y', strtotime("-1 year"));
	}
	else
	{
		$year = date('Y');	
	}
	return $year;
}



$configFileName = "mysql.conf";
$configFile=fopen($configFileName, 'r');

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
	
$lastMonth = getLastMonth();
$year = getProperYear($lastMonth);
echo "last month was $lastMonth and thus the proper year is $year \n";


$dsn = "mysql:host=$server;dbname=$dbName";
try{
	$pdo = new PDO($dsn, $user, $password);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch (PDOException $e){
	throw new PDOException($e->getMessage(), (int)$e->getCode());
}

/* First Get All Employees */
// again no prepared statements required as only SQL columns are read and php dates are used.
// exception are employee names, example: O'Hara

//get all employees
$emps = $pdo->query("SELECT * FROM employees");

//backup current entries
//also create a log file
$dir = getcwd();
$bkdir = $dir."/backup/";
$logdir = $dir."/log/"; 

mkdir("backup");
mkdir("log");

$fp = fopen($bkdir.date("Y-m-d_H:i:s").".bk", 'w');
$logfile = fopen($logdir.date("Y-m-d_H:i:s").".log", 'w');

//get the full sum of prices
$pQuery = $pdo->query("SELECT SUM(prices) AS totalsum FROM purchases WHERE MONTH(dates) = 09");
$fullPriceSql = $pQuery->fetch();
$fullPrice = $fullPriceSql['totalsum'];

//get how many people have eaten this month
$sql = "SELECT COUNT(consumers) AS countEaten FROM consumers WHERE hasEaten = ? AND MONTH(date) = ?";
$eatenSql = $pdo->prepare($sql);
$eatenSql->execute(["true", $lastMonth+1]);
$eatenCount = $eatenSql->fetch();
$eatenCount = $eatenCount['countEaten'];

$pricePerMeal = $fullPrice/$eatenCount;
fwrite($logfile, "It was $eatenCount times eaten this month with a total cost of $fullPrice giving a price per meal of $pricePerMeal \n\n");

// collect what all employees have paid
foreach($emps as $emp)
{	
	// backup immediately so if anything goes wrong the old values can be restored
	fwrite($fp, $emp['employees']." - ".$emp['active']." - ".$emp['balance']."\n");
	
	$currentEmp = $emp['employees'];

	// transactions between 2 employees are calculated immediately so only choose rows where "receivers = none"
	$sql = "SELECT sum(prices) AS sumPerEmp FROM purchases WHERE buyers = ? AND receivers = ?";
	$balanceChangePerEmpSql = $pdo->prepare($sql);
	$balanceChangePerEmpSql->execute([$currentEmp, "none"]);
	$balanceChangePerEmp = $balanceChangePerEmpSql->fetch();
	//+1-1 is a trick to make pdo display a 0 instead of an empty line
	$balanceChangePerEmp = $balanceChangePerEmp['sumPerEmp']+1-1;

	//get the balance of current employee
	$balance = $emp['balance'];

	// get who has eaten when
	$sql = "SELECT COUNT(consumers) AS countEaten FROM consumers WHERE consumers = ? AND hasEaten = ?";
	$eatenSql = $pdo->prepare($sql);
	$eatenSql->execute([$currentEmp, "true"]);
	$hasEatenCount = $eatenSql->fetch();
	$hasEatenCount = $hasEatenCount['countEaten'];

	// calculate the new balance
	$newBalance = $balance + $balanceChangePerEmp - ($hasEatenCount*$pricePerMeal);

	// insert new balance into table
	$sql = "UPDATE employees SET balance = ? WHERE employees = ?";
	$updateSql = $pdo->prepare($sql);
	$updateSql->execute([$newBalance, $currentEmp]);

	fwrite($logfile, "Employee ".$emp['employees']." currently has a balance of ".$emp['balance']." \n");
	fwrite($logfile, "$currentEmp has paid this month $balanceChangePerEmp euros and has eaten for ".$hasEatenCount*$pricePerMeal." euros. \n");
	fwrite($logfile, "The new balance for $currentEmp is $newBalance euros\ncalculation: $balance + $balanceChangePerEmp - ($hasEatenCount*$pricePerMeal\n\n");
}
fclose($fp);
fclose($logfile);

correctOverallBalance($pdo);

?>
