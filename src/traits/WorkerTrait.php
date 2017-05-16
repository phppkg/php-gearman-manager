<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-05-05
 * Time: 12:41
 */

namespace inhere\gearman\traits;

/**
 * Class WorkerTrait
 * @package inhere\gearman\traits
 */
trait WorkerTrait
{
    /**
     * Bootstrap a set of workers and any vars that need to be set
     */
    protected function startWorkers()
    {
        $lastWorkerId = 0;
        $workersCount = [];

        // If we have "doAllWorkerNum" workers, start them first do_all workers register all functions
        if (($num = $this->doAllWorkerNum) > 0) {
            $jobAry = [];// not focus_on jobs

            foreach ($this->getJobs() as $job) {
                if (!$this->jobsOpts[$job]['focus_on']) {
                    $jobAry[] = $job;
                    $workersCount[$job] = $num;
                }
            }

            for ($x = 0; $x < $num; $x++) {
                $lastWorkerId++;
                $this->startWorker($jobAry, $lastWorkerId);

                // Don't start workers too fast. They can overwhelm the gearmand server and lead to connection timeouts.
                usleep(500000);
            }
        }

        // Next we loop the workers and ensure we have enough running for each worker
        foreach ($this->handlers as $job => $handler) {
            // If we don't have 'doAllWorkerNum' workers, this won't be set, so we need to init it here
            if (!isset($workersCount[$job])) {
                $workersCount[$job] = 0;
            }

            $workerNum = $this->jobsOpts[$job]['worker_num'];

            while ($workersCount[$job] < $workerNum) {
                $lastWorkerId++;
                $this->startWorker($job, $lastWorkerId);

                $workersCount[$job]++;

                usleep(500000);
            }
        }

        // Set the last code check time to now since we just loaded all the code
        // $this->lastCheckTime = time();

        $this->log("Jobs workers count:\n" . print_r($workersCount, true), self::LOG_DEBUG);
    }

    /**
     * Start a worker do there are assign jobs. If is in the parent, record worker info.
     *
     * @param string|array $jobs Jobs for the current worker.
     * @param int $workerId The worker id
     * @param bool $isFirst True: Is first start by manager. False: is restart by monitor `startWorkerMonitor()`
     */
    protected function startWorker($jobs, $workerId, $isFirst = true)
    {
        $timeouts = [];
        $jobAry = is_string($jobs) ? [$jobs] : $jobs;
        $defTimeout = $this->get('timeout', 0);

        foreach ($jobAry as $job) {
            $timeouts[$job] = (int)$this->getJobOpt($job, 'timeout', $defTimeout);
        }

        if (!$isFirst) {
            // clear file info
            clearstatcache();
        }

        // fork process
        $pid = pcntl_fork();

        switch ($pid) {
            case 0: // at workers
                $this->isWorker = true;
                $this->isMaster = false;
                $this->masterPid = $this->pid;
                $this->id = $workerId;
                $this->pid = getmypid();
                $this->meta['start_time'] = time();

                if (($jCount = count($jobAry)) > 1) {
                    // shuffle the list to avoid queue preference
                    shuffle($jobAry);
                }

                $this->setProcessTitle(sprintf(
                    "php-gwm: worker process%s (%s)",
                    $this->getShowName(),
                    ($jCount === 1 ? "focus on:{$jobAry[0]}" : 'do all jobs')
                ));
                $this->registerSignals(false);

                if (($splay = $this->get('restart_splay')) > 0) {
                    $this->maxLifetime += mt_rand(0, $splay);
                    $this->log("The worker adjusted max run time to {$this->maxLifetime} seconds", self::LOG_DEBUG);
                }

                $code = $this->startDriverWorker($jobAry, $timeouts);
                $this->log("Worker #$workerId exiting(PID:{$this->pid} Code:$code)", self::LOG_WORKER_INFO);

                $this->quit($code);
                break;

            case -1: // fork failed.
                $this->log('Could not fork workers process! exiting');
                $this->stopWork();
                $this->stopWorkers();
                break;

            default: // at parent
                $text = $isFirst ? 'First' : 'Restart';
                $this->log("Started worker #$workerId(PID:$pid) ($text) (Jobs:" . implode(',', $jobAry) . ')', self::LOG_PROC_INFO);
                $this->workers[$pid] = array(
                    'id' => $workerId,
                    'jobs' => $jobAry,
                    'start_time' => time(),
                );
        }
    }

