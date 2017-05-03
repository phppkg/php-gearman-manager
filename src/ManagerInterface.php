<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/5/2
 * Time: 下午8:39
 */

namespace inhere\gearman;

/**
 * Interface ManagerInterface
 * @package inhere\gearman
 */
interface ManagerInterface
{
    const VERSION = '0.1.0';

    /**
     * Events list
     */
    const EVENT_BEFORE_PUSH = 'beforePush';
    const EVENT_AFTER_PUSH = 'afterPush';

    const EVENT_WORKER_START = 'workerStart';
    const EVENT_WORKER_EXIT = 'workerExit';

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


    const DO_ALL = 'all';

    const MIN_LIFETIME = 1800;
    const MIN_RUN_JOBS = 100;
    const MIN_JOB_TIMEOUT = 10;
    const MIN_WATCH_INTERVAL = 10;

    /**
     * some default values
     */
    const WORKER_NUM = 1;
    const JOB_TIMEOUT = 300;
    const MAX_LIFETIME = 3600;
    const MAX_RUN_JOBS = 2000;
    const RESTART_SPLAY = 600;
    const WATCH_INTERVAL = 30;

    /**
     * process exit status code.
     */
    const CODE_MANUAL_KILLED = -500;
    const CODE_NORMAL_EXITED = 0;
    const CODE_CONNECT_ERROR = 170;
    const CODE_NO_HANDLERS   = 171;
    const CODE_UNKNOWN_ERROR = 180;

    /**
     * do run manager
     */
    public function start();
}