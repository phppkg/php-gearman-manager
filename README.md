# gearman manager

a php gearman workers manager.

referrer the project [brianlmoon/GearmanManager](https://github.com/brianlmoon/GearmanManager), Thank you very much for this project.

add some feature:

- Code is easier to read and understand
- Can support `reload` `restart` `stop` command

## usage 

### entry script

- file: `gwm.php`

```php
use \inhere\gearman\GwManager;

$config = [
    'daemon' => false,
    'pid_file' => __DIR__ . '/manager.pid',

    'log_level' => GwManager::LOG_DEBUG,
    'log_file' => __DIR__ . '/workers.log',

    'loader_file' => __DIR__ . '/job_handlers.php',
];

$mgr = new GwManager($config);

$mgr->setHandlersLoader(function (GwManager $mgr)
{
    require __DIR__ . '/job_handlers.php';
});

$mgr->start();
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
