<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/4/28
 * Time: 下午11:03
 */

declare(ticks = 1);
error_reporting(E_ALL | E_STRICT);

require __DIR__ . '/simple-loader.php';

date_default_timezone_set('Asia/Shanghai');

$opts = getopt('s:h', ['server:', 'help']);

if (isset($opts['h']) || isset($opts['help'])) {
    $script = array_shift($GLOBALS['argv']);
    $help = <<<EOF
Start a gearman client.

Usage:
  $script -s HOST[:PORT]

Options:
  -s,--server HOST[:PORT]  Connect to server HOST and optional PORT(default 127.0.0.1:4730)
  -h,--help                Show this help information
     --debug               Debug mode
     --dump                Dump all config data
\n
EOF;
    fwrite(\STDOUT, $help);
    exit(0);
}

$client = new \inhere\gearman\client\JobClient([
    'servers' => isset($opts['s']) ? $opts['s'] : (isset($opts['server']) ? $opts['server'] : ''),
]);

$ret[] = $client->doNormal('reverse_string', 'hello a');
$ret[] = $client->doBackground('reverse_string', 'hello b');
$ret[] = $client->doBackground('reverse_string', 'hello c');
$ret[] = $client->doHighBackground('reverse_string', 'hello d');

$ret[] = $client->doBackground('test_echo', 'hello welcome');

$ret[] = $client->doBackground('echo_job', 'hello welcome!!');

var_dump($ret);
