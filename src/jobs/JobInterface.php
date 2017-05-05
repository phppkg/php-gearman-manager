<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-04-27
 * Time: 16:06
 */

namespace inhere\gearman\jobs;

use inhere\gearman\WorkerManager;

/**
 * Class JobInterface
 * @package inhere\gearman\jobs
 */
interface JobInterface
{
    /**
     * do the job
     * @param string $workload
     * @param \GearmanJob $job
     * @param WorkerManager $manager
     * @return mixed
     */
    public function run($workload, \GearmanJob $job, WorkerManager $manager);
}
