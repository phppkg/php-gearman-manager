<?php

error_reporting(E_ALL | E_STRICT);
date_default_timezone_set('Asia/Shanghai');
require __DIR__ . '/simple-autoloader.php';

$host = '192.168.101.2';
$port = 4730;
$tt = new \inhere\gearman\tools\TelnetGmdServer($host, $port);

print_r($tt->statusInfo());
print_r($tt->workersInfo());
