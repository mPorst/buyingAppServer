<?php
/* Set Up Mandatory DB Connection */
function removeNewLine(&$inputString) {
	$inputString = strtok($inputString, "\n");
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
	

$dsn = "mysql:host=$server;dbname=$dbName";
try{
	$pdo = new PDO($dsn, $user, $password);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch (PDOException $e){
	throw new PDOException($e->getMessage(), (int)$e->getCode());
}

/* First Get All Employees */
$sth = $pdo->query("SELECT employees FROM employees");
foreach($sth as $emp)
{	
	echo "employee ".$emp['employees']." added for calculation \n";
}
?>
