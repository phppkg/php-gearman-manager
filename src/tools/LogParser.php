<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/5/22
 * Time: 下午9:22
 */

namespace inhere\gearman\tools;

/**
 * Class LogParser
 * @package inhere\gearman\tools
 */
class LogParser
{
    /**
     * @var string
     */
    private $file;

    /**
     * @var array
     */
    private $config = [
        'cacheData' => false,
        'cacheDir' => '',
    ];

    public function __construct($file, array $config = [])
    {
        if (!is_file($file)) {
            throw new \InvalidArgumentException("File not exists. FILE: $file");
        }

        $this->file = $file;
        $this->config = array_merge($this->config, $config);
    }

    /**
     * @param string $type
     * @return array
     */
    public function getInfo($type = 'started')
    {
        $kw = null;

        switch ($type) {
            case 'started': // started jobs
                $kw = 'Starting job';
                break;
            case 'completed': // completed jobs
                $kw = 'completed';
                break;
            case 'failed': // Failed jobs
                $kw = 'Failed';
                break;
            default:
                break;
        }

        if (!$kw) {
            return [];
        }

        $data = [];

        if ($lines = $this->getMatchedLines($kw)) {
            $data = $this->parseLines($lines);

            if ($this->config['cacheData'] && ($dir = $this->config['cacheDir'])) {
                $filename = basename($this->file);
                file_put_contents($dir . '/' . $filename, $data);
            }
        }

        return $data;
    }

    public function getMatchedLines($keyword)
    {
        // started jobs
        exec("cat $this->file | grep '$keyword'", $lines);

        return $lines;
    }

    /**
     * @return int
     */
    public function getWorkerStartTimes()
    {
        return (int)exec("cat $this->file | grep 'Started worker #0' | wc -l");
    }

    /**
     * @param $lines
     * @return null
     */
    public function parseLines(array $lines)
    {
        if (!$lines) {
            return null;
        }

        $data = [];
        foreach ($lines as $line) {
            $info = explode('] ', trim($line));
            list($role, $pid) = explode(':', $info[1]);
            $data[] = [
                'time' => $info[0],
                'role' => $role,
                'pid' => $pid,
                'level' => $info[2],
            ];
        }

        return $data;
    }

    protected function cacheResult()
    {

    }

}
