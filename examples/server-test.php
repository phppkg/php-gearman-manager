<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/5/9
 * Time: 下午10:40
 */
//$host = 'gearman';
$host = '127.0.0.1';
$port = 4730;

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

    //echo fread($fp, 1024);
    while ($out = fread($fp, 2048)) {
        var_dump($out);
        echo $out;
    }


    fclose($fp);
}