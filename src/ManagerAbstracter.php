<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/4/28
 * Time: 下午9:30
 */

namespace inhere\gearman;

/**
 * Class ManagerAbstracter
 * @package inhere\gearman
 */
abstract class ManagerAbstracter
{
    const VERSION = '0.1.0';

    /**
     * Events list
     */
    const EVENT_BEFORE_PUSH = 'beforePush';
    const EVENT_AFTER_PUSH = 'afterPush';
    const EVENT_BEFORE_WORK = 'beforeWork';
    const EVENT_AFTER_WORK = 'afterWork';
    const EVENT_AFTER_ERROR = 'afterError';

    /**
     * handler types
     */
    const HANDLER_FUNC = 'func';
    const HANDLER_CLOSURE = 'closure';
    const HANDLER_JOB = 'job';

    /**
     * Log levels can be enabled from the command line with -v, -vv, -vvv
     */
    const LOG_EMERG = -8;
    const LOG_ERROR = -6;
    const LOG_WARN = -4;
    const LOG_NOTICE = -2;
    const LOG_INFO = 0;
    const LOG_PROC_INFO = 2;
    const LOG_WORKER_INFO = 4;
    const LOG_DEBUG = 6;
    const LOG_CRAZY = 8;

    /**
     * Logging levels
     * @var array $levels Logging levels
     */
    protected static $levels = array(
        self::LOG_EMERG => 'EMERGENCY',
        self::LOG_ERROR => 'ERROR',
        self::LOG_WARN => 'WARNING',
        self::LOG_INFO => 'INFO',
        self::LOG_PROC_INFO => 'PROC_INFO',
        self::LOG_WORKER_INFO => 'WORKER_INFO',
        self::LOG_DEBUG => 'DEBUG',
        self::LOG_CRAZY => 'CRAZY',
    );

    const DO_ALL = 'all';

    const MIN_HANDLE = 10;

    /**
     * @var string
     */
    protected $script;

    /**
     * @var string
     */
    protected $command;

    /**
     * Verbosity level for the running script. Set via -v option
     * @var int
     */
    protected $verbose = 0;

    /**
     * The worker id
     * @var int
     */
    protected $id = 0;

    /**
     * Holds the resource for the log file
     * @var resource
     */
    protected $logFileHandle;

    ///////// process control //////////

    /**
     * @var bool
     */
    protected $isParent = true;

    /**
     * @var bool
     */
    protected $daemon = false;

    /**
     * The PID of the running process. Set for parent and child processes
     */
    protected $pid = 0;

    /**
     * @var string
     */
    protected $pidFile;

    /**
     * The PID of the parent process, when running in the forked helper,child.
     */
    protected $parentPid = 0;

    /**
     * The PID of the helper process
     * @var int
     */
    protected $helperPid = 0;

    /**
     * children
     * @var array
     * [
     *  pid => [
     *      'jobs' => [],
     *      'start_time' => int
     *  ]
     * ]
     */
    protected $children = [];

    /**
     * Workers will only live for 1 hour
     * @var integer
     */
    protected $maxLifetime = 3600;

    /**
     * the worker max handle 2000 job. after will restart.
     * @var integer
     */
    protected $maxHandleJob = 2000;

    /**
     * allow multi process of the current environment
     * @var boolean
     */
    protected $multiProcess = true;

    /**
     * @var bool
     */
    protected $waitForSignal = false;

    ///////// jobs //////////

    /**
     * Number of workers that do all jobs
     * @var int
     */
    protected $doAllWorkers = 0;

    /**
     * Number of times this worker has run a job
     * @var int
     */
    protected $jobExecCount = 0;

    /**
     * The array of jobs that have workers running
     * @var string[]
     */
    protected $running = [];

    /**
     * There are jobs config
     * @var array
     */
    protected $jobsOpts = [
        // job name => job config
        'reverse_string' => [
            // 至少需要 3 个 worker 去处理这个 job (可能会超过 3 个，会在它和 $doAllWorkers 取最大值), 可以同时做其他的 job
            'worker_num' => 3,
        ],
        'fetch_url' => [
            'worker_num' => 5
        ],
        'sum' => [
            // 需要 5 个 worker 处理这个 job
            'worker_num' => 5,
            // 当设置 dedicated = true, 这些 worker 将专注这一个job
            'dedicated' => true, // true | false
            // job 执行超时时间 秒
            'timeout' => 100,
        ],
    ];

