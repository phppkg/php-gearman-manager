# gearman manager

a php gearman workers manager.

Learning reference the project [brianlmoon/GearmanManager](https://github.com/brianlmoon/GearmanManager), Thank you very much for this project.

add some feature:

- Code is easier to read and understand
- Can support `reload` `restart` `stop` command

> only support linux system, and require enable `pcntl` `posix` 

### config 

there are some config 

```php 
    // run in the background
    'daemon' => false,

    // need 4 worker do all jobs
    'worker_num' => 4,

    // Workers will only live for 1 hour, after will auto restart.
    'max_lifetime' => 3600,
    // now, max_lifetime is >= 3600 and <= 4200
    'restart_splay' => 600,
    // max run 2000 job of each worker, after will auto restart.
    'max_run_jobs' => 2000,
```

## usage 

### entry script

- file: `gwm.php`

```php
use \inhere\gearman\Manager;

$config = [
    'daemon' => false,
    'pid_file' => __DIR__ . '/manager.pid',

    'log_level' => Manager::LOG_DEBUG,
    'log_file' => __DIR__ . '/workers.log',

    'loader_file' => __DIR__ . '/job_handlers.php',
];

$mgr = new Manager($config);

$mgr->setHandlersLoader(function (Manager $mgr)
{
    require __DIR__ . '/job_handlers.php';
});

$mgr->start();
```

### tool commands

- start

```bash
// start
php bin/manager.php 
// run as daemon
php bin/manager.php --daemon 
```

- stop 

```bash 
php bin/manager.php stop
```

- restart

```bash
php bin/manager.php restart
```

- other

```bash
// see help info
php bin/manager.php --help

// print manager config info
php bin/manager.php -D
```

### add handler

you can add job handler use: `function` `Closure` `Class/Object`

> class or object must be is a class implement the `__invoke()` or a class implement the interface `inhere\gearman\jobs\JobInterface`

- file: `job_handlers.php`

```php

/**
 * a class implement the '__invoke()'
 */
class TestJob
{
    public function __invoke($workload, \GearmanJob $job)
    {
        echo "from TestJob, call by __invoke";
    }
}

// add job handlers

$mgr->addHandler('reverse_string', function ($string, \GearmanJob $job)
{
    $result = strrev($string);

    echo "Result: $result\n";

    return $result;
});

$mgr->addHandler('test_job', TestJob::class);

// use a class implement the interface `inhere\gearman\jobs\JobInterface`, add some option for the job.
$mgr->addHandler('echo_job', \inhere\gearman\jobs\EchoJob::class, [
    'worker_num' => 2,
    'focus_on' => 1,
]);
```

### start manager

use `php gwm.php -h` see more help information

run: `php gwm.php`

## License

BSD
