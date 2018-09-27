<?php

function init_socket($strIPorDOMAIN, $host, $port){

	if($strIPorDOMAIN === "IP")
	{
		$ip = $host;
	}
	else if($strIPorDOMAIN === "DOMAIN")
	{
		$ip = gethostbyname($host);
	}
	else
	{
		die("in serverScriptBuyingApp.php you have to either specify \"IP\" or \"DOMAIN\" \n");
	}

	/* Create Socket */
	if(!($sock = socket_create(AF_INET, SOCK_STREAM, 0)))
	{
		$errorcode = socket_last_error();
		$errormsg = socket_strerror($errorcode);
		die("Could not create socket: [$errorcode] $errormsg \n");
	}

	/* Bind To Previously Specified IP and Port */
	if(!(socket_bind($sock, $ip, $port)))
	{

		$errorcode = socket_last_error();
		$errormsg = socket_strerror($errorcode);
		die("Could not bind socket: [$errorcode] $errormsg \n");
	}

	/* Set Socket To Listen */
	if(!socket_listen($sock, 10))
	{
		$errorcode = socket_last_error();
		$errormsg = socket_strerror($errorcode);
		die("Could not listen on socket: [$errorcode] $errormsg \n");
	}
	echo "Socket is now listening on IP $ip and port $port \n";
	return $sock;
}

function acceptClient($sock){
	echo "Awaiting new client connection \n";
	$client = socket_accept($sock);
	return $client;
}

function receiveMessage($client){
	     
	//display information about the client who is connected
	if(socket_getpeername($client , $address , $port))
	{
	    echo "Client $address : $port is now connected to us. \n";
	}
	echo "start reading \n";

	if(false === ($msg = socket_read($client, 2048, PHP_NORMAL_READ)))
	{
		echo "error when reading \n";
		return "readingError";
	}
	echo "received a message: ";
	echo "$msg \n";
	return $msg;
}

function sendMessage($client, $message){
	if( ! socket_send ( $client , $message."\n" , strlen($message)+1 , 0))
	{
	    $errorcode = socket_last_error();
	    $errormsg = socket_strerror($errorcode);
	     
	    die("Could not send data: [$errorcode] $errormsg \n");
	}	
	echo "sent message: $message \n";
}
?>
