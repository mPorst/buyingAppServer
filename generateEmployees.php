#!/usr/bin/php

<?php
/*
/
/ script for generating the employees.conf file
/
/
*/

include_once "mysqlBackend.php";

$configFileName = "mysql.conf";

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

/* Setup Database Connection And Initial Entries Using Previously Read Configs */
$pdo = pdoDbSetup($user, $server, $password, $dbName);

$fd = fopen("generatedEmployees.conf", 'w');
$employeesSql = $pdo->query("SELECT * FROM employees");
$employees = $employeesSql->fetchAll();
foreach($employees as $emp)
{
	fwrite($fd, $emp['employees']."\n".$emp['active']."\n".$emp['balance']."\n");
}
fclose($fd);

?>
