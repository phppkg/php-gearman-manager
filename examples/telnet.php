<?php

error_reporting(E_ALL | E_STRICT);

require __DIR__ . '/simple-loader.php';

date_default_timezone_set('Asia/Shanghai');

global $argv;
$opts = getopt('h', ['help']);

if (isset($opts['h']) || isset($opts['help'])) {
    $script = array_shift($argv);
    $help = <<<EOF
Start a telnet client.

Usage:
  $script HOST [PORT]

Options:
  -h,--help  Show this help information
\n
EOF;
    exit($help);
}

$host = isset($argv[1]) ? $argv[1] : '127.0.0.1';
$port = isset($argv[2]) ? $argv[2] : 80;

$tt = new \inhere\gearman\tools\Telnet($host, $port);

echo $tt->command('status');
