<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/4/28
 * Time: 下午9:26
 * gearman worker manager
 */

error_reporting(E_ALL | E_STRICT);

require __DIR__ . '/simple-loader.php';

date_default_timezone_set('Asia/Shanghai');

$config = [
    'name' => 'test-lite',
    'daemon' => false,
    'pid_file' => __DIR__ . '/lite-manager.pid',

    'log_level' => \inhere\gearman\LiteManager::LOG_DEBUG,
    'log_file' => __DIR__ . '/logs/lite-manager.log',
];

$mgr = new \inhere\gearman\LiteManager($config);

require __DIR__ . '/job_handlers.php';

$mgr->start();
