<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/5/9
 * Time: 下午10:40
 */

$opts = getopt('h:p:');
$host = isset($opts['h']) ? $opts['h'] : '127.0.0.1';
$port = isset($opts['p']) ? $opts['p'] : '4730';

$fp = stream_socket_client(
    "tcp://{$host}:{$port}",
    $errNo,
    $errStr,
    10,
    STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT
);

if (!$fp) {
    echo "ERROR: $errNo - $errStr<br />\n";
} else {
    fwrite($fp, "status\r\n");

    echo "Reading response:\n\n";

    echo fread($fp, 1024);

    // while ($out = fread($fp, 2048)) {
    //     echo $out;
    // }

    echo "Closing\n";
    fclose($fp);
}
