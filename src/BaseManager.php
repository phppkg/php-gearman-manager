<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/4/28
 * Time: 下午9:30
 */

// declare(ticks=1); 更换为使用 pcntl_signal_dispatch(), 性能更好

namespace inhere\gearman;

use inhere\gearman\jobs\JobInterface;
use inhere\gearman\tools\Telnet;
use inhere\gearman\traits;

/**
 * Class BaseManager
 * @package inhere\gearman
 */
abstract class BaseManager implements ManagerInterface
{
    use traits\EventTrait;
    use traits\LogTrait;
    use traits\ProcessControlTrait;

    /**
     * Logging levels
     * @var array $levels Logging levels
     */
    protected static $levels = [
        self::LOG_EMERG => 'EMERGENCY',
        self::LOG_ERROR => 'ERROR',
        self::LOG_WARN => 'WARNING',
        self::LOG_INFO => 'INFO',
        self::LOG_PROC_INFO => 'PROC_INFO',
        self::LOG_WORKER_INFO => 'WORKER_INFO',
        self::LOG_DEBUG => 'DEBUG',
        self::LOG_CRAZY => 'CRAZY',
    ];

    /**
     * @var string
     */
    private $fullScript;

    /**
     * @var string
     */
    private $script;

    /**
     * @var string
     */
    private $command;

    /**
     * @var string
     */
    private $name;

    /**
     * Verbosity level for the running script. Set via -v option
     * @var int
     */
    protected $verbose = 4;

    ///////// process control //////////

    /**
     * The worker id
     * @var int
     */
    protected $id = 0;

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
     * @var bool
     */
    // protected $working = true;

    /**
     * workers
     * @var array
     * [
     *  pid => [
     *      'jobs' => [],
     *      'start_time' => int
     *  ]
     * ]
     */
    protected $workers = [];

    ///////// jobs //////////

    /**
     * Number of workers that do all jobs
     * @var int
     */
    protected $doAllWorkerNum = 0;

    /**
     * Workers will only live for 1 hour
     * @var integer
     */
    protected $maxLifetime = 3600;

    /**
     * Number of times this worker has run job
     * @var int
     */
    protected $jobExecCount = 0;

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
     * The array of meta for manager/worker
     * @var array
     */
    protected $meta = [
        'start_time' => 0,
        'stop_time'  => 0,
        'start_times' => 0,
    ];

    ///////// config //////////

    /**
     * the workers config
     * @var array
     */
    protected $config = [
        // if you setting name, will display on the process name.
        'name' => '',

        'servers' => '127.0.0.1:4730',

        // the jobs config, @see $jobs property
        // 'jobs' => [],

        'conf_file' => '',

        // auto reload when 'loader_file' has been modify
        'watch_modify' => true,
        'watch_modify_interval' => 300, // seconds

        // handlers load file
        'loader_file' => '',

        // user and group
        'user' => '',
        'group' => '',

        // run in the background
        'daemon' => false,

        // need 4 worker do all jobs
        'worker_num' => 4,

        'no_test' => false,

        // Workers will only live for 1 hour, after will auto restart.
        'max_lifetime' => 3600,
        // now, max_lifetime is >= 3600 and <= 4200
        'restart_splay' => 600,
        // max run 2000 job of each worker, after will auto restart.
        'max_run_jobs' => 2000,

        // the master process pid save file
        'pid_file' => 'gwm.pid',

        // will record manager meta data to file
        'meta_file' => 'meta.dat',

        // job handle default timeout seconds
        'timeout' => 300,

        // log
        'log_level' => 4,
        // 'day' 'hour', if is empty, not split.
        'log_split' => 'day',
        // will write log by `syslog()`
        'log_syslog' => false,
        'log_file' => 'gwm.log',
    ];

    /**
     * The default job option
     * @var array
     */
    private static $defaultJobOpt = [
        // 需要 'worker_num' 个 worker 处理这个 job
        'worker_num' => 0,
        // 当设置 focus_on = true, 这些 worker 将专注这一个job
        'focus_on' => false, // true | false
        // job 执行超时时间 秒
        'timeout' => 200,
    ];

