<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-04-27
 * Time: 16:06
 */

namespace inhere\gearman\jobs;

/**
 * Class Job
 * @package inhere\gearman\jobs
 */
abstract class Job implements JobInterface
{
    /**
     * @var mixed
     */
    protected $context;

    /**
     * do the job
     * @param string $workload
     * @param \GearmanJob $job
     * @return mixed
     */
    abstract public function run($workload, \GearmanJob $job);

    /**
     * @param mixed $context
     */
    public function setContext($context)
    {
        $this->context = $context;
    }
}
