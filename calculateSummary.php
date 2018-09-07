<?php
/* Set Up Mandatory DB Connection */
function removeNewLine(&$inputString) {
	$inputString = strtok($inputString, "\n");
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

//get the full sum of prices
$pQuery = $pdo->query("SELECT SUM(prices) as totalsum FROM purchases WHERE MONTH(dates) = 09");
$fullPriceSql = $pQuery->fetch(PDO::FETCH_ASSOC);
$fullPrice = $fullPriceSql['totalsum'];
echo $fullPrice;

foreach($emps as $emp)
{	
	echo "Employee ".$emp['employees']." currently has a balance of ".$emp['balance']." for calculation \n";
	$currentEmp = $emp['employees'];
	$sql = "SELECT sum(prices) as sumPerEmp FROM purchases WHERE buyers = ?";
	$newBalancePerEmpSql = $pdo->prepare($sql);
	$newBalancePerEmpSql->execute([$currentEmp]);
	$newBalancePerEmp = $newBalancePerEmpSql->fetch(PDO::FETCH_ASSOC);
	//+1-1 is a trick to make pdo display a 0 instead of an empty line
	$newBalancePerEmp = $newBalancePerEmp['sumPerEmp']+1-1;
	echo "New balance: $newBalancePerEmp \n";
}
?>
