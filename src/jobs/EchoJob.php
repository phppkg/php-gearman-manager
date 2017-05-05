<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/5/4
 * Time: 下午8:54
 */

namespace inhere\gearman\jobs;

/**
 * Class EchoJob
 * @package inhere\gearman\jobs
 */
class EchoJob extends Job
{
    /**
     * {@inheritDoc}
     */
    protected function doRun($workload, \GearmanJob $job)
    {
        echo "receive: $workload";
    }
}