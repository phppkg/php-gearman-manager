<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-05-16
 * Time: 13:44
 */

namespace inhere\gearman\jobs;

/**
 * Class StdRequestProxyJob - 通用的项目请求代理job handler
 *
 * usage:
 *
 * ```
 * $mgr->addHandler('user_api', new StdRequestProxyJob('http://user.domain.com'));
 * $mgr->addHandler('goods_api', new StdRequestProxyJob('http://goods.domain.com'));
 * ```
 *
 * in client:
 *
 * ```
 * $client->doBackground('user_api', [
 *     '_uri' => '/update-info', // will request: http://user.domain.com/update-info
 *     'userId' => 123,
 *     // ... ...
 * ]);
 * ```
 *
 * @package inhere\gearman\jobs
 */
class StdRequestProxyJob extends RequestProxyJob
{
    /**
     * StdRequestProxyJob constructor.
     * @param $baseUrl
     */
    public function __construct($baseUrl)
    {
        $this->baseUrl = $baseUrl;

        parent::__construct();
    }

    /**
     * @param array $payload
     * @return bool
     */
    protected function beforeSend(array &$payload)
    {
        if (!isset($payload['_uri']) || $payload['_uri']) {
            return false;
        }

        if (isset($payload['_method'])) {
            $this->method = trim($payload['_method']);
        }

        $this->path = trim($payload['_uri']);

        unset($payload['_uri']);
        return true;
    }
}
