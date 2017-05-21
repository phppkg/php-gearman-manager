<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-05-05
 * Time: 12:41
 */

namespace inhere\gearman\traits;

use inhere\gearman\Helper;

/**
 * Class ProcessManageTrait
 * @package inhere\gearman\traits
 */
trait ProcessManageTrait
{
    /**
     * The worker id
     * @var int
     */
    protected $id = 0;

    ///////// process control //////////

    /**
     * The PID of the current running process. Set for parent and child processes
     */
    protected $pid = 0;

    /**
     * The PID of the parent(master) process, when running in the forked helper,worker.
     */
    protected $masterPid = 0;

    /**
     * @var bool
     */
    protected $isMaster = false;

    /**
     * @var bool
     */
    protected $isWorker = false;

    /**
     * @var string
     */
    protected $pidFile;

    /**
     * When true, workers will stop look for jobs and the parent process will kill off all running workers
     * @var boolean
     */
    protected $stopWork = false;

    /**
     * workers
     * @var array
     * [
     *  pid => [
     *      'id' => [],
     *      'jobs' => [],
     *      'start_time' => int,
     *      'start_times' => int
     *  ],
     *  ... ...
     * ]
     */
    protected $workers = [];

    /**
     * Number of times this worker has run job
     * @var int
     */
    protected $jobExecCount = 0;

    /**
     * pipeHandle
     * @var resource
     */
    protected $pipeHandle;

    /**
     * @deprecated unused
     * param $workerId
     * param $message
     * @return bool
     */
    protected function pipeMessage()
    {
        if (!$this->pipeHandle) {
            return false;
        }

        // 父进程读写管道
        $string = fread($this->pipeHandle, 1024);
        $json = json_decode($string);
        $cmd = $json->command;

        if ($cmd === 'status') {
            fwrite($this->pipeHandle, json_encode([
                'status' => 0,
                'data' => 'received data: ' . json_encode($json->data),
            ]));
        }

        return true;
    }

    /**
     * @deprecated unused
     * @param $command
     * @param $message
     * @param bool $readResult
     * @return bool|int|string
     */
    protected function sendMessage($command, $message, $readResult = true)
    {
        if (!$this->pipeHandle) {
            return false;
        }
        // $pid = $this->masterPid;

        // 子进程读写管道
        $len = fwrite($this->pipeHandle, json_encode([
            'command' => $command,
            'data' => $message,
        ]));

        if ($len && $readResult) {
            return fread($this->pipeHandle, 1024);
        }

        return $len;
    }

    /**
     * Daemon, detach and run in the background
     */
    protected function runAsDaemon()
    {
        $pid = pcntl_fork();

        if ($pid > 0) {// at parent
            // disable trigger stop event in the __destruct()
            $this->isMaster = false;
            $this->clear();
            $this->quit();
        }

        $this->pid = getmypid();
        posix_setsid();

        return true;
    }

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
                $this->startWorker($jobAry, $lastWorkerId++);

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
                $workersCount[$job]++;

                $this->startWorker($job, $lastWorkerId++);

