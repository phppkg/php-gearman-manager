<?php
/**
 * job callbacks
 * @var \inhere\gearman\WorkerManager $mgr
 */

/**
 * a class implement the '__invoke()'
 */
class TestJob
{
    public function __invoke($workload, \GearmanJob $job)
    {
        # code...
    }
}

$mgr->addHandler('reverse_string', function ($string, \GearmanJob $job)
{
    $result = strrev($string);

    echo "Result: $result\n";

    return $result;
});

$mgr->addHandler('test_echo', function ($str) {
   echo $str;
});

$mgr->addHandler('echo_job', \inhere\gearman\jobs\EchoJob::class, [
    'worker_num' => 2,
    'focus_on' => 1,
]);
