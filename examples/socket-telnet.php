<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/5/9
 * Time: 下午11:55
 */

//$host = 'gearman';
$host = '127.0.0.1';
$port = 4730;

/* Create a TCP/IP socket. */
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
    echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
} else {
    echo "OK.\n";
}

echo "Attempting to connect to '$host' on port '$port'...";
$result = socket_connect($socket, $host, $port);

if ($result === false) {
    echo "socket_connect() failed.\nReason: ($result) " . socket_strerror(socket_last_error($socket)) . "\n";
} else {
    echo "OK.\n";
}

$cmd = "status\r\n";

//socket_write($socket, $cmd, strlen($cmd));
//$buffer = socket_read($socket, 1024, PHP_NORMAL_READ);
//echo $buffer;

echo "Sending request...";
socket_write($socket, $cmd, strlen($cmd));
echo "OK.\n";

echo "Reading response:\n\n";
while ($out = socket_read($socket, 2048)) {
    echo $out;
}

$out = socket_read($socket, 2048);
echo $out;


echo "Closing socket...";
socket_close($socket);
echo "OK.\n\n";