    /**
     * There are jobs config
     * @var array
     */
    protected $jobsOpts = [
        // job name => job option // please see self::$defaultJobOpt
    ];

//////////////////////////////////////////////////////////////////////
/// begin logic, init config and properties
//////////////////////////////////////////////////////////////////////

    /**
     * ManagerAbstracter constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        // checkEnvironment
        $this->checkEnvironment();

        $this->pid = getmypid();

        $this->setConfig($config);

        $this->init();
    }

    /**
     * init parse CLI commands and options and config.
     */
    protected function init()
    {
        // handleCommandAndConfig
        $this->handleCommandAndConfig();
    }

    /**
     * handle CLI command and load options
     * @return bool
     */
    protected function handleCommandAndConfig()
    {
        $result = Helper::parseParameters([
            'd', 'daemon', 'w', 'watch', 'h', 'help', 'V', 'version', 'no-test', 'watch-status'
        ]);
        $this->fullScript = implode(' ', $GLOBALS['argv']);
        $this->script = strpos($result[0], '.php') ? "php {$result[0]}" : $result[0];
        $this->command = $command = isset($result[1]) ? $result[1] : 'start';
        unset($result[0], $result[1]);

        $supported = ['start', 'stop', 'restart', 'reload', 'status'];

        if (!in_array($command, $supported, true)) {
            $this->showHelp("The command [{$command}] is don't supported!");
        }

        // load CLI Options
        $this->loadCliOptions($result);

        // init Config And Properties
        $this->initConfigAndProperties($this->config);

        // Debug option to dump the config and exit
        if (isset($result['D']) || isset($result['dump'])) {
            $val = isset($result['D']) ? $result['D'] : (isset($result['dump']) ? $result['dump'] : '');
            $this->dumpInfo($val === 'all');
        }

        $masterPid = $this->getPidFromFile($this->pidFile);
        $isRunning = $this->isRunning($masterPid);

        // start: do Start Server
        if ($command === 'start') {
            // check master process is running
            if ($isRunning) {
                $this->stderr("The worker manager has been running. (PID:{$masterPid})\n", true, -__LINE__);
            }

            return true;
        }

        // check master process
        if (!$isRunning) {
            $this->stderr("The worker manager is not running. can not execute the command: {$command}\n", true, -__LINE__);
        }

        // switch command
        switch ($command) {
            case 'stop':
            case 'restart':
                // stop: stop and exit. restart: stop and start
                $this->stopMaster($masterPid, $command === 'stop');
                break;
            case 'reload':
                // reload workers
                $this->reloadWorkers($masterPid);
                break;
            case 'status':
                $cmd = isset($result['cmd']) ? $result['cmd']: 'status';
                $this->showStatus($cmd, isset($result['watch-status']));
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

            'n' => 'worker_num',  // worker number do all jobs
            'u' => 'user',
            'g' => 'group',

            'l' => 'log_file',
            'p' => 'pid_file',

            'r' => 'max_run_jobs', // max run jobs for a worker
            'x' => 'max_lifetime',// max lifetime for a worker
            't' => 'timeout',
        ];

        // show help
        if (isset($opts['h']) || isset($opts['help'])) {
            $this->showHelp();
        }
        // show version
        if (isset($opts['V']) || isset($opts['version'])) {
            $this->showVersion();
        }

        // load opts values to config
        foreach ($map as $k => $v) {
            if (isset($opts[$k]) && $opts[$k]) {
                $this->config[$v] = $opts[$k];
            }
        }

        // load Custom Config File
        if ($file = $this->config['conf_file']) {
            if (!file_exists($file)) {
                $this->showHelp("Custom config file {$file} not found.");
            }

            $config = require $file;
            $this->setConfig($config);
        }

        // watch modify
        if (isset($opts['w']) || isset($opts['watch'])) {
            $this->config['watch_modify'] = $opts['w'];
        }

        // run as daemon
        if (isset($opts['d']) || isset($opts['daemon'])) {
            $this->config['daemon'] = true;
        }

        // no test
        if (isset($opts['no-test'])) {
            $this->config['no_test'] = true;
        }

        if (isset($opts['v'])) {
            $opts['v'] = $opts['v'] === true ? '' : $opts['v'];

            switch ($opts['v']) {
                case '':
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
                    // $this->config['log_level'] = self::LOG_INFO;
                    break;
            }
        }
    }

