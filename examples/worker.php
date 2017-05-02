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
    'daemon' => false,
    'pid_file' => __DIR__ . '/manager.pid',

    'log_level' => WorkerManager::LOG_DEBUG,
    'log_file' => __DIR__ . '/workers.log',

    'loader_file' => __DIR__ . '/job_handlers.php',
];

$worker = new WorkerManager($config);

require __DIR__ . '/job_handlers.php';

$worker->start();
