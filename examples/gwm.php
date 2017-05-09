<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/4/28
 * Time: 下午9:26
 * gearman worker manager
 */

use \inhere\gearman\Manager;

error_reporting(E_ALL | E_STRICT);

require __DIR__ . '/simple-loader.php';

date_default_timezone_set('Asia/Shanghai');

$config = [
    'daemon' => false,
    'pid_file' => __DIR__ . '/manager.pid',

    'log_level' => Manager::LOG_DEBUG,
    'log_file' => __DIR__ . '/logs/manager.log',

    'loader_file' => __DIR__ . '/job_handlers.php',
];

$mgr = new Manager($config);

$mgr->setHandlersLoader(function (Manager $mgr)
{
    require __DIR__ . '/job_handlers.php';
});

$mgr->start();
