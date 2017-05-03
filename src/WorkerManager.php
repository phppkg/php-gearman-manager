<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-04-28
 * Time: 17:03
 */

namespace inhere\gearman;

use GearmanJob;
use GearmanWorker;

/**
 * Class WorkerManager
 * @package inhere\gearman
 */
class WorkerManager extends ManagerAbstracter
{
    /**
     * Starts a worker for the PECL library
     *
     * @param   array $jobs List of worker functions to add
     * @param   array $timeouts list of worker timeouts to pass to server
     * @return  int The exit status code
     * @throws \GearmanException
     */
    protected function startDriverWorker(array $jobs, array $timeouts = [])
    {
        $gmWorker = new GearmanWorker();
        // 设置非阻塞式运行
        $gmWorker->addOptions(GEARMAN_WORKER_NON_BLOCKING);
        $gmWorker->setTimeout(5000);

        $this->debug("The Gearman worker started(PID:{$this->pid})");

        foreach ($this->getServers() as $s) {
            $this->log("Adding server $s", self::LOG_WORKER_INFO);

            // see: https://bugs.php.net/bug.php?id=63041
            try {
                $gmWorker->addServers($s);
            } catch (\GearmanException $e) {
                if ($e->getMessage() !== 'Failed to set exception option') {
                    $this->stopWork = true;
                    throw $e;
                }
            }

            // test server
            if (!@$gmWorker->echo('echo')) {
                $this->log("Failed connect to the server: $s");
                $this->stopWork = true;

                return self::CODE_CONNECT_ERROR;
            }
        }

        foreach ($jobs as $job) {
            $timeout = $timeouts[$job] >= 0 ? $timeouts[$job] : 0;
            $this->log("Adding job handler to worker, Name: $job Timeout: $timeout", self::LOG_WORKER_INFO);
            $gmWorker->addFunction($job, [$this, 'doJob'], null, $timeout);
        }

        $start = time();
        $maxRun = $this->maxRunJobs;

        while (!$this->stopWork) {
            if (
                @$gmWorker->work() ||
                $gmWorker->returnCode() === GEARMAN_IO_WAIT ||
                $gmWorker->returnCode() === GEARMAN_NO_JOBS
            ) {
                if ($gmWorker->returnCode() === GEARMAN_SUCCESS) {
                    continue;
                }

                $this->log('Waiting for next job...', self::LOG_CRAZY);

                if (!@$gmWorker->wait()) {
                    // no received anything jobs. sleep 5 seconds
                    if ($gmWorker->returnCode() === GEARMAN_NO_ACTIVE_FDS) {
                        sleep(5);
                        continue;
                    }

                    break;
                }
            }

            $runtime = time() - $start;

            // Check the worker running time of the current child. If it has been too long, stop working.
            if ($this->maxLifetime > 0 && ($runtime > $this->maxLifetime)) {
                $this->log("Worker have been running too long time({$runtime}s), exiting", self::LOG_WORKER_INFO);
                $this->stopWork = true;
            }

            if ($this->jobExecCount >= $maxRun) {
                $this->log("Ran $this->jobExecCount jobs which is over the maximum($maxRun), exiting and restart", self::LOG_WORKER_INFO);
                $this->stopWork = true;
            }
        }

        return $gmWorker->unregisterAll() ? 0 : -1;
    }

    /**
     * Validates the PECL compatible worker files/functions
     */
    protected function validateDriverWorkers()
    {
        $gmWorker = new GearmanWorker();

        // 设置非阻塞式运行
        $gmWorker->addOptions(GEARMAN_WORKER_NON_BLOCKING);
        $gmWorker->setTimeout(5000);

        foreach ($this->getServers() as $s) {
            $this->log("Testing adding server: $s", self::LOG_WORKER_INFO);

            // see: https://bugs.php.net/bug.php?id=63041
            try {
                $gmWorker->addServers($s);
            } catch (\GearmanException $e) {
                if ($e->getMessage() !== 'Failed to set exception option') {
                    $this->stopWork = true;
                    throw $e;
                }
            }

            // test server
            if (!$gmWorker->echo('echo')) {
                $this->log("Failed connect to the server: $s", self::LOG_ERROR);
                $this->stopWork = true;

                // posix_kill($this->pid, SIGUSR2);
                posix_kill($this->masterPid, SIGUSR2);

                $this->quit(self::CODE_CONNECT_ERROR);
            }
        }
    }

    /**
     * Wrapper function handler for all registered functions
     * This allows us to do some nice logging when jobs are started/finished
     * @param GearmanJob $job
     * @return bool
     */
    public function doJob($job)
    {
        $h = $job->handle();
        $wl = $job->workload();
        $name = $job->functionName();

        if (!$handler = $this->getHandler($name)) {
            $this->log("($h) Unknown job, The job name $name is not registered.", self::LOG_ERROR);
            return false;
        }

        $e = $ret = null;

        $this->log("($h) Starting Job: $name", self::LOG_WORKER_INFO);
        $this->log("($h) Job Workload: $wl", self::LOG_DEBUG);
        $this->trigger(self::EVENT_BEFORE_WORK, [$job]);

        // Run the job handler here
        try {
            if ($handler instanceof JobInterface) {
                $jobClass = get_class($handler);
                $this->log("($h) Calling: Calling Job object ($jobClass) for $name.", self::LOG_DEBUG);
                $ret = $handler->run($job->workload(), $this, $job);
            } else {
                $jobFunc = is_string($handler) ? $handler : 'Closure';
                $this->log("($h) Calling: Calling function ($jobFunc) for $name.", self::LOG_DEBUG);
                $ret = $handler($job->workload(), $this, $job);
            }
        } catch (\Exception $e) {
            $this->log("($h) Failed: failed to handle job for $name. Msg: " . $e->getMessage(), self::LOG_ERROR);
            $this->trigger(self::EVENT_AFTER_ERROR, [$job, $e]);
        }

        $this->jobExecCount++;

        if (!$e) {
            $this->log("($h) Completed Job: $name", self::LOG_WORKER_INFO);
            $this->trigger(self::EVENT_AFTER_WORK, [$job, $ret]);
        }

        return $ret;
    }
}
