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
     * @param array $payload
     * @return bool
     */
    protected function dataValidate(array &$payload)
    {
        if (!isset($payload['_uri']) || $payload['_uri']) {
            return false;
        }

        if (isset($payload['_method'])) {
            $this->method = trim($payload['_method']);
        }

        $this->baseUrl = \Ugs::$app->params['inner_api']['appv2'];
        $this->path = trim($payload['_uri']);

        unset($payload['_uri']);
        return true;
    }
}
