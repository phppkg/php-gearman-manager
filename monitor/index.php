<?php

use \inhere\gearman\tools\Monitor;
use \inhere\gearman\tools\TelnetGmdServer;

error_reporting(E_ALL | E_STRICT);
date_default_timezone_set('Asia/Shanghai');
define('ROOT_PATH', dirname(__DIR__));

require dirname(__DIR__) . '/examples/simple-autoloader.php';

get_log_info();die;

$route = isset($_GET['r']) ? $_GET['r']: '';

if ($route === 'log-info') {
    # code...
} else {
    show_monitor_info();
}

function show_monitor_info()
{
    $monitor = new Monitor([
       'servers' => [
           [
               'name' => 'test',
               'address' => '10.0.0.2:4730',
           ],
           [
               'name' => 'product',
               'address' => '10.0.0.1:4730',
           ]
       ]
    ]);

    $statusInfo = json_encode($monitor->getFunctionData());
    $workersInfo = json_encode($monitor->getWorkersData());

    render_view(__DIR__ . '/views/monitor.html', [
       'servers' => json_encode($monitor->getServersData()),
       'statusInfo' => $statusInfo,
       'workersInfo' => $workersInfo,
    ]);
}

function get_log_info($date = '', $key = 'started')
{
    // $name = 'manager.log';
    $realName = sprintf('manager_%s.log', $date ? : date('Y-m-d'));
    $file = ROOT_PATH . '/examples/logs/' . $realName;

    if (!is_file($file)) {
        out_json([], -200, 'log file not exists!');
    }

    // started jobs
    exec("cat $file | grep 'Starting job'", $result);

    // completed jobs
    exec("cat $file | grep ' completed'", $result);

    // Failed jobs
    exec("cat $file | grep 'Failed'", $result);

    var_dump($result);

    // out_json([
    // ]);
}

function render_view($view, array $data = [])
{
    extract($data);

    require $view;
}

function out_json(array $data = [], $code = 0, $msg = 'successful')
{
    if (!headers_sent()) {
        header("Content-type: application/json;charset=utf-8");
    }

    exit(json_encode([
        'code' => (int)$code,
        'msg' => $msg,
        'data' => $data,
    ]));
}

function parse_log($logs)
{
    if (!$logs) {
        return null;
    }

    $data = [];
    foreach ($logs as $log) {
        $info = explode('] ', $log);
        list($role, $pid) = explode(':', $info[1]);
        $data[] = [
            'time' => $info[0],
            'role' => $role,
            'pid' => $pid,
            'level' => $info[2],
        ];
    }


}
