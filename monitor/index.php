<?php

use inhere\gearman\Helper;
use \inhere\gearman\tools\Monitor;
use \inhere\gearman\tools\TelnetGmdServer;

error_reporting(E_ALL | E_STRICT);
date_default_timezone_set('Asia/Shanghai');
define('ROOT_PATH', dirname(__DIR__));

require dirname(__DIR__) . '/examples/simple-autoloader.php';

if (!Helper::isAjax()) {
    Helper::render(__DIR__ . '/views/index.html', [

    ]);
}

$route = Helper::get('r');

switch ($route) {
    case 'log-info':
        get_log_info();
        break;

    default:
        get_monitor_info();
        break;
}

function get_monitor_info()
{
    $servers = Helper::get('servers', []);

    if (!$servers) {

    }

    $monitor = new Monitor([
        'servers' => $servers,
//       'servers' => [
//           [
//               'name' => 'test',
//               'address' => '10.0.0.2:4730',
//           ],
//           [
//               'name' => 'product',
//               'address' => '10.0.0.1:4730',
//           ]
//       ]
    ]);

    Helper::outJson([
       'servers' => $monitor->getServersData(),
       'statusInfo' => $monitor->getFunctionData(),
       'workersInfo' => $monitor->getWorkersData(),
    ]);
}

function get_log_info()
{
    $date = Helper::get('date', date('Y-m-d'));
    $type = Helper::get('type', 'started');

    // $name = 'manager.log';
    $realName = sprintf('manager_%s.log', '2017-05-21');
    $file = ROOT_PATH . '/examples/logs/' . $realName;

    $lp = new \inhere\gearman\tools\LogParser($file);

    var_dump($lp->getWorkerStartTimes(),$lp->getInfo($type));
}