    /**
     * @param array $config
     */
    protected function initConfigAndProperties(array $config)
    {
        // init config attributes

        $this->config['daemon'] = (bool)$config['daemon'];
        $this->config['pid_file'] = trim($config['pid_file']);
        $this->config['worker_num'] = (int)$config['worker_num'];

        $this->config['log_level'] = (int)$config['log_level'];
        $logFile = trim($config['log_file']);

        if ($logFile === 'syslog') {
            $this->config['log_syslog'] = true;
            $this->config['log_file'] = '';
        } else {
            $this->config['log_file'] = $logFile;
        }

        $this->config['timeout'] = (int)$config['timeout'];
        $this->config['max_lifetime'] = (int)$config['max_lifetime'];
        $this->config['max_run_jobs'] = (int)$config['max_run_jobs'];
        $this->config['restart_splay'] = (int)$config['restart_splay'];

        $this->config['watch_modify'] = (bool)$config['watch_modify'];
        $this->config['watch_modify_interval'] = (int)$config['watch_modify_interval'];

        // config value fix ... ...

        if ($this->config['worker_num'] <= 0) {
            $this->config['worker_num'] = self::WORKER_NUM;
        }

        if ($this->config['max_lifetime'] < self::MIN_LIFETIME) {
            $this->config['max_lifetime'] = self::MAX_LIFETIME;
        }

        if ($this->config['max_run_jobs'] < self::MIN_RUN_JOBS) {
            $this->config['max_run_jobs'] = self::MAX_RUN_JOBS;
        }

        if ($this->config['restart_splay'] <= 100) {
            $this->config['restart_splay'] = self::RESTART_SPLAY;
        }

        if ($this->config['timeout'] <= self::MIN_JOB_TIMEOUT) {
            $this->config['timeout'] = self::JOB_TIMEOUT;
        }

        if ($this->config['watch_modify_interval'] <= self::MIN_WATCH_INTERVAL) {
            $this->config['watch_modify_interval'] = self::WATCH_INTERVAL;
        }

        // init properties

        $this->name = trim($config['name']);
        $this->doAllWorkerNum = $this->config['worker_num'];
        $this->maxLifetime = $this->config['max_lifetime'];
        $this->verbose = $this->config['log_level'];
        $this->pidFile = $this->config['pid_file'];

        unset($config);
    }

//////////////////////////////////////////////////////////////////////
/// manager methods
//////////////////////////////////////////////////////////////////////

    protected function beforeStart()
    {}

    /**
     * do start run manager
     */
    public function start()
    {
        $this->beforeStart();

        // check
        if (!$this->handlers) {
            $this->stderr("No jobs handler found. please less register one.\n");
            $this->quit();
        }

        // 不能直接将属性 isMaster 定义为 True
        // 这会导致启动后，在执行任意命令时都会删除 pid 文件(触发了__destruct)
        $this->isMaster = true;
        $this->meta['start_time'] = time();
        $this->setProcessTitle(sprintf("php-gwm: master process%s (%s)", $this->getShowName(), $this->fullScript));

        // prepare something for start
        $this->prepare();

        $this->log("Started manager with pid {$this->pid}, Current script owner: " . get_current_user(), self::LOG_PROC_INFO);

        // Register signal listeners `pcntl_signal_dispatch()`
        $this->registerSignals();

        // before Start Workers
        $this->beforeStartWorkers();

        // start workers and set up a running environment
        $this->startWorkers();

        // start worker monitor
        $this->startWorkerMonitor();

        $this->afterStart();
    }

    protected function beforeStartWorkers()
    {}

    protected function afterStart()
    {
        $this->log('Stopping Manager ...', self::LOG_PROC_INFO);

        $this->quit();
    }

