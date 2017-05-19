<?php

use \inhere\gearman\tools\Monitor;
use \inhere\gearman\tools\TelnetGmdServer;

error_reporting(E_ALL | E_STRICT);
date_default_timezone_set('Asia/Shanghai');
require dirname(__DIR__) . '/examples/simple-autoloader.php';

$monitor = new Monitor([
   'servers' => [
       [
           'name' => 'test',
           'address' => '192.168.101.2:4730',
       ],
       [
           'name' => 'product',
           'address' => '192.168.139.1:4730',
       ]
   ]
]);

$statusInfo = json_encode($monitor->getFunctionData());
$workersInfo = json_encode($monitor->getWorkersData());

function render_view($view, array $data)
{
    extract($data);

    require $view;
}

render_view(__DIR__ . '/views/monitor.html', [
   'servers' => json_encode($monitor->getServersData()),
   // 'statusFileds' => TelnetGmdServer::STATUS_FIELDS,
   'statusInfo' => $statusInfo,
   // 'workersFileds' => TelnetGmdServer::WORKERS_FIELDS,
   'workersInfo' => $workersInfo,
]);
