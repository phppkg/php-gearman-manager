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
    private $routes = [];

    private $config = [
        'basePath' => '',
        'logPath' => '',
        'logFileName' => 'manager_%s.log',
    ];

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    public function get($name, $default = null)
    {
        return Helper::get($name, $default);
    }

    public function getServerValue($name, $default = null)
    {
        return Helper::getServerValue($name, $default);
    }

    protected function render($view, array $data = [])
    {
        Helper::render($this->config['basePath'] . $view, $data);
    }

    protected function outJson(array $data = [], $code = 0, $msg = 'successful')
    {
        Helper::outJson($data, $code, $msg);
    }

    public function setRoutes(array $routes)
    {
        $this->routes = $routes;
    }

    public function dispatch($route)
    {
        $method = 'indexAction';

        if (isset($this->routes[$route])) {
            $method = $this->routes[$route] . 'Action';
        }

        $this->$method();
    }

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
            'servers' => $servers,
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

        $realName = sprintf('manager_%s.log', $date);
        $file = ROOT_PATH . '/examples/logs/' . $realName;

        $lp = new \inhere\gearman\tools\LogParser($file);

        var_dump($lp->getWorkerStartTimes(),$lp->getTypeCounts(),$lp->getJobsInfo(),$lp->getJobDetail('H:afa64bc05a60:2'));
    }
}