    /**
     * List of job handlers(functions) available for work
     * @var array
     */
    protected $handlers = [
        // job name  => job handler(allow:string,closure,class,object),
        // 'reverse_string' => 'my_reverse_string',
    ];

    ///////// other //////////

    /**
     * Holds the last timestamp of when the code was checked for updates
     * @var int
     */
    protected $lastCheckTime = 0;

    /**
     * @var bool
     */
    protected $watchModify = true;

    /**
     * When true, workers will stop look for jobs and the parent process will kill off all running children
     * @var boolean
     */
    protected $stopWork = false;

    /**
     * @var bool
     */
    protected $restartWork = false;

    /**
     * @var int
     */
    protected $stopTime = 0;

    /**
     * @var array
     */
    private $_events = [];

    ///////// config //////////

    /**
     * the workers config
     * @var array
     */
    protected $config = [
        'servers' => '127.0.0.1:4730',

        // the jobs config, @see $jobs property
        // 'jobs' => [],

        'conf_file' => '',

        // auto reload when 'loader_file' has been modify
        'watch_modify' => true,
        'watch_modify_interval' => 30, // seconds

        // handlers load file
        'loader_file' => '',

        // user and group
        'user' => '',
        'group' => '',

        'daemon' => false,
        'pid_file' => 'worker_manager.pid',

        // 需要 4 个 worker 处理所有的 job, 随机处理。
        'worker_num' => 4,

        // job handle timeout seconds
        'timeout' => 300,

        // Workers will only live for 1 hour
        'max_lifetime' => 3600,

        // now, max_lifetime is <= 3600 and <= 4200
        'restart_splay' => 600,

        // max run 2000 job of each worker. after will auto restart. // todo ...
        'max_run_job' => 2000,

        // log
        'log_level' => 0,
        'log_split' => 'day', // 'day' 'hour', if is empty, not split
        'log_syslog' => false,
        'log_file' => 'job_workers.log',
    ];

    /**
     * ManagerAbstracter constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        // $this->pid = getmypid();
        $this->setConfig($config);

        $this->init();
    }

    /**
     * bootstrap
     */
    protected function init()
    {
        // checkEnvironment
        $this->checkEnvironment();

        // parseCliOption
        $this->handleCliCommand();

        // $this->debug("Start gearman worker, connection to the gearman server {$host}:{$port}");
    }

    /**
     * do start run manager
     */
    public function start()
    {
        $this->pid = getmypid();

        $this->log("Started manager with pid {$this->pid}, Current script owner: " . get_current_user(), self::LOG_PROC_INFO);

        //$this->forkOwn('validateWorkers');
        $this->forkOwn();

        $this->bootstrap();

        $this->startWorkers();

        $this->beginMonitor();

        $this->log('Exiting ... ...');
    }

    /**
     * handle CLI command and load options
     * @return bool
     */
    protected function handleCliCommand()
    {
        $result = Helper::parseParameters([
            'w', 'h', 'V', 'Z', 'help'
        ]);
        $this->script = $result[0];
        $this->command = $command = isset($result[1]) ? $result[1] : 'start';
        unset($result[0], $result[1]);

        $supported = ['start' ,'stop', 'restart', 'reload', 'status'];

        if (!in_array($command, $supported, true)) {
            $this->showHelp("The command [{$command}] is don't supported!");
        }

        // load CLI Options
        $this->loadCliOptions($result);

        // init Config
        $this->initConfig($this->config);

        // Debug option to dump the config and exit
        if (isset($result['Z'])) {
            $this->dumpInfo();
        }

        $pid = $this->getPidFromFile($this->pidFile);
        $isRunning = $this->isRunning($pid);

        // start: do Start Server
        if ($command === 'start') {
            // check master process is running
            if ($isRunning) {
                $this->stdout("The worker manager has been running. (PID:{$pid})", true, -__LINE__);
            }

            return true;
        }

        // check master process
        if (!$isRunning) {
            $this->stdout('The worker manager is not running.', true, -__LINE__);
        }

        // switch command
        switch ($command) {
            case 'stop':
            case 'restart':
            case 'reload':
                // stop: stop and exit. restart: stop and start
                $this->stop($pid, $command === 'stop');
                break;

            case 'status':
                // $this->showStatus();
                $this->showHelp("The command [{$command}] is un-completed!");
                break;

            default:
                $this->showHelp("The command [{$command}] is don't supported!");
                break;
        }

        return true;
    }

