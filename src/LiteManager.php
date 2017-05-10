<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/5/9
 * Time: 下午8:06
 */

namespace inhere\gearman;

use GearmanJob;
use GearmanWorker;
use inhere\gearman\jobs\JobInterface;

/**
 * {@inheritDoc}
 */
class LiteManager extends BaseManager
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
        $wkrTimeout = 5;
        $gmWorker = new GearmanWorker();

        // 设置非阻塞式运行
        $gmWorker->addOptions(GEARMAN_WORKER_NON_BLOCKING);
        $gmWorker->setTimeout($wkrTimeout * 1000); // 5s

        $this->debug("The gearman worker started");

        foreach ($this->getServers() as $s) {
            $this->log("Adding a job server: $s", self::LOG_DEBUG);

            // see: https://bugs.php.net/bug.php?id=63041
            try {
                $gmWorker->addServers($s);
            } catch (\GearmanException $e) {
                if ($e->getMessage() !== 'Failed to set exception option') {
                    $this->stopWork = true;
                    throw $e;
                }
            }
        }

        foreach ($jobs as $job) {
            $timeout = $timeouts[$job] >= 0 ? $timeouts[$job] : 0;
            $this->log("Adding job handler to worker, Name: $job Timeout: $timeout", self::LOG_CRAZY);
            $gmWorker->addFunction($job, [$this, 'doJob'], null, $timeout);
        }

        $start = time();
        $maxRun = $this->config['max_run_jobs'];

        while (!$this->stopWork) {
            // receive and dispatch sig
            pcntl_signal_dispatch();

            if (
                @$gmWorker->work() ||
                $gmWorker->returnCode() === GEARMAN_IO_WAIT ||  // code: 1
                $gmWorker->returnCode() === GEARMAN_NO_JOBS     // code: 35
            ) {
                if ($gmWorker->returnCode() === GEARMAN_SUCCESS) { // code 0
                    continue;
                }

                // no received anything jobs. sleep 5 seconds
                if ($gmWorker->returnCode() === GEARMAN_NO_JOBS) {
                    if ($this->stopWork) {
                        break;
                    }
                    $this->log('No received anything job.(sleep 5s)', self::LOG_CRAZY);
                    sleep(5);
                    continue;
                }

                // if (!@$gmWorker->wait()) {
                if (!$gmWorker->wait()) {
                    // GearmanWorker was called with no connections.
                    if ($gmWorker->returnCode() === GEARMAN_NO_ACTIVE_FDS) { // code: 7
                        if ($this->stopWork) {
                            break;
                        }
                        $this->log('We are not connected to any servers, so wait a bit before trying to reconnect.(sleep 5s)', self::LOG_CRAZY);
                        sleep(5);
                        continue;
                    }

                    if ($gmWorker->returnCode() === GEARMAN_TIMEOUT) { // code: 47
                        $this->log("Timeout({$wkrTimeout}s). Waiting for next job...", self::LOG_CRAZY);
                        continue;
                    }

                    $this->log("Worker Error: {$gmWorker->error()}", self::LOG_DEBUG);
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
            $this->log("doJob: ($h) Unknown job, The job name $name is not registered.", self::LOG_ERROR);
            return false;
        }

        $e = $ret = null;

        $this->log("doJob: ($h) Starting job: $name", self::LOG_WORKER_INFO);
        $this->log("doJob: ($h) Job $name workload: $wl", self::LOG_DEBUG);
        $this->trigger(self::EVENT_BEFORE_WORK, [$job]);

        // Run the job handler here
        try {
            if ($handler instanceof JobInterface) {
                $jobClass = get_class($handler);
                $this->log("doJob: ($h) Calling Job object handler($jobClass) do the job: $name.", self::LOG_WORKER_INFO);
                $ret = $handler->run($job->workload(), $job, $this);
            } else {
                $jobFunc = is_string($handler) ? $handler : get_class($handler);
                $this->log("doJob: ($h) Calling function handler($jobFunc) do the job: $name.", self::LOG_WORKER_INFO);
                $ret = $handler($job->workload(), $job, $this);
            }
        } catch (\Exception $e) {
            $this->log("doJob: ($h) Failed to do the job: $name. Error: " . $e->getMessage(), self::LOG_ERROR);
            $this->trigger(self::EVENT_AFTER_ERROR, [$job, $e]);
        }

        $this->jobExecCount++;

        if (!$e) {
            $this->log("doJob: ($h) Completed Job: $name", self::LOG_WORKER_INFO);
            $this->trigger(self::EVENT_AFTER_WORK, [$job, $ret]);
        }

        return $ret;
    }
}
