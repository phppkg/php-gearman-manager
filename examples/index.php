<?php

error_reporting(E_ALL | E_STRICT);
date_default_timezone_set('Asia/Shanghai');
require __DIR__ . '/simple-autoloader.php';

//$monitor = new \inhere\gearman\tools\Monitor([
//    'servers' => [
//        [
//            'name' => 'default',
//            'address' => '127.0.0.1:4730',
//        ]
//    ]
//]);

//print_r($monitor->getFunctionData());

function render_view($view, array $data)
{
    extract($data);

    require $view;
}

render_view(__DIR__ . '/views/monitor.html', [
//    'sInfo' => $monitor->getFunctionData(),
]);