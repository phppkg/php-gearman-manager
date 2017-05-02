<?php
/**
 * job callbacks
 * @var \inhere\gearman\WorkerManager $worker
 */

$worker->addHandler('reverse_string', function ($string, \GearmanJob $job)
{
    $result = strrev($string);

    echo "Result: $result\n";

    return $result;
});
