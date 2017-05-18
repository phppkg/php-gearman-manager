<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/5/9
 * Time: 下午10:55
 */

namespace inhere\gearman\tools;

/**
 * Class TelnetGmdServer
 * @package inhere\gearman\tools
 */
class TelnetGmdServer extends Telnet
{
    public $statusFields = ['job_name', 'in_queue', 'jobs_running', 'capable_workers'];

    public function __construct($host = '127.0.0.1', $port = 4730, array $config = [])
    {
        parent::__construct($host, $port, $config);
    }

    public function version()
    {
        // eg: OK 1.1.12
        $version = $this->command('version');

        return substr(trim($version), 2);
    }

    public function statusInfo()
    {
        /* @see $statusFields
        test_job            1       0       0
        sendRetryPassEmail  0       0       3
        writeErrorLog       0       0       3
        .
         */
        $status = $this->command('status');

        if (!($status = trim($status, ".\n")) || 0 === strpos($status, 'ERR')) {
            return null;
        }

        if (!$rows = explode("\n", $status)) {
            return null;
        }

        $data = [];

        foreach ($rows as $row) {
            list(
                $item['job_name'],
                $item['in_queue'],
                $item['jobs_running'],
                $item['capable_workers']
            ) = explode(' ', trim(preg_replace('/\s+/', ' ', $row)));

            $data[] = $item;
        }

        return $data;
    }

    public $workerFields = ['id', 'ip', 'job_names'];

    public function workersInfo()
    {
        /*
        38 127.0.0.1 - : appV2RequestProxy
        36 127.0.0.1 - : queue_youguoquan
        37 127.0.0.1 - : queue_manage
        .
         */
        $workers = $this->command('workers');

        if (!($workers = trim($workers, ".\n")) || 0 === strpos($workers, 'ERR')) {
            return null;
        }

        if (!$rows = explode("\n", $workers)) {
            return null;
        }

        $data = [];
        foreach ($rows as $row) {
            list($idIp, $jobNames) = explode('- :', $row);
            list($id, $ip) = explode(' ', trim($idIp));
            $jobNames = explode(' ', trim($jobNames));

            $data[] = [
                'id' => $id,
                'ip' => $ip,
                'job_names' => $jobNames,
            ];
        }

        return $data;
    }
}
