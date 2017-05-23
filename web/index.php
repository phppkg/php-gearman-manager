<?php
/**
 * php -S 127.0.0.1:5888 -t web
 */

use \inhere\gearman\tools\WebPanelHandler;

error_reporting(E_ALL | E_STRICT);
date_default_timezone_set('Asia/Shanghai');
define('ROOT_PATH', dirname(__DIR__));

require dirname(__DIR__) . '/examples/simple-autoloader.php';

$wph = new WebPanelHandler([
    'basePath' => __DIR__,
    'logPath' => dirname(__DIR__) . '/examples/logs/',
    'logFileName' => 'manager_%s.log',
]);

$wph->setRoutes([
    'home' => 'index',
    'server-info' => 'serverInfo',
    'log-info' => 'logInfo',
]);

$route = $wph->get('r');
// $route = $wph->getServerValue('REQUEST_URI');
// var_dump($route, $_SERVER);
$wph->dispatch($route);


