<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-04-27
 * Time: 9:56
 */

namespace inhere\gearman\client;
use inhere\gearman\traits\EventTrait;

/**
 * Class JobClient
 * @package inhere\gearman\client
 *
 * @method string doHigh($function_name, $workload, $unique = null)
 * @method string doNormal($function_name, $workload, $unique = null)
 * @method string doLow($function_name, $workload, $unique = null)
 *
 * @method string doHighBackground($function_name, $workload, $unique = null)
 * @method string doBackground($function_name, $workload, $unique = null)
 * @method string doLowBackground($function_name, $workload, $unique = null)
 *
 * @method array jobStatus($job_handle)
 */
class JobClient
{
    use EventTrait;

    /**
     * Events list
     */
    const EVENT_BEFORE_DO = 'beforeDo';
    const EVENT_AFTER_DO = 'afterDo';

    const EVENT_BEFORE_ADD = 'beforeAdd';
    const EVENT_AFTER_ADD = 'afterAdd';

    /**
     * @var bool
     */
    public $enable = true;

    /**
     * @var bool
     */
    public $debug = false;

    /**
     * @var \GearmanClient
     */
    private $client;

    /**
     * allow 'json','php'
     * @var string
     */
    public $serializer = 'json';

    /**
     * @var array|string
     * [
     *  '10.0.0.1', // use default port 4730
     *  '10.0.0.2:7003'
     * ]
     */
    public $servers = [];

    /**
     * @var array
     */
    private static $frontMethods = [
        'doHigh', 'doNormal', 'doLow',
    ];

    /**
     * @var array
     */
    private static $backMethods = [
        'doBackground', 'doHighBackground', 'doLowBackground',
    ];

    /**
     * JobClient constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $properties = ['enable', 'debug', 'servers', 'serializer'];

        foreach ($properties as $property) {
            if (isset($config[$property])) {
                $this->$property = $config[$property];
            }
        }

        $this->init();
    }

    /**
     * init
     */
    public function init()
    {
        if (!$this->enable) {
            return false;
        }

        try {
            $client = new \GearmanClient();

            if ($servers = implode(',', (array)$this->servers)) {
                $this->stdout("connect to the servers {$servers}");
                $client->addServers($servers);
            } else {
                $this->stdout("connect to the default server 127.0.0.1:4730");
                $client->addServer();
            }
        } catch (\Exception $e) {
            $this->stdout("connect to the gearmand server error: {$e->getMessage()}", true, -500);
        }

        if ($er = $client->error()) {
            $this->stdout("connect to the gearmand server error: {$er}", true, -500);
        }

        $this->enable = true;
        $this->client = $client;

        return true;
    }

    /**
     * do frontend job
     * @param $funcName
     * @param $workload
     * @param null $unique
     * @param string $clientMethod
     * @return mixed
     */
    public function doJob($funcName, $workload, $unique = null, $clientMethod = 'doNormal')
    {
        if (!$this->enable) {
            return null;
        }

        $this->trigger(self::EVENT_BEFORE_DO, [$funcName, $workload, $clientMethod]);

        if (is_array($workload) || is_object($workload)) {
            if ($this->serializer === 'json') {
                $workload = json_encode($workload);
            } elseif (is_callable($this->serializer)) { // custom serializer
                $workload = call_user_func($this->serializer, $workload);
            } else { //  default use 'php' -- serialize
                $workload = serialize($workload);
            }
        }

        $result = $this->client->$clientMethod($funcName, $workload, $unique);

        $this->trigger(self::EVENT_AFTER_DO, [$funcName, $workload, $result]);

        return $result;
    }

    /**
     * add background job
     * @param string $funcName
     * @param string $workload
     * @param null $unique
     * @param string $clientMethod
     * @return mixed
     */
    public function addJob($funcName, $workload, $unique = null, $clientMethod = 'doBackground')
    {
        if (!$this->enable) {
            return null;
        }

        $this->trigger(self::EVENT_BEFORE_ADD, [$funcName, $workload, $clientMethod]);

        if (is_array($workload) || is_object($workload)) {
            if ($this->serializer === 'json') {
                $workload = json_encode($workload);
            } elseif (is_callable($this->serializer)) { // custom serializer
                $workload = call_user_func($this->serializer, $workload);
            } else { //  default use 'php' -- serialize
                $workload = serialize($workload);
            }
        }

        $this->stdout("push a job to the server.Job: $funcName Type: $clientMethod Data: $workload");

        $ret = $this->client->$clientMethod($funcName, $workload, $unique);

        if (in_array($clientMethod, self::$frontMethods, true)) {
            return $ret;
        }

        $stat = $this->client->jobStatus($ret);

        $this->trigger(self::EVENT_BEFORE_ADD, [$funcName, $workload, $stat]);

        return !$stat[0];// bool
    }

    /**
     * @return array|string
     */
    public function getServers()
    {
        return $this->servers;
    }

    /**
     * @param array|string $servers
     */
    public function setServers($servers)
    {
        $this->servers = $servers;
    }

    /**
     * @return \GearmanClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @param \GearmanClient $client
     */
    public function setClient(\GearmanClient $client)
    {
        $this->client = $client;
    }

    /**
     * @param string $name
     * @param array $params
     * @return mixed
     * @throws \RuntimeException
     */
    public function __call($name, $params)
    {
        if (!$this->enable) {
            return null;
        }

        if (in_array($name, self::$frontMethods, true)) {
            return $this->doJob(
                $params[0],
                isset($params[1]) ? $params[1] : '',
                isset($params[2]) ? $params[2] : null,
                $name
            );
        }

        if (in_array($name, self::$backMethods, true)) {
            return $this->addJob(
                $params[0],
                isset($params[1]) ? $params[1] : '',
                isset($params[2]) ? $params[2] : null,
                $name
            );
        }

        if (method_exists($this->client, $name)) {
            return call_user_func_array([$this->client, $name], $params);
        }

        throw new \RuntimeException('Calling unknown method: ' . get_class($this) . "::$name()");
    }

    /**
     * Logs data to stdout
     * @param string $logString
     * @param bool $nl
     * @param bool|int $quit
     * @return null
     */
    protected function stdout($logString, $nl = true, $quit = false)
    {
        if (!$this->debug) {
            return null;
        }

        fwrite(\STDOUT, $logString . ($nl ? PHP_EOL : ''));

        if (($isTrue = true === $quit) || is_int($quit)) {
            $code = $isTrue ? 0 : $quit;
            exit($code);
        }

        return null;
    }
}