                usleep(500000);
            }
        }

        $this->log(sprintf(
            "Started workers number: %s, Jobs assigned workers info:\n%s",
            Helper::color($lastWorkerId, 'green'),
            Helper::printR($workersCount)
        ), self::LOG_DEBUG);
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
                $this->log("Worker #$workerId exiting(Exit-Code:$code)", self::LOG_WORKER_INFO);
                $this->quit($code);
                break;

            case -1: // fork failed.
                $this->log('Could not fork workers process! exiting');
                $this->stopWork();
                $this->stopWorkers();
                break;

            default: // at parent
                $text = $isFirst ? 'Start' : 'Restart';
                $this->log("Started worker #$workerId with PID $pid ($text) (Jobs:" . implode(',', $jobAry) . ')', self::LOG_PROC_INFO);
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
            $this->dispatchSignal();

            // Check for exited workers
            $status = null;
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
                if (time() - $this->meta['stop_time'] > 60) {
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

        $this->log('All workers stopped', self::LOG_PROC_INFO);
    }

    /**
     * Do shutdown Manager
     * @param  int $pid Master Pid
     * @param  boolean $quit Quit, When stop success?
     */
    protected function stopMaster($pid, $quit = true)
    {
        $this->stdout("Stop the manager(PID:$pid)");

        // do stop
        // 向主进程发送此信号(SIGTERM)服务器将安全终止；也可在PHP代码中调用`$server->shutdown()` 完成此操作
        if (!$this->killProcess($pid, SIGTERM)) {
            $this->stdout("Stop the manager process(PID:$pid) failed!");
        }

        $startTime = time();
        $timeout = 30;
        $this->stdout("Stopping .", false);

        // wait exit
        while (true) {
            if (!$this->isRunning($pid)) {
                break;
            }

            if (time() - $startTime > $timeout) {
                $this->stdout("Stop the manager process(PID:$pid) failed(timeout)!", true, -2);
                break;
            }

            $this->stdout('.', false);
            sleep(1);
        }

        // stop success
        $this->stdout("\nThe manager stopped.\n");

        if ($quit) {
            $this->quit();
        }

        // clear file info
        clearstatcache();

        $this->stdout("Begin restart manager ...");
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

        $signals = [
            SIGINT => 'SIGINT(Ctrl+C)',
            SIGTERM => 'SIGTERM',
            SIGKILL => 'SIGKILL',
        ];

        $this->log("Stopping workers({$signals[$signal]}) ...", self::LOG_PROC_INFO);

        foreach ($this->workers as $pid => $worker) {
            $this->log("Stopping worker #{$worker['id']}(PID:$pid)", self::LOG_PROC_INFO);

            // send exit signal.
            $this->killProcess($pid, $signal);
        }

        return true;
    }

    /**
     * @param int $pid
     * @param array $jobs
     * @param int $statusCode
     */
    protected function logWorkerStatus($pid, $jobs, $statusCode)
    {
        $jobStr = implode(',', $jobs);

        switch ((int)$statusCode) {
            case self::CODE_MANUAL_KILLED:
                $message = "Worker (PID:$pid) has been running too long. Forcibly killing process. (Jobs:$jobStr)";
                break;
            case self::CODE_NORMAL_EXITED:
                $message = "Worker (PID:$pid) normally exited. (Jobs:$jobStr)";
                break;
            case self::CODE_CONNECT_ERROR:
                $message = "Worker (PID:$pid) connect to job server failed. exiting";
                $this->stopWork();
                break;
            default:
                $message = "Worker (PID:$pid) died unexpectedly with exit code $statusCode. (Jobs:$jobStr)";
                break;
        }

        $this->log($message, self::LOG_PROC_INFO);
    }


    /**
     * savePidFile
     */
    protected function savePidFile()
    {
        if ($this->pidFile && !file_put_contents($this->pidFile, $this->pid)) {
            $this->showHelp("Unable to write PID to the file {$this->pidFile}");
        }
    }

    /**
     * delete pidFile
     */
    protected function delPidFile()
    {
        if ($this->pidFile && file_exists($this->pidFile) && !unlink($this->pidFile)) {
            $this->log("Could not delete PID file: {$this->pidFile}", self::LOG_WARN);
        }
    }

    /**
     * mark stopWork
     */
    protected function stopWork()
    {
        //if ()
        $this->stopWork = true;
        $this->meta['stop_time'] = time();
    }

    /**
     * exit
     * @param int $code
     */
    protected function quit($code = 0)
    {
        exit((int)$code);
    }

    /**
     * getWorkerId
     * @param  int $pid
     * @return int
     */
    public function getWorkerId($pid)
    {
        return isset($this->workers[$pid]) ? $this->workers[$pid]['id'] : 0;
    }

    /**
     * getPidByWorkerId
     * @param  int $id
     * @return int
     */
    public function getPidByWorkerId($id)
    {
        $thePid = 0;

        foreach ($this->workers as $pid => $item) {
            if ($id === $item['id']) {
                $thePid = $pid;
                break;
            }
        }

        return $thePid;
    }

    /**
     * @return mixed
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * @return string
     */
    public function getPidFile()
    {
        return $this->pidFile;
    }

    /**
     * @return string
     */
    public function getPidRole()
    {
        return $this->isMaster ? 'Master' : 'Worker';
    }
}
