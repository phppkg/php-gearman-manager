<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/5/9
 * Time: 下午10:55
 */

namespace inhere\gearman\tools;

use inhere\gearman\Helper;
use inhere\gearman\BaseManager;

/**
 * Class WebPanelHandler
 * @package inhere\gearman\tools
 */
class WebPanelHandler
{
    /**
     * @var array
     */
    private $routes = [];

    /**
     * @var array
     */
    private $config = [
        'basePath' => '',
        'logPath' => '',
        'logFileName' => 'manager_%s.log',
    ];

    /**
     * WebPanelHandler constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * @param $name
     * @param null $default
     * @return mixed
     */
    public function get($name, $default = null)
    {
        return Helper::get($name, $default);
    }

    /**
     * @param $name
     * @param null $default
     * @return mixed
     */
    public function getServerValue($name, $default = null)
    {
        return Helper::getServerValue($name, $default);
    }

    /**
     * @param $view
     * @param array $data
     */
    protected function render($view, array $data = [])
    {
        Helper::render($this->config['basePath'] . $view, $data);
    }

    /**
     * @param array $data
     * @param int $code
     * @param string $msg
     */
    protected function outJson(array $data = [], $code = 0, $msg = 'successful')
    {
        Helper::outJson($data, $code, $msg);
    }

    /**
     * @param array $routes
     */
    public function setRoutes(array $routes)
    {
        $this->routes = $routes;
    }

    /**
     * @param string $route
     */
    public function dispatch($route)
    {
        $method = 'indexAction';

        if (isset($this->routes[$route])) {
            $method = $this->routes[$route] . 'Action';
        }

        $this->$method();
    }

/////////////////////////////////////////////////////////////////////
/// actions
/////////////////////////////////////////////////////////////////////

    /**
     * index
     */
    public function indexAction()
    {
        $this->render('/views/index.html');
    }

    public function projInfoAction()
    {
        $this->outJson([
            'version' => BaseManager::VERSION,
            'github' => 'http://github.com/inhere/php-gearman-manager',
            'gitosc' => 'http://git.oschina.net/inhere/php-gearman-manager',
        ]);
    }

    public function serverInfoAction()
    {
        $servers = $this->get('servers', []);

        if (!$servers) {
            $this->outJson([], __LINE__, 'Please provide server info!');
        }

        $monitor = new Monitor([
            'servers' => json_decode($servers, true),
    //       'servers' => [
    //           [
    //               'name' => 'test',
    //               'address' => '10.0.0.2:4730',
    //           ],
    //           [
    //               'name' => 'product',
    //               'address' => '10.0.0.1:4730',
    //           ]
    //       ]
        ]);

        $this->outJson([
           'servers' => $monitor->getServersData(),
           'statusInfo' => $monitor->getFunctionData(),
           'workersInfo' => $monitor->getWorkersData(),
        ]);
    }

    public function logInfoAction()
    {
        $date = $this->get('date', date('Y-m-d'));

        if (!$date) {
            $this->outJson([], __LINE__, 'Please provide the date to want see log!');
        }

        $realName = sprintf($this->config['logFileName'], $date);
        $file = $this->config['logPath']. $realName;

        if (!is_file($file)) {
            $this->outJson([], __LINE__, "Log file not exists of the date: $date");
        }

        $lp = new LogParser($file);

        // var_dump($lp->getWorkerStartTimes(),$lp->getTypeCounts(),$lp->getJobsInfo());

        $this->outJson([
            'startTimes' => $lp->getWorkerStartTimes(),
            'typeCounts' => $lp->getTypeCounts(),
            'jobsInfo' => $lp->getJobsInfo(),
        ]);
    }

    public function jobDetailAction()
    {
        $date = $this->get('date', date('Y-m-d'));
        $jobId = $this->get('jobId');

        if (!$date || !$jobId) {
            $this->outJson([], __LINE__, 'Please provide the date and jobId!');
        }

        $realName = sprintf($this->config['logFileName'], $date);
        $file = $this->config['logPath']. $realName;

        if (!is_file($file)) {
            $this->outJson([], __LINE__, "Log file not exists of the date: $date");
        }

        $lp = new LogParser($file);

        $this->outJson([
            'detail' => $lp->getJobDetail($jobId)
        ]);
    }
}
