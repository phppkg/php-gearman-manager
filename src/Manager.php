<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-04-28
 * Time: 17:03
 */

namespace inhere\gearman;

use GearmanWorker;

/**
 * Class Manager - gearman worker manager
 * @package inhere\gearman
 */
class Manager extends LiteManager
{
    /**
     * The PID of the helper process
     * @var int
     */
    protected $helperPid = 0;

    /**
     * @var bool
     */
    protected $isHelper = false;

    /**
     * wait response for process signal
     * @var bool
     */
    private $waitForSignal = false;

    /**
     * @var \Closure
     */
    private $handlersLoader;

    /**
     * do start run manager
     */
    public function start()
    {
        // load all job handlers
        if ($loader = $this->handlersLoader) {
            $loader($this);
        }

        // check
        if (!$this->handlers) {
            $this->stdout("ERROR: No jobs handler found. please less register one.\n");
            $this->quit();
        }

        // 不能直接将属性 isMaster 定义为 True
        // 这会导致启动后，在执行任意命令时都会删除 pid 文件(触发了__destruct)
        $this->isMaster = true;
        $this->meta['start_time'] = time();
        $this->setProcessTitle(sprintf("php-gwm: master process (%s)", $this->getFullScript()));

        // prepare something for start
        $this->prepare();

        // Register signal listeners `pcntl_signal_dispatch()`
        $this->registerSignals();

        // fork a Helper process
        // $this->startHelper('startWatcher');
        $this->startHelper();

        $this->log("Started manager with pid {$this->pid}, Current script owner: " . get_current_user(), self::LOG_PROC_INFO);

        // start workers and set up a running environment
        $this->startWorkers();

        // start worker monitor
        $this->startWorkerMonitor();

        // stop Helper
        $this->stopHelper();

        $this->log('Stopping Manager ...', self::LOG_PROC_INFO);

        $this->quit();
    }

    /**
     * Forks the process and runs the given method. The parent then waits
     * for the worker child process to signal back that it can continue
     *
     * param   string $method Class method to run after forking @see `startWatcher()`
     *
     * @old forkOwner()
     */
    protected function startHelper()
    {
        $this->waitForSignal = true;
        $pid = pcntl_fork();

        switch ($pid) {
            case 0: // at workers(helper process)
                $this->setProcessTitle("php-gwm: helper process");
                $this->isMaster = false;
                $this->isHelper = true;
                $this->masterPid = $this->pid;
                $this->pid = getmypid();

                $this->validateDriverWorkers();
                $this->startWatchModify();
                break;
            case -1:
                $this->log('Failed to fork helper process', self::LOG_ERROR);
                $this->stopWork = true;
                break;
            default: // at parent
                $this->log("Helper process forked with PID:$pid", self::LOG_PROC_INFO);
                $this->helperPid = $pid;

                while ($this->waitForSignal && !$this->stopWork) {
                    usleep(5000);

                    // receive and dispatch sig
                    pcntl_signal_dispatch();
                    pcntl_waitpid($pid, $status, WNOHANG);
                    $exitCode = pcntl_wexitstatus($status);

                    if (self::CODE_CONNECT_ERROR === $exitCode) {
                        $servers = $this->getServers(false);
                        $this->log("Error validating job servers, please check server address.(job servers: $servers)");
                        $this->quit($exitCode);
                    } elseif (self::CODE_NORMAL_EXITED !== $exitCode) {
                        $this->log("Helper process exited with non-zero exit code [$exitCode].");
                        $this->quit($exitCode);
                    }
                }
                break;
        }
    }

    /**
     * Forked method that watch the worker code and checks it if desired
     * @old validateWorkers()
     */
    protected function startWatchModify()
    {
        if ($this->config['watch_modify'] && ($loaderFile = $this->config['loader_file'])) {
            $lastCheckTime = 0;
            $checkInterval = $this->config['watch_modify_interval'];

            $this->log("Running loop to watch modify(interval:{$checkInterval}s) for 'loader_file': $loaderFile", self::LOG_DEBUG);

            while (!$this->stopWork) {
                // $maxTime = 0;
                $mdfTime = filemtime($loaderFile);
                // $maxTime = max($maxTime, $mdfTime);

                $this->log("'loader_file': {$loaderFile} - MODIFY TIME: $mdfTime,LAST CHECK TIME: $lastCheckTime", self::LOG_DEBUG);

                if ($lastCheckTime && $mdfTime > $lastCheckTime) {
                    clearstatcache();
                    $this->log("New code modify found. Sending SIGHUP(reload) to master(PID:{$this->masterPid})", self::LOG_PROC_INFO);
                    $this->sendSignal($this->masterPid, SIGHUP);
                    break;
                }

                $lastCheckTime = time();
                sleep($checkInterval);
            }
        }

        $this->log('Helper stopping', self::LOG_PROC_INFO);

        $this->quit();
    }