    /**
     * Begin monitor workers
     *  - will monitoring workers process running status
     *
     * @notice run in the parent main process, workers process will exited in the `startWorkers()`
     */
    protected function startWorkerMonitor()
    {
        $this->log('Now, Begin monitor runtime status for all workers', self::LOG_DEBUG);

        // Main processing loop for the parent process
        while (!$this->stopWork || count($this->workers)) {
            // receive and dispatch sig
            pcntl_signal_dispatch();

            $status = null;

            // Check for exited workers
            $exitedPid = pcntl_wait($status, WNOHANG);

            // We run other workers, make sure this is a worker
            if (isset($this->workers[$exitedPid])) {
                /*
                 * If they have exited, remove them from the workers array
                 * If we are not stopping work, start another in its place
                 */
                if ($exitedPid) {
                    $workerId = $this->workers[$exitedPid]['id'];
                    $workerJobs = $this->workers[$exitedPid]['jobs'];
                    $exitCode = pcntl_wexitstatus($status);
                    unset($this->workers[$exitedPid]);

                    $this->logWorkerStatus($exitedPid, $workerJobs, $exitCode);

                    if (!$this->stopWork) {
                        $this->startWorker($workerJobs, $workerId, false);
                    }
                }
            }

            if ($this->stopWork) {
                if (time() - $this->meta['stop_time'] > 30) {
                    $this->log('Workers have not exited, force killing.', self::LOG_PROC_INFO);
                    $this->stopWorkers(SIGKILL);
                    // $this->killProcess($pid, SIGKILL);
                }
            } else {
                // If any workers have been running 150% of max run time, forcibly terminate them
                foreach ($this->workers as $pid => $worker) {
                    if (!empty($worker['start_time']) && time() - $worker['start_time'] > $this->maxLifetime * 1.5) {
                        $this->logWorkerStatus($pid, $worker['jobs'], self::CODE_MANUAL_KILLED);
                        $this->killProcess($pid, SIGKILL);
                    }
                }
            }

            // php will eat up your cpu if you don't have this
            usleep(10000);
        }
    }

    /**
     * reloadWorkers
     * @param $masterPid
     */
    protected function reloadWorkers($masterPid)
    {
        $this->stdout("Workers reloading ...");

        $this->sendSignal($masterPid, SIGHUP);

        $this->quit();
    }

    /**
     * Stops all running workers
     * @param int $signal
     * @return bool
     */
    protected function stopWorkers($signal = SIGTERM)
    {
        if (!$this->workers) {
            $this->log('No child process(worker) need to stop', self::LOG_PROC_INFO);
            return false;
        }

        static $stopping = false;

        if ($stopping) {
            $this->log('Workers stopping ...', self::LOG_PROC_INFO);
            return true;
        }

        $signals = [
            SIGINT => 'SIGINT',
            SIGTERM => 'SIGTERM',
            SIGKILL => 'SIGKILL',
        ];

        $this->log("Stopping workers({$signals[$signal]}) ...", self::LOG_PROC_INFO);

        foreach ($this->workers as $pid => $worker) {
            $this->log("Stopping worker (PID:$pid) (Jobs:".implode(",", $worker['jobs']).")", self::LOG_PROC_INFO);

            // send exit signal.
            $this->killProcess($pid, $signal);
        }

        if ($signal === SIGKILL) {
            $stopping = true;
        }

        return true;
    }

}
