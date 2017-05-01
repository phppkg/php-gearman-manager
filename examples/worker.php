<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/4/28
 * Time: 下午9:26
 */

use \inhere\gearman\WorkerManager;

require __DIR__ . '/simple-loader.php';

date_default_timezone_set('Asia/Shanghai');

$config = [
    'log_level' => WorkerManager::LOG_DEBUG,
    'log_file' => __DIR__ . '/workers.log',
    'pid_file' => __DIR__ . '/manager.pid',
];

$worker = new WorkerManager($config);

$worker->addHandler('reverse_string', function ($string, \GearmanJob $job)
{
    echo "Received job: " . $job->handle() . "\n";
    echo "Workload: $string\n";

    $result = strrev($string);

    echo "Result: $result\n";

    return $result;
});

$worker->start();