    /**
     * Validates the PECL compatible worker files/functions
     */
    protected function validateDriverWorkers()
    {
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
                $this->quit(self::CODE_CONNECT_ERROR);
            }
        }

        unset($gmWorker);

        // Since we got here, all must be ok, send a CONTINUE
        $this->log("code watch is running. Sending SIGCONT(continue) to master(PID:{$this->masterPid}).", self::LOG_PROC_INFO);
        posix_kill($this->masterPid, SIGCONT);
    }

    /**
     * {@inheritDoc}
     */
    protected function startWorker($jobs, $isFirst = true)
    {
        $this->isHelper = false;

        parent::startWorker($jobs, $isFirst);
    }

    /**
     * stop Helper process
     */
    protected function stopHelper()
    {
        if ($pid = $this->helperPid) {
            $this->log("Stopping helper(PID:$pid) ...", self::LOG_PROC_INFO);

            $this->helperPid = 0;
            $this->killProcess($pid, SIGKILL);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function registerSignals($isMaster = true)
    {
        if ($isMaster) {
            pcntl_signal(SIGCONT, array($this, 'signalHandler'));
        }

        parent::registerSignals($isMaster);
    }

    /**
     * Handles signals
     * @param int $sigNo
     */
    public function signalHandler($sigNo)
    {
        static $stopCount = 0;

        if ($this->isMaster) {
            switch ($sigNo) {
                case SIGCONT:
                    $this->log('Validation through, continue(signal:SIGCONT)...', self::LOG_PROC_INFO);
                    $this->waitForSignal = false;
                    break;
                case SIGINT: // Ctrl + C
                case SIGTERM:
                    $sigText = $sigNo === SIGINT ? 'SIGINT' : 'SIGTERM';
                    $this->log("Shutting down(signal:$sigText)...", self::LOG_PROC_INFO);
                    $this->stopWork = true;
                    $this->meta['stop_time'] = time();
                    $stopCount++;

                    if ($stopCount < 5) {
                        $this->stopWorkers();
                    } else {
                        $this->log('Stop workers failed by(signal:SIGTERM), force kill workers by(signal:SIGKILL)', self::LOG_PROC_INFO);
                        $this->stopWorkers(SIGKILL);
                    }
                    break;
                case SIGHUP:
                    $this->log('Restarting workers(signal:SIGHUP)', self::LOG_PROC_INFO);
                    $this->openLogFile();
                    $this->stopWorkers();
                    break;
                case SIGUSR1: // reload workers and reload handlers
                    $this->log('Reloading workers and handlers(signal:SIGUSR1)', self::LOG_PROC_INFO);
                    $this->stopWork = true;
                    $this->start();
                    break;
                case SIGUSR2:
                    break;
                default:
                    // handle all other signals
            }

        } else {
            $this->stopWork = true;
            $this->meta['stop_time'] = time();
            $this->log("Received 'stopWork' signal(signal:SIGTERM), will be exiting.", self::LOG_PROC_INFO);
        }
    }


    /**
     * Handles anything we need to do when we are shutting down
     */
    public function __destruct()
    {
        // master
        if ($this->isMaster) {
            // stop Helper
            $this->stopHelper();

            // delPidFile
            $this->delPidFile();

            // close logFileHandle
            if ($this->logFileHandle) {
                fclose($this->logFileHandle);

                $this->logFileHandle = null;
            }

            $this->log('All workers stopped', self::LOG_PROC_INFO);
            $this->log("Manager stopped\n", self::LOG_PROC_INFO);

            // helper
        } elseif ($this->isHelper) {
            $this->log("Helper stopped", self::LOG_PROC_INFO);
            // worker
        } elseif ($this->isWorker) {
            // $this->log("Worker stopped(PID:{$this->pid})", self::LOG_PROC_INFO);
        }

        $this->clear($this->isMaster);
    }

    /**
     * @return string
     */
    public function getPidRole()
    {
        return $this->isMaster ? 'Master' : ($this->isHelper ? 'Helper' : 'Worker');
    }

    /**
     * @param \Closure $loader
     */
    public function setHandlersLoader(\Closure $loader)
    {
        $this->handlersLoader = $loader;
    }
}