    /**
     * prepare start
     */
    protected function prepare()
    {
        // If we want run as daemon, fork here and exit
        if ($this->config['daemon']) {
            $this->stdout('Run the worker manager in the background');
            $this->runAsDaemon();
        }

        // save Pid File
        $this->savePidFile();

        // open Log File
        $this->openLogFile();

        if ($username = $this->config['user']) {
            $this->changeScriptOwner($username, $this->config['group']);
        }
    }

    /**
     * Bootstrap a set of workers and any vars that need to be set
     */
    protected function startWorkers()
    {
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
                $this->startWorker($jobAry);

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
                $this->startWorker($job);

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
     * @param bool $isFirst True: Is first start by manager. False: is restart by monitor `startWorkerMonitor()`
     */
    protected function startWorker($jobs, $isFirst = true)
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
                $this->log("Worker exiting(PID:{$this->pid} Code:$code)", self::LOG_WORKER_INFO);

                $this->quit($code);
                break;

            case -1: // fork failed.
                $this->log('Could not fork workers process! exiting');
                $this->stopWork = true;
                $this->stopWorkers();
                break;

            default: // at parent
                $text = $isFirst ? 'First' : 'Restart';
                $this->log("Started worker(PID:$pid) ($text) (Jobs:" . implode(',', $jobAry) . ')', self::LOG_PROC_INFO);
                $this->workers[$pid] = array(
                    'jobs' => $jobAry,
                    'start_time' => time(),
                );
        }
    }

    /**
     * Starts a worker for the PECL library
     *
     * @param   array $jobs List of worker functions to add
     * @param   array $timeouts list of worker timeouts to pass to server
     * @return  int The exit status code
     * @throws \GearmanException
     */
    abstract protected function startDriverWorker(array $jobs, array $timeouts = []);

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
                    $workerJobs = $this->workers[$exitedPid]['jobs'];
                    $exitCode = pcntl_wexitstatus($status);
                    unset($this->workers[$exitedPid]);

                    $this->logWorkerStatus($exitedPid, $workerJobs, $exitCode);

                    if (!$this->stopWork) {
                        $this->startWorker($workerJobs, false);
                    }
                }
            }

            if ($this->stopWork && time() - $this->meta['stop_time'] > 30) {
                $this->log('Workers have not exited, force killing.', self::LOG_PROC_INFO);
                $this->stopWorkers(SIGKILL);
                // $this->killProcess($pid, SIGKILL);
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
     *  'focus_on' => int
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

        // no test handler
        if ($this->config['no_test'] && 0 === strpos($name,'test')) {
            return false;
        }

        $this->trigger(self::EVENT_BEFORE_PUSH, [$name, $handler, $opts]);

        // get handler type
        if (is_string($handler)) {
            if (function_exists($handler)) {
                $opts['type'] = self::HANDLER_FUNC;
            } elseif (class_exists($handler) && is_subclass_of($handler, JobInterface::class)) {
                $handler = new $handler;
                $opts['type'] = self::HANDLER_JOB;
            } elseif (class_exists($handler) && method_exists($handler, '__invoke')) {
                $handler = new $handler;
                $opts['type'] = self::HANDLER_INVOKE;
            } else {
                throw new \InvalidArgumentException(sprintf(
                    "The job(%s) handler(%s) must be is a function name or a class implement the '__invoke()' or a class implement the interface %s",
                    $name,
                    $handler,
                    JobInterface::class
                ));
            }
        } elseif ($handler instanceof \Closure) {
            $opts['type'] = self::HANDLER_CLOSURE;
        } elseif ($handler instanceof JobInterface) {
            $opts['type'] = self::HANDLER_JOB;
        } elseif (method_exists($handler, '__invoke')) {
            $opts['type'] = self::HANDLER_INVOKE;
        } else {
            throw new \InvalidArgumentException(sprintf(
                'The job [%s] handler [%s] must instance of the interface %s',
                $name,
                get_class($handler),
                JobInterface::class
            ));
        }

        // init opts
        $opts = array_merge(self::$defaultJobOpt, $this->getJobOpts($name), $opts);
        $opts['focus_on'] = (bool)$opts['focus_on'];

        if (!$opts['focus_on']) {
            $minCount = max($this->doAllWorkerNum, 1);

            if ($opts['worker_num'] > 0) {
                $minCount = max($opts['worker_num'], $this->doAllWorkerNum);
            }

            $opts['worker_num'] = $minCount;
        } else {
            $opts['worker_num'] = $opts['worker_num'] < 0 ? 0 : (int)$opts['worker_num'];
        }

        $this->setJobOpts($name, $opts);
        $this->handlers[$name] = $handler;

        $this->trigger(self::EVENT_AFTER_PUSH, [$name, $handler, $opts]);

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
/// some help method
//////////////////////////////////////////////////////////////////////

    /**
     * clear
     * @param  boolean $workerInfo
     */
    public function clear($workerInfo = false)
    {
        $this->config = $this->_events = $this->jobsOpts = $this->handlers = [];

        if ($workerInfo) {
            $this->workers = [];
        }
    }

    /**
     * show Status
     * @param string $cmd
     * @param bool $doWatch
     */
    protected function showStatus($cmd = 'status', $doWatch = false)
    {
        // todo 暂时只支持一个
        $server = $this->getServers()[0];

        if (strpos($server, ':')) {
            list($host, $port) = explode(':', $server);
        } else {
            $host = $server;
            $port = 4730;
        }

        $this->stdout("Connect to the gearman server " . Helper::color("{$host}:{$port}", 'green'));

        $telnet = new Telnet($host, $port);

        if ($doWatch) {
            $telnet->watch($cmd);
            $this->quit();
        }

        switch ($cmd) {
            case 'workers':
                $this->stdout("There are workers info:\n");
                $result = $telnet->command($cmd);
                break;

            case 'status':
            default:
            $this->stdout("There are jobs status info:\n");
                $result = $telnet->command('status');

                break;
        }

        $this->stdout($result, true, 0);
    }

    /**
     * show Version
     */
    protected function showVersion()
    {
        printf("Gearman worker manager script tool. Version %s\n", Helper::color(self::VERSION, 'green'));

        $this->quit();
    }

    /**
     * Shows the scripts help info with optional error message
     * @param string $msg
     * @param int $code The exit code
     */
    protected function showHelp($msg = '', $code = 0)
    {
        $version = Helper::color(self::VERSION, 'green');
        $script = $this->getScript();

        if ($msg) {
            $code = $code ?: self::CODE_UNKNOWN_ERROR;
            echo "ERROR:\n  " . wordwrap($msg, 108, "\n  ") . "\n\n";
        }

        echo <<<EOF
Gearman worker manager(gwm) script tool. Version $version(lite)

USAGE:
  $script {COMMAND} -c CONFIG [-v LEVEL] [-l LOG_FILE] [-d] [-w] [-p PID_FILE]
  $script -h
  $script -D

COMMANDS:
  start             Start gearman worker manager(default)
  stop              Stop running's gearman worker manager
  restart           Restart running's gearman worker manager
  reload            Reload all running workers of the manager
  status            Get gearman worker manager runtime status

SPECIAL OPTIONS:
  start/restart
    -d,--daemon        Daemon, detach and run in the background
       --no-test       Not add test handler, when job name prefix is 'test'.(eg: test_job)

  status
    --cmd COMMAND      Send command when connect to the job server. allow:status,workers.(default:status)
    --watch-status     Watch status command, will auto refresh status.

PUBLIC OPTIONS:
  -c CONFIG          Load a custom worker manager configuration file
  -s HOST[:PORT]     Connect to server HOST and optional PORT, multi server separated by commas(',')

  -n NUMBER          Start NUMBER workers that do all jobs

  -l LOG_FILE        Log output to LOG_FILE or use keyword 'syslog' for syslog support
  -p PID_FILE        File to write master process ID out to

  -r NUMBER          Maximum run job iterations per worker
  -x SECONDS         Maximum seconds for a worker to live
  -t SECONDS         Number of seconds gearmand server should wait for a worker to complete work before timing out

  -v [LEVEL]         Increase verbosity level by one. eg: -v vv | -v vvv

  -h,--help          Shows this help information
  -V,--version       Display the version of the manager
  -D,--dump [all]    Parse the command line and config file then dump it to the screen and exit.\n\n
EOF;
        exit($code);
    }

    /**
     * dumpInfo
     * @param bool $allInfo
     */
    protected function dumpInfo($allInfo = false)
    {
         if ($allInfo) {
            $this->stdout("There are all information of the manager:");
            Helper::printR($this);
        } else {
            $this->stdout("There are configure information:");
            Helper::printR($this->config);
        }

        $this->quit();
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
                unset($this->workers[$pid]);
                $message = "Worker (PID:$pid) normally exited. (Jobs:$jobStr)";
                break;
            case self::CODE_CONNECT_ERROR:
                $message = "Worker (PID:$pid) connect to job server failed. exiting";
                $this->stopWork = true;
                break;
            default:
                $message = "Worker (PID:$pid) died unexpectedly with exit code $statusCode. (Jobs:$jobStr)";
                break;
        }

        $this->log($message, self::LOG_PROC_INFO);
    }

    /**
     * checkEnvironment
     */
    protected function checkEnvironment()
    {
        $e1 = function_exists('posix_kill');
        $e2 = function_exists('pcntl_fork');

        if (!$e1 || !$e2) {
            $e1t = $e1 ? 'yes' : 'no';
            $e2t = $e2 ? 'yes' : 'no';

            $this->stdout(
                "ERROR: Run worker manager of the current system. the posix($e1t),pcntl($e2t) extensions is required.\n",
                true,
                -500
            );
        }
    }

    /**
     * Handles anything we need to do when we are shutting down
     */
    public function __destruct()
    {
        // master
        if ($this->isMaster) {

            // delPidFile
            $this->delPidFile();

            // close logFileHandle
            if ($this->logFileHandle) {
                fclose($this->logFileHandle);

                $this->logFileHandle = null;
            }

            $this->log('All workers stopped', self::LOG_PROC_INFO);
            $this->log("Manager stopped\n", self::LOG_PROC_INFO);

            // worker
        } elseif ($this->isWorker) {
            // $this->log("Worker stopped(PID:{$this->pid})", self::LOG_PROC_INFO);
        }

        $this->clear($this->isMaster);
    }

