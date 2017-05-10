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
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

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
        $result = false;
        $this->id = $job->handle();
        $this->name = $job->functionName();
        $this->manager = $manager;

        if (false !== $this->beforeRun($workload, $job)) {
            $result = $this->doRun($workload, $job);

            $this->afterRun();
        }

        return $result;
    }

    /**
     * beforeRun
     * @param $workload
     * @param \GearmanJob $job
     */
    protected function beforeRun($workload, \GearmanJob $job)
    {}

    /**
     * doRun
     * @param $workload
     * @param \GearmanJob $job
     * @return mixed
     */
    abstract protected function doRun($workload, \GearmanJob $job);


    /**
     * afterRun
     * @param mixed $result
     */
    protected function afterRun($result)
    {}

    /**
     * @param mixed $context
     */
    public function setContext($context)
    {
        $this->context = $context;
    }
}
