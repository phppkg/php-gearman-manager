<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-04-28
 * Time: 17:03
 */

namespace inhere\gearman\traits;

use inhere\gearman\Helper;

/**
 * Trait ProcessControlTrait
 * @package inhere\gearman\traits
 */
trait ProcessControlTrait
{
    /**
     * Daemon, detach and run in the background
     */
    protected function runAsDaemon()
    {
        $pid = pcntl_fork();

        if ($pid > 0) {
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
     * check process exist
     * @param $pid
     * @return bool
     */
    public function isRunning($pid)
    {
        return ($pid > 0) && @posix_kill($pid, 0);
    }

    /**
     * setProcessTitle
     * @param $title
     */
    public function setProcessTitle($title)
    {
        if (!Helper::isMac()) {
            cli_set_process_title($title);
        }
    }

    /**
     * Registers the process signal listeners
     * @param bool $isMaster
     */
    protected function registerSignals($isMaster = true)
    {
        if ($isMaster) {
            // $signals = ['SIGTERM' => 'close worker', ];
            $this->log('Registering signal handlers for master(parent) process', self::LOG_DEBUG);

            pcntl_signal(SIGTERM, array($this, 'signalHandler'));
            pcntl_signal(SIGINT, array($this, 'signalHandler'));
            pcntl_signal(SIGUSR1, array($this, 'signalHandler'));
            pcntl_signal(SIGUSR2, array($this, 'signalHandler'));
            pcntl_signal(SIGCONT, array($this, 'signalHandler'));
            pcntl_signal(SIGHUP, array($this, 'signalHandler'));
        } else {
            $this->log('Registering signal handlers for worker process', self::LOG_DEBUG);

            if (!pcntl_signal(SIGTERM, array($this, 'signalHandler'))) {
                $this->quit();
            }
        }
    }

    /**
     * Handles signals
     * @param int $sigNo
     */
    public function signalHandler($sigNo)
    {
        static $stopCount = 0;

        if (!$this->isMaster) {
            $this->stopWork = true;
        } else {
            switch ($sigNo) {
                case SIGUSR1:
                    $this->showHelp("No jobs handlers could be found(signal:SIGUSR1)");
                    break;
                case SIGUSR2:
                    $servers = $this->getServers(false);
                    $this->showHelp("Error validating job servers, please check server address.(job servers: $servers)");
                    break;
                case SIGCONT:
                    $this->log('Validation through, continue(signal:SIGCONT)...', self::LOG_PROC_INFO);
                    $this->waitForSignal = false;
                    break;
                case SIGINT:
                case SIGTERM:
                    $this->log('Shutting down(signal:SIGTERM)...', self::LOG_PROC_INFO);
                    $this->stopWork = true;
                    $this->meta['stop_time'] = time();
                    $stopCount++;

                    if ($stopCount < 5) {
                        $this->stopWorkers(SIGTERM, true);
                    } else {
                        $this->log('Stop workers failed by(signal:SIGTERM), force kill workers by(signal:SIGKILL)', self::LOG_PROC_INFO);
                        $this->stopWorkers(SIGKILL, true);
                    }
                    break;
                case SIGHUP:
                    $this->log('Restarting workers(signal:SIGHUP)', self::LOG_PROC_INFO);
                    $this->openLogFile();
                    $this->stopWorkers();
                    break;
                default:
                    // handle all other signals
            }
        }
    }

    /**
     * kill process by PID
     * @param int $pid
     * @param int $signal
     * @param int $timeout
     * @return bool
     */
    public function killProcess($pid, $signal = SIGTERM, $timeout = 3)
    {
        if ($pid <= 0) {
            return false;
        }

        // do kill
        if ($ret = posix_kill($pid, $signal)) {
            return true;
        }

        // don't want retry
        if ($timeout <= 0) {
            return $ret;
        }

        // failed, try again ...

        $timeout = $timeout > 0 && $timeout < 10 ? $timeout : 3;
        $startTime = time();

        // retry stop if not stopped.
        while (true) {
            // success
            if (!$isRunning = @posix_kill($pid, 0)) {
                break;
            }

            // have been timeout
            if ((time() - $startTime) >= $timeout) {
                return false;
            }

            // try again kill
            $ret = posix_kill($pid, $signal);

            usleep(10000);
        }

        return $ret;
    }
}