    /**
     * load the command line options
     * @param array $opts
     */
    protected function loadCliOptions(array $opts)
    {
        $map = [
            'c' => 'conf_file',   // config file
            's' => 'servers', // server address

            'd' => 'daemon',   // run in the background
            'n' => 'worker_num',  // worker number do all jobs
            'u' => 'user',
            'g' => 'group',

            'l' => 'log_file',
            'p' => 'pid_file',

            'r' => 'max_run_job', // max run jobs for a worker
            'x' => 'max_lifetime',// max lifetime for a worker
            't' => 'timeout',
        ];

        // show help
        if (isset($opts['h']) || isset($opts['help'])) {
            $this->showHelp();
        }

        // load Config File
        if ($file = $this->config['conf_file']) {
            if (!file_exists($file)) {
                $this->showHelp("Config file {$this->config['file']} not found.");
            }

            $config = require $file;

            $this->setConfig($config);
        }

        // load opts values to config
        foreach ($map as $k => $v) {
            if (isset($opts[$k]) && $opts[$k]) {
                $this->config[$v] = $opts[$k];
            }
        }

        if (isset($opts['w'])) {
            $this->config['watch_modify'] = $opts['w'];
        }

        if (isset($opts['v'])) {
            switch ($opts['v']) {
                case true:
                    $this->config['log_level'] = self::LOG_INFO;
                    break;
                case 'v':
                    $this->config['log_level'] = self::LOG_PROC_INFO;
                    break;
                case 'vv':
                    $this->config['log_level'] = self::LOG_WORKER_INFO;
                    break;
                case 'vvv':
                    $this->config['log_level'] = self::LOG_DEBUG;
                    break;
                case 'vvvv':
                    $this->config['log_level'] = self::LOG_CRAZY;
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * @param array $config
     */
    protected function initConfig(array $config)
    {
        $this->config['worker_num'] = (int)$config['worker_num'];
        $this->config['max_lifetime'] = (int)$config['max_lifetime'];
        $this->config['restart_splay'] = (int)$config['restart_splay'];
        $this->config['timeout'] = (int)$config['timeout'];
        $this->config['watch_modify'] = (bool)$config['watch_modify'];
        $this->config['watch_modify_interval'] = (int)$config['watch_modify_interval'];

        if ($this->config['worker_num'] <= 0) {
            $this->config['worker_num'] = 1;
        }

        if ($this->config['max_lifetime'] <= 100) {
            $this->config['max_lifetime'] = 600;
        }

        if ($this->config['restart_splay'] <= 100) {
            $this->config['restart_splay'] = 600;
        }

        if ($this->config['timeout'] <= 10) {
            $this->config['timeout'] = 300;
        }

        if ($this->config['watch_modify_interval'] <= 10) {
            $this->config['watch_modify_interval'] = 30;
        }

        $this->doAllWorkers = $this->config['worker_num'];
        $this->maxLifetime = $this->config['max_lifetime'];
        $this->watchModify = (bool)$this->config['watch_modify'];
        $this->daemon = (bool)$this->config['daemon'];
        $this->verbose = (int)$this->config['log_level'];
        $this->pidFile = trim($config['pid_file']);

    }

    /**
     * Forked method that validates the worker code and checks it if desired
     */
    protected function validateWorkers()
    {
        if (!$this->handlers) {
            $this->log('No job handlers registered!');
            posix_kill($this->parentPid, SIGUSR1);
            $this->quit();
        }

        // $this->validateDriverWorkers();

        // Since we got here, all must be ok, send a CONTINUE
        $this->log("Helper is running. Sending SIGCONT(continue) to $this->parentPid.", self::LOG_DEBUG);
        posix_kill($this->parentPid, SIGCONT);

        if ($this->watchModify && ($loaderFile = $this->config['loader_file'])) {
            $lastCheckTime = 0;
            $checkInterval = $this->config['watch_modify_interval'];

            $this->log("Running loop to watch modify(interval:{$checkInterval}s) for 'loader_file': $loaderFile", self::LOG_DEBUG);

            while (true) {
                $maxTime = 0;
                clearstatcache();
                $mdfTime = filemtime($loaderFile);
                $maxTime = max($maxTime, $mdfTime);

                $this->log("{$loaderFile} - MODIFY $mdfTime,LAST CHECK $lastCheckTime", self::LOG_DEBUG);

                if ($lastCheckTime !== 0 && $mdfTime > $lastCheckTime) {
                    $this->log("New code modify found. Sending SIGHUP(reload) to $this->parentPid", self::LOG_PROC_INFO);
                    posix_kill($this->parentPid, SIGHUP);
                    break;
                }

                $lastCheckTime = $maxTime;
                sleep($checkInterval);
            }
        } else {
            $this->quit();
        }
    }


    /**
     * Forks the process and runs the given method. The parent then waits
     * for the child process to signal back that it can continue
     *
     * param   string $method Class method to run after forking @see `validateWorkers()`
     */
    protected function forkOwn()
    {
        $this->waitForSignal = true;
        $pid = pcntl_fork();

        switch ($pid) {
            case 0: // at children(helper process)
                $this->setProcessTitle("pgm: Helper process");
                $this->isParent = false;
                $this->parentPid = $this->pid;
                $this->pid = getmypid();

                // $this->$method();
                $this->validateWorkers();
                break;
            case -1:
                $this->log("Failed to fork");
                $this->stopWork = true;
                break;
            default: // at parent
                $this->log("Helper process forked with PID:$pid", self::LOG_PROC_INFO);
                $this->helperPid = $pid;

                while ($this->waitForSignal && !$this->stopWork) {
                    usleep(5000);

                    pcntl_waitpid($pid, $status, WNOHANG);

                    if (pcntl_wifexited($status) && $status) {
                        $this->log("Helper process exited with non-zero exit code $status.");
                        $this->quit($status+1);
                    }
                }
                break;
        }
    }

    /**
     * bootstrap start
     */
    protected function bootstrap()
    {
        if ($this->pidFile && !file_put_contents($this->pidFile, $this->pid)) {
            $this->showHelp("Unable to write PID to the file {$this->pidFile}");
        }

        // If we want run as daemon, fork here and exit
        if ($this->config['daemon']) {
            $this->runAsDaemon();
        }

        if ($logFile = $this->config['log_file']) {
            if ($logFile === 'syslog') {
                $this->config['log_syslog'] = true;
                $this->config['log_file'] = $logFile = '';
            } else {
                $this->openLogFile();
            }
        }

        if ($username = $this->config['user']) {
            $user = posix_getpwnam($username);

            if (!$user || !isset($user['uid'])) {
                $this->showHelp("User ({$username}) not found.");
            }

            // Ensure new uid can read/write pid and log files
            if ($this->pidFile && !chown($this->pidFile, $user['uid'])) {
                $this->log("Unable to chown PID file to {$username}", self::LOG_ERROR);
            }

            if ($this->logFileHandle && !chown($logFile, $user['uid'])) {
                $this->log("Unable to chown log file to {$username}", self::LOG_ERROR);
            }

            posix_setuid($user['uid']);

            if (posix_geteuid() !== (int)$user['uid']) {
                $this->showHelp("Unable to change user to {$username} (UID: {$user['uid']}).");
            }

            $this->log("User set to {$username}", self::LOG_PROC_INFO);
        }
    }

    /**
     * Bootstrap a set of workers and any vars that need to be set
     */
    abstract protected function startWorkers();

    /**
     * Begin monitor workers
     *  - will monitoring children process running status
     *
     * @notice run in the parent main process, children process have been exited in the `startWorkers()`
     */
    abstract protected function beginMonitor();

    /**
     * Start a worker do there are assign jobs.
     * If is in the parent, record child info.
     *
     * @param string|array $jobs Jobs for the current worker.
     */
    abstract protected function startWorker($jobs = 'all');

    /**
     * Starts a worker for the driver
     *
     * @param   array $jobs List of worker functions to add
     * @param   array $timeouts list of worker timeouts to pass to server
     * @return void
     */
    abstract protected function startDriverWorker(array $jobs, array $timeouts = []);

    /**
     * Validates the worker handlers
     */
    abstract protected function validateDriverWorkers();

//////////////////////////////////////////////////////////////////////
/// job handle methods
//////////////////////////////////////////////////////////////////////

    /**
     * add a job handler (alias of the `addHandler`)
     * @param string $name
     * @param callable $handler
     * @param array $opts
     * @return bool
     */
    public function addFunction($name, $handler, array $opts = [])
    {
        return $this->addHandler($name, $handler, $opts);
    }

    /**
     * add a job handler
     * @param string $name The job name
     * @param callable $handler The job handler
     * @param array $opts The job options. more @see $jobsOpts property.
     * options allow: [
     *  'timeout' => int
     *  'worker_num' => int
     *  'dedicated' => int
     * ]
     * @return bool
     */
    public function addHandler($name, $handler, array $opts = [])
    {
        if ($this->hasJob($name)) {
            $this->log("The job name [$name] has been registered. don't allow repeat add.", self::LOG_WARN);

            return false;
        }

        if (!$handler && (!is_string($handler) || !is_object($handler))) {
            throw new \InvalidArgumentException("The job [$name] handler data type only allow: string,object");
        }

        // get handler type
        if (is_string($handler)) {
            if (function_exists($handler)) {
                $opts['type'] = self::HANDLER_FUNC;
            } elseif (class_exists($handler) && is_subclass_of($handler, JobInterface::class)) {
                $handler = new $handler;
                $opts['type'] = self::HANDLER_JOB;
            } else {
                throw new \InvalidArgumentException(sprintf(
                    "The job [%s] handler [%s] must be is a function or a subclass of the interface %s",
                    $name,
                    $handler,
                    JobInterface::class
                ));
            }
        } elseif ($handler instanceof \Closure) {
            $opts['type'] = self::HANDLER_CLOSURE;
        } elseif ($handler instanceof JobInterface) {
            $opts['type'] = self::HANDLER_JOB;
        } else {
            throw new \InvalidArgumentException(sprintf(
                'The job [%s] handler [%s] must instance of the interface %s',
                $name,
                get_class($handler),
                JobInterface::class
            ));
        }

        // init opts
        $opts = array_merge([
            'timeout' => 200,
            'worker_num' => 0,
            'dedicated' => false,
        ], $this->getJobOpts($name), $opts);

        if (!$opts['dedicated']) {
            $minCount = max($this->doAllWorkers, 1);

            if ($opts['worker_num'] > 0) {
                $minCount = max($opts['worker_num'], $this->doAllWorkers);
            }

            $opts['worker_num'] = $minCount;
        }

        $this->setJobOpts($name, $opts);
        $this->handlers[$name] = $handler;

        return true;
    }

    /**
     * Wrapper function handler for all registered functions
     * This allows us to do some nice logging when jobs are started/finished
     * @param mixed $job
     * @return bool
     */
    abstract public function doJob($job);

//////////////////////////////////////////////////////////////////////
/// process control method
//////////////////////////////////////////////////////////////////////

    /**
     * Do shutdown Manager
     * @param  int $pid Master Pid
     * @param  boolean $quit Quit, When stop success?
     */
    protected function stop($pid, $quit = true)
    {
        $this->log("The manager process(PID:$pid) stopping ...");

        // do stop
        // 向主进程发送此信号(SIGTERM)服务器将安全终止；也可在PHP代码中调用`$server->shutdown()` 完成此操作
        if (!Helper::killProcess($pid, SIGTERM)) {
            $this->log("Stop the manager process(PID:$pid) failed!", self::LOG_ERROR);
        }

        if ($this->pidFile && file_exists($this->pidFile)) {
            unlink($this->pidFile);
        }

        // stop success
        $this->log("The manager process(PID:$pid) stopped.");

        $quit && $this->quit();
    }

    /**
     * Stops all running children
     * @param int $signal
     * @return bool
     */
    protected function stopChildren($signal = SIGTERM)
    {
        if (!$this->children) {
            $this->log('No children need to stop', self::LOG_PROC_INFO);
            return false;
        }

        $this->log('Stopping children ... ...', self::LOG_PROC_INFO);

        foreach ($this->children as $pid => $child) {
            $this->log(sprintf("Stopping child (PID:$pid) (JOBS: %s)", implode(',', $child['jobs'])), self::LOG_PROC_INFO);

            Helper::killProcess($pid, $signal, 2);
        }

        $this->log('Children Stopped', self::LOG_PROC_INFO);

        return true;
    }

    /**
     * Daemon, detach and run in the background
     */
    protected function runAsDaemon()
    {
        if ($this->multiProcess) {
            $this->log("This is not support run in the background of the current environment.");
            return false;
        }

        $pid = pcntl_fork();

        if ($pid > 0) {
            $this->isParent = false;
            $this->quit();
        }

        $this->pid = getmypid();
        posix_setsid();

        return true;
    }

    /**
     * @param $pid
     * @return bool
     */
    public function isRunning($pid)
    {
        return ($pid > 0) && @posix_kill($pid, 0);
    }

    /**
     * @param $title
     */
    public function setProcessTitle($title)
    {
        cli_set_process_title($title);
    }

    /**
     * Registers the process signal listeners
     * @param bool $parent
     */
    protected function registerSignals($parent = true)
    {
        if ($parent) {
            $this->log('Registering signals for parent', self::LOG_DEBUG);

            pcntl_signal(SIGTERM, array($this, 'signalHandler'));
            pcntl_signal(SIGINT, array($this, 'signalHandler'));
            pcntl_signal(SIGUSR1, array($this, 'signalHandler'));
            pcntl_signal(SIGUSR2, array($this, 'signalHandler'));
            pcntl_signal(SIGCONT, array($this, 'signalHandler'));
            pcntl_signal(SIGHUP, array($this, 'signalHandler'));
        } else {
            $this->log('Registering signals for child', self::LOG_DEBUG);

            if (!$res = pcntl_signal(SIGTERM, array($this, 'signalHandler'))) {
                exit(0);
            }
        }
    }

    /**
     * Handles signals
     * @param int $sigNo
     */
    public function signalHandler($sigNo)
    {
        static $termCount = 0;

        if (!$this->isParent) {
            $this->stopWork = true;
        } else {
            switch ($sigNo) {
                case SIGUSR1:
                    $this->showHelp("No worker files could be found");
                    break;
                case SIGUSR2:
                    $this->showHelp("Error validating worker functions");
                    break;
                case SIGCONT:
                    $this->waitForSignal = false;
                    break;
                case SIGINT:
                case SIGTERM:
                    $this->log('Shutting down...');
                    $this->stopWork = true;
                    $this->stopTime = time();
                    $termCount++;

                    if ($termCount < 5) {
                        $this->stopChildren();
                    } else {
                        $this->stopChildren(SIGKILL);
                    }
                    break;
                case SIGHUP:
                    $this->log("Restarting children", self::LOG_PROC_INFO);
                    $this->openLogFile();
                    $this->stopChildren();
                    break;
                default:
                    // handle all other signals
            }
        }
    }

//////////////////////////////////////////////////////////////////////
/// events method
//////////////////////////////////////////////////////////////////////

    /**
     * register a event callback
     * @param string $name event name
     * @param callable $cb event callback
     * @param bool $replace replace exists's event cb
     * @return $this
     */
    public function on($name, callable $cb, $replace = false)
    {
        if ($replace || !isset($this->_events[$name])) {
            $this->_events[$name] = $cb;
        }

        return $this;
    }

    /**
     * @param string $name
     * @param array $args
     * @return mixed
     */
    protected function trigger($name, array $args = [])
    {
        if (!isset($this->_events[$name]) || !($cb = $this->_events[$name])) {
            return null;
        }

        return call_user_func_array($cb, $args);
    }

//////////////////////////////////////////////////////////////////////
/// getter/setter method
//////////////////////////////////////////////////////////////////////

    /**
     * @return string
     */
    public function getScript()
    {
        return $this->script;
    }

    /**
     * @return string
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param array $config
     */
    public function setConfig(array $config)
    {
        if ($config) {
            $this->config = array_merge($this->config, $config);
        }
    }

    /**
     * @param $name
     * @param null $default
     * @return mixed
     */
    public function get($name, $default = null)
    {
        return isset($this->config[$name]) ? $this->config[$name] : $default;
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
     * @param string $pidFile
     * @return int
     */
    protected function getPidFromFile($pidFile)
    {
        if ($pidFile && file_exists($pidFile)) {
            return (int)file_get_contents($pidFile);
        }

        return 0;
    }

    /**
     * get servers info
     * @param bool $toArray
     * @return array|string
     */
    public function getServers($toArray = true)
    {
        $servers = str_replace(' ', '', $this->get('servers', ''));

        if ($toArray) {
            $servers = strpos($servers, ',') ? explode(',', $servers) : [$servers];
        }

        return $servers;
    }

    /**
     * @return array
     */
    public static function getLevels()
    {
        return self::$levels;
    }

    /**
     * @return bool
     */
    public function isDaemon()
    {
        return $this->daemon;
    }

    /**
     * @return int
     */
    public function getVerbose()
    {
        return $this->verbose;
    }

    /**
     * @return bool
     */
    public function isParent()
    {
        return $this->isParent;
    }

    /**
     * @return int
     */
    public function getMaxLifetime()
    {
        return $this->maxLifetime;
    }

    /**
     * @return array
     */
    public function getHandlers()
    {
        return $this->handlers;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function getHandler($name)
    {
        return isset($this->handlers[$name]) ? $this->handlers[$name] : null;
    }

    /**
     * @return int
     */
    public function getJobCount()
    {
        return count($this->handlers);
    }

    /**
     * @return array
     */
    public function getJobs()
    {
        return array_keys($this->handlers);
    }

    /**
     * @param $name
     * @return bool
     */
    public function hasJob($name)
    {
        return isset($this->handlers[$name]);
    }

    /**
     * @return array
     */
    public function getRunning()
    {
        return $this->running;
    }

    /**
     * @return array
     */
    public function getJobsOpts()
    {
        return $this->jobsOpts;
    }

    /**
     * @param array $optsList
     */
    public function setJobsOpts(array $optsList)
    {
        $this->jobsOpts = $optsList;
    }

    /**
     * @param $name
     * @return bool
     */
    public function hasJobOpts($name)
    {
        return isset($this->jobsOpts[$name]);
    }

    /**
     * get a job's options
     * @param string $name
     * @return array
     */
    public function getJobOpts($name)
    {
        return isset($this->jobsOpts[$name]) ? $this->jobsOpts[$name] : [];
    }

    /**
     * set a job's options
     * @param string $name
     * @param array $opts
     */
    public function setJobOpts($name, array $opts)
    {
        if (isset($this->jobsOpts[$name])) {
            $this->jobsOpts[$name] = array_merge($this->jobsOpts[$name], $opts);
        } else {
            $this->jobsOpts[$name] = $opts;
        }
    }

    /**
     * get a job's option value
     * @param string $name The job name
     * @param string $key The option key
     * @param mixed $default
     * @return mixed
     */
    public function getJobOpt($name, $key, $default = null)
    {
        if ($opts = $this->getJobOpts($name)) {
            return isset($opts[$key]) ? $opts[$key] : $default;
        }

        return $default;
    }

//////////////////////////////////////////////////////////////////////
/// some help method
//////////////////////////////////////////////////////////////////////

    protected function showStatus()
    {
        // todo ...
    }

    /**
     * Shows the scripts help info with optional error message
     * @param string $msg
     */
    protected function showHelp($msg = '')
    {
        $version = self::VERSION;
        $script = $this->getScript();

        if ($msg) {
            echo "ERROR:\n  " . wordwrap($msg, 72, "\n  ") . "\n\n";
        }

        echo <<<EOF
Gearman worker manager script tool. Version $version

USAGE:
  $script -h | -c CONFIG [-v LEVEL] [-l LOG_FILE] [-d] [-a] [-p PID_FILE] {COMMAND}

COMMANDS:
  start         Start gearman worker manager
  stop          Stop gearman worker manager
  restart       Restart gearman worker manager
  status        Get gearman worker manager runtime status

OPTIONS:
  -a             Automatically check and reload when 'loader_file' has been modify
  -c CONFIG      Worker configuration file
  -s HOST[:PORT] Connect to server HOST and optional PORT

  -d             Daemon, detach and run in the background
  -n NUMBER      Start NUMBER workers that do all jobs
  -u USERNAME    Run workers as USERNAME
  -g GROUP_NAME  Run workers as user's GROUP NAME

  -l LOG_FILE    Log output to LOG_FILE or use keyword 'syslog' for syslog support
  -p PID_FILE    File to write process ID out to

  -r NUMBER      Maximum job iterations per worker
  -x SECONDS     Maximum seconds for a worker to live
  -t SECONDS     Maximum number of seconds gearmand server should wait for a worker to complete work before timing out and reissuing work to another worker.

  -v LEVEL       Increase verbosity level by one. eg: -v vv

  -h,--help      Shows this help
  -V             Display the version of the manager
  -Z             Parse the command line and config file then dump it to the screen and exit.\n
EOF;
        exit(0);
    }

    /**
     * dumpInfo
     */
    protected function dumpInfo()
    {
//        $this->stdout('There Are Configure Information:');
//        print_r($this->config);
//
//        $props = [];
//        $exclude = ['config', 'handlers', 'jobOpts', 'children', '_events'];
//        $cls = new \ReflectionObject($this);
//
//        foreach ($cls->getProperties() as $property) {
//            $name = $property->getName();
//
//            if (in_array($name, $exclude) || $property->isStatic()) {
//                continue;
//            }
//
//            $props[$name] = $property->getValue();
//        }

        $this->stdout('There Are Properties Information:');
        print_r($this);

        $this->quit();
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
     * exit
     * @param int $code
     */
    protected function quit($code = 0)
    {
        exit((int)$code);
    }

    /**
     * @param int $pid
     * @param array $jobs
     * @param string|int $status
     */
    protected function logChildStatus($pid, $jobs, $status)
    {
        $jobStr = implode(',', $jobs);

        switch ($status) {
            case 'killed':
                $message = "Child (PID:$pid) has been running too long. Forcibly killing process. (Jobs:$jobStr)";
                break;
            case 'exited':
                $message = "Child (PID:$pid) exited cleanly. (Jobs:$jobStr)";
                break;
            default:
                $message = "Child (PID:$pid) died unexpectedly with exit code $status. (Jobs:$jobStr)";
                break;
        }

        $this->log($message, self::LOG_PROC_INFO);
    }

    /**
     * debug log
     * @param  string $msg
     * @param  array $data
     */
    public function debug($msg, array $data = [])
    {
        $this->log($msg, self::LOG_DEBUG, $data);
    }

    /**
     * Logs data to disk or stdout
     * @param string $msg
     * @param int $level
     * @param array $data
     * @return bool
     */
    public function log($msg, $level = self::LOG_INFO, array $data = [])
    {
        if ($level > $this->verbose) {
            return true;
        }

        $data = $data ? json_encode($data) : '';

        if ($this->get('log_syslog')) {
            return $this->sysLog($msg . ' ' . $data, $level);
        }

        $label = isset(self::$levels[$level]) ? self::$levels[$level] : self::LOG_INFO;

        list($ts, $ms) = explode('.', sprintf('%f', microtime(true)));
        $ds = date('y-m-d H:i:s', $ts) . '.' . str_pad($ms, 6, 0);
        $logString = sprintf('[%s] [PID:%d] [%s] %s %s' . PHP_EOL, $ds, $this->pid, $label, trim($msg), $data);

        // if not in daemon, print log to \STDOUT
        if (!$this->isDaemon()) {
            $this->stdout($logString, false);
        }

        if ($this->logFileHandle) {
            fwrite($this->logFileHandle, $logString);
        }

        return true;
    }

    /**
     * Opens the log file. If already open, closes it first.
     */
    protected function openLogFile()
    {
        if ($logFile = $this->get('log_file')) {
            if ($this->logFileHandle) {
                fclose($this->logFileHandle);
            }

            $this->logFileHandle = @fopen($logFile, 'a');

            if (!$this->logFileHandle) {
                $this->showHelp("Could not open the log file {$logFile}");
            }
        }
    }

    /**
     * Logs data to stdout
     * @param string $logString
     * @param bool $nl
     * @param bool|int $quit
     */
    protected function stdout($logString, $nl = true, $quit = false)
    {
        fwrite(\STDOUT, $logString . ($nl ? PHP_EOL : ''));

        if (($isTrue = true === $quit) || is_int($quit)) {
            $code = $isTrue ? 0 : $quit;
            exit($code);
        }
    }

    /**
     * Logs data to the syslog
     * @param string $msg
     * @param int $level
     * @return bool
     */
    protected function sysLog($msg, $level)
    {
        switch ($level) {
            case self::LOG_EMERG:
                $priority = LOG_EMERG;
                break;
            case self::LOG_ERROR:
                $priority = LOG_ERR;
                break;
            case self::LOG_WARN:
                $priority = LOG_WARNING;
                break;
            case self::LOG_DEBUG:
                $priority = LOG_DEBUG;
                break;
            case self::LOG_INFO:
            case self::LOG_PROC_INFO:
            case self::LOG_WORKER_INFO:
            default:
                $priority = LOG_INFO;
                break;
        }

        if (!$ret = syslog($priority, $msg)) {
            $this->stdout("Unable to write to syslog\n");
        }

        return $ret;
    }

    /**
     * checkEnvironment
     */
    protected function checkEnvironment()
    {
        $this->multiProcess = true;

        if (!function_exists('posix_kill')) {
            $this->multiProcess = false;
            // $this->log("The function 'posix_kill' was not found. Please ensure POSIX functions are installed");
        }

        if (!function_exists('pcntl_fork')) {
            $this->multiProcess = false;
            // $this->log("The function 'pcntl_fork' was not found. Please ensure Process Control functions are installed");
        }

        if (!$this->multiProcess) {
            $this->log("This is not support run multi worker process of the current environment. Require the 'posix,pcntl' extensions.");
        }
    }

    /**
     * Handles anything we need to do when we are shutting down
     */
    public function __destruct()
    {
        if ($this->isParent) {
            $this->delPidFile();

            $this->log('Stopping children trigger by __destruct()', self::LOG_PROC_INFO);

            // stop children processes
            $this->stopChildren();

            if ($this->logFileHandle) {
                fclose($this->logFileHandle);

                $this->logFileHandle = null;
            }
        }
    }
}
