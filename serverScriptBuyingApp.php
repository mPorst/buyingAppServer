#!/usr/bin/php
<?php
$ip = gethostbyname("palaven.de");
$port = 39978;

if(!($sock = socket_create(AF_INET, SOCK_STREAM, 0)))
{
	$errorcode = socket_last_error();
	$errormsg = socket_strerror($errorcode);
	die("Could not create socket: [$errorcode] $errormsg \n");
}

if(!(socket_bind($sock, $ip, $port)))
{
	$errorcode = socket_last_error();
	$errormsg = socket_strerror($errorcode);
	die("Could not bind socket: [$errorcode] $errormsg \n");
}
	echo "Bound socket to IP $ip and port $port \n";

if(!socket_listen($sock, 10))
{
	$errorcode = socket_last_error();
	$errormsg = socket_strerror($errorcode);
	die("Could not listen on socket: [$errorcode] $errormsg \n");
}
	echo "socket listening \n";

	// now start the actual server loop
	do{
	echo "Awaiting new client connection \n";
	$client = socket_accept($sock);
	     
	//display information about the client who is connected
	if(socket_getpeername($client , $address , $port))
	{
	    echo "Client $address : $port is now connected to us. \n";
	}
	echo "start reading \n";

	if(false === ($msg = socket_read($client, 2048, PHP_NORMAL_READ)))
	{
		die("error when reading \n");
	}
	echo "read a message: ";
	echo "$msg \n";

	$message = "This is a test sent from $ip \n";

	if( ! socket_send ( $client , $message , strlen($message) , 0))
	{
	    $errorcode = socket_last_error();
	    $errormsg = socket_strerror($errorcode);
	     
	    die("Could not send data: [$errorcode] $errormsg \n");
	}	
	echo "sent back message: $message";

	} while (true);
?>
