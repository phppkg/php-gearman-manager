<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-04-27
 * Time: 16:06
 */

namespace inhere\gearman\jobs;

use inhere\gearman\ManagerInterface;

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
     * @var ManagerInterface
     */
    protected $manager;

    /**
     * do the job
     * @param string $workload
     * @param \GearmanJob $job
     * @param ManagerInterface $manager
     * @return mixed
     */
    public function run($workload, \GearmanJob $job, ManagerInterface $manager)
    {
        $this->manager = $manager;

        $this->beforeRun();

        $ret = $this->doRun($workload, $job);

        $this->afterRun();

        return $ret;
    }

    /**
     * beforeRun
     */
    protected function beforeRun()
    {
    }

    /**
     * doRun
     * @param $workload
     * @param \GearmanJob $job
     * @return mixed
     */
    abstract protected function doRun($workload, \GearmanJob $job);

    /**
     * afterRun
     */
    protected function afterRun()
    {
    }

    /**
     * @param mixed $context
     */
    public function setContext($context)
    {
        $this->context = $context;
    }
}
