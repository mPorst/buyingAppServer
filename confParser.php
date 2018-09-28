<?php


function parseConfig($configFileName)
{
	$cnf = fopen($configFileName, 'r');
	if($cnf == false)
		die("Could not open $configFileName");
	$parameters = array();
	while(!feof($cnf))
	{
		$line = fgets($cnf);
		removeNewLine($line);
		if($line[0] != '#')	
			array_push($parameters, $line);
	}
	return $parameters;
}

?>
