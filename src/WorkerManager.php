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
     * Bootstrap a set of workers and any vars that need to be set
     */
    protected function startWorkers()
    {
        $workersCount = [];
        $jobs = $this->getJobs();

        // If we have "doAllWorkers" workers, start them first do_all workers register all functions
        if (($num = $this->doAllWorkers) > 0) {
            for ($x = 0; $x < $num; $x++) {
                $this->startWorker();

                // Don't start workers too fast. They can overwhelm the gearmand server and lead to connection timeouts.
                usleep(500000);
            }

            foreach ($jobs as $job) {
                if (!$this->getJobOpt($job, 'dedicated', false)) {
                    $workersCount[$job] = $num;
                }
            }
        }

        // Next we loop the workers and ensure we have enough running for each worker
        foreach ($this->handlers as $job => $handler) {
            // If we don't have do_all workers, this won't be set, so we need to init it here
            if (!isset($workersCount[$job])) {
                $workersCount[$job] = 0;
            }

            $workerNum = (int)$this->getJobOpt($job, 'worker_num', 0);

            while ($workersCount[$job] < $workerNum) {
                $this->startWorker($job);

                $workersCount[$job]++;

                // Don't start workers too fast. They can overwhelm the gearmand server and lead to connection timeouts.
                usleep(500000);
            }
        }

        // Set the last code check time to now since we just loaded all the code
        $this->lastCheckTime = time();
    }

    /**
     * Begin monitor workers
     *  - will monitoring children process running status
     *
     * @notice run in the parent main process, children process will exited in the `startWorkers()`
     */
    protected function startWorkerMonitor()
    {
        $this->setProcessTitle("pgm: Master process");
        $this->log('Begin monitor check runtime status for children', self::LOG_DEBUG);

        // Main processing loop for the parent process
        while (!$this->stopWork || count($this->children)) {
            $status = null;

            // Check for exited children
            $exited = pcntl_wait($status, WNOHANG);

            // We run other children, make sure this is a worker
            if (isset($this->children[$exited])) {
                /*
                 * If they have exited, remove them from the children array
                 * If we are not stopping work, start another in its place
                 */
                if ($exited) {
                    $workerJobs = $this->children[$exited]['jobs'];
                    $code = pcntl_wexitstatus($status);
                    $exitStatus = $code === 0 ? 'exited' : $code;
                    unset($this->children[$exited]);

                    $this->logChildStatus($exited, $workerJobs, $exitStatus);

                    if (!$this->stopWork) {
                        $this->startWorker($workerJobs);
                    }
                }
            }

            if ($this->stopWork && time() - $this->stopTime > 60) {
                $this->log('Children have not exited, killing.', self::LOG_PROC_INFO);
                $this->stopChildren(SIGKILL);
            } else {
                // If any children have been running 150% of max run time, forcibly terminate them
                foreach ($this->children as $pid => $child) {
                    if (!empty($child['start_time']) && time() - $child['start_time'] > $this->maxLifetime * 1.5) {
                        $this->logChildStatus($pid, $child['jobs'], "killed");
                        Helper::killProcess($pid, SIGKILL);
                    }
                }
            }

            // php will eat up your cpu if you don't have this
            usleep(10000);
        }
    }

    /**
     * Start a worker do there are assign jobs.
     * If is in the parent, record child info.
     *
     * @param string|array $jobs Jobs for the current worker.
     */
    protected function startWorker($jobs = 'all')
    {
        $timeouts = [];
        $defTimeout = $this->get('timeout', 0);
        $jobs = is_string($jobs) && $jobs === self::DO_ALL ? $this->getJobs() : (array)$jobs;

        foreach ($jobs as $job) {
            $timeouts[$job] = (int)$this->getJobOpt($job, 'timeout', $defTimeout);
        }

        // fork process
        $pid = pcntl_fork();

        switch ($pid) {
            case 0: // at children
                $this->setProcessTitle("pgm: Worker process");

                $this->isParent = false;
                $this->parentPid = $this->pid;
                $this->pid = getmypid();
                $this->registerSignals(false);

                if (count($jobs) > 1) {
                    // shuffle the list to avoid queue preference
                    shuffle($jobs);
                }

                if (($splay = $this->get('restart_splay')) > 0) {
                    // Since all child threads use the same seed, we need to reseed with the pid so that we get a new "random" number.
                    mt_srand($this->pid);

                    $this->maxLifetime += mt_rand(0, $splay);
                    $this->log("The worker adjusted max run time to {$this->maxLifetime} seconds", self::LOG_DEBUG);
                }

                $this->startDriverWorker($jobs, $timeouts);

                $this->log('Child exiting', self::LOG_WORKER_INFO);
                $this->quit();
                break;

            case -1: // fork failed.
                $this->log("Could not fork children process!");
                $this->stopWork = true;
                $this->stopChildren();
                break;

            default: // at parent
                $this->log("Started child (PID:$pid) (Jobs:" . implode(',', $jobs) . ')', self::LOG_PROC_INFO);
                $this->children[$pid] = array(
                    'jobs' => $jobs,
                    'start_time' => time(),
                );
        }
    }

    /**
     * Starts a worker for the PECL library
     *
     * @param   array $jobs List of worker functions to add
     * @param   array $timeouts list of worker timeouts to pass to server
     * @return void
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
                    throw $e;
                }
            }
        }

        foreach ($jobs as $job) {
            $timeout = $timeouts[$job] >= 0 ? $timeouts[$job] : 0;
            $this->log("Adding job handler to gearman worker, Name: $job Timeout: $timeout", self::LOG_WORKER_INFO);
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

                $this->debug('Waiting for next job...');

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

        $gmWorker->unregisterAll();
    }

    /**
     * Validates the PECL compatible worker files/functions
     */
    protected function validateDriverWorkers()
    {
        foreach ($this->handlers as $func => $props) {
            if (!function_exists($func) && (!class_exists($func) || !method_exists($func, "run"))) {
                $this->log("Function $func not found in ".$props["path"]);
                posix_kill($this->pid, SIGUSR2);
                exit();
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