//////////////////////////////////////////////////////////////////////
/// getter/setter method
//////////////////////////////////////////////////////////////////////

    /**
     * @return array
     */
    public static function getLevels()
    {
        return self::$levels;
    }

    /**
     * @return array
     */
    public static function getDefaultJobOpt()
    {
        return self::$defaultJobOpt;
    }

    /**
     * @return string
     */
    public function getFullScript()
    {
        return $this->fullScript;
    }

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
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getShowName()
    {
        return $this->name ? "({$this->name})" : '';
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
            if (isset($config['jobs']) && is_array($config['jobs'])) {
                $this->setJobsOpts($config['jobs']);
            }

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
     * @return string
     */
    public function getPidRole()
    {
        return $this->isMaster ? 'Master' : 'Worker';
    }

    /**
     * @param string $pidFile
     * @return int
     */
    protected function getPidFromFile($pidFile)
    {
        if ($pidFile && file_exists($pidFile)) {
            return (int)trim(file_get_contents($pidFile));
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
     * @return bool
     */
    public function isDaemon()
    {
        return $this->config['daemon'];
    }

    /**
     * @return int
     */
    public function getVerbose()
    {
        return $this->verbose;
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
    public function getJobsOpts()
    {
        return $this->jobsOpts;
    }

    /**
     * @param array $optsList
     */
    public function setJobsOpts(array $optsList)
    {
        $this->jobsOpts = array_merge($this->jobsOpts, $optsList);
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

    /**
     * @return array
     */
    public function getMeta()
    {
        return $this->meta;
    }
}
