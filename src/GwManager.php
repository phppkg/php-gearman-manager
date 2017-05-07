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
use inhere\gearman\jobs\JobInterface;

/**
 * Class GwManager - gearman worker manager
 * @package inhere\gearman
 */
class GwManager extends WorkerManager
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
            $this->log("Adding a job server: $s", self::LOG_WORKER_INFO);

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
            $this->log("Adding job handler to worker, Name: $job Timeout: $timeout", self::LOG_WORKER_INFO);
            $gmWorker->addFunction($job, [$this, 'doJob'], null, $timeout);
        }

        $start = time();
        $maxRun = $this->config['max_run_jobs'];

        while (!$this->stopWork) {
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
                    $this->log('No received anything job.(sleep 5s)', self::LOG_CRAZY);
                    !$this->stopWork && sleep(5);
                    continue;
                }

                // if (!@$gmWorker->wait()) {
                if (!$gmWorker->wait()) {
                    // GearmanWorker was called with no connections.
                    if ($gmWorker->returnCode() === GEARMAN_NO_ACTIVE_FDS) { // code: 7
                        $this->log('We are not connected to any servers, so wait a bit before trying to reconnect.(sleep 5s)', self::LOG_CRAZY);
                        !$this->stopWork && sleep(5);
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
     * Validates the PECL compatible worker files/functions
     */
    protected function validateDriverWorkers()
    {
        if (!$this->handlers) {
            $this->log('No job handlers registered!');
            posix_kill($this->masterPid, SIGUSR1);
            $this->quit(self::CODE_NO_HANDLERS);
        }

        $gmWorker = new GearmanWorker();
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

            if (!$gmWorker->echo('test_server') && $gmWorker->returnCode() === GEARMAN_COULD_NOT_CONNECT) {
                $this->log("Failed connect to the server: $s", self::LOG_ERROR);
                $this->stopWork = true;

                // posix_kill($this->pid, SIGUSR2);
                posix_kill($this->masterPid, SIGUSR2);
                $this->quit(self::CODE_CONNECT_ERROR);
            }
        }

        unset($gmWorker);

        // Since we got here, all must be ok, send a CONTINUE
        $this->log("code watch is running. Sending SIGCONT(continue) to master(PID:{$this->masterPid}).", self::LOG_PROC_INFO);
        posix_kill($this->masterPid, SIGCONT);
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
