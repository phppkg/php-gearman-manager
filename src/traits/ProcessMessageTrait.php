<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-05-31
 * Time: 17:44
 */

namespace inhere\gearman\traits;

/**
 * Class ProcessMessageTrait - IPC
 * @package inhere\gearman\traits
 */
trait ProcessMessageTrait
{
    /**
     * pipe Handle
     * @var resource
     */
    protected $pipe;

    /**
     * @return bool
     */
    protected function createPipe()
    {
        if (!$this->config['enablePipe']) {
            return false;
        }

        //创建管道
        $pipeFile = "/tmp/{$this->name}.pipe";

        if(!file_exists($pipeFile) && !posix_mkfifo($pipeFile, 0666)){
            $this->stderr("Create the pipe failed! PATH: $pipeFile");
        }

        $this->pipe = fopen($pipeFile, 'wr');
        stream_set_blocking($this->pipe, false);  //设置成读取非阻塞

        return true;
    }

    /**
     * @param int $bufferSize
     * @return bool
     */
    protected function readMessage($bufferSize = 2048)
    {
        if (!$this->pipe) {
            return false;
        }

        // 父进程读写管道
        $string = fread($this->pipe, $bufferSize);
        $json = json_decode($string);
        $cmd = $json->command;

        if ($cmd === 'status') {
            fwrite($this->pipe, json_encode([
                'status' => 0,
                'data' => 'received data: ' . json_encode($json->data),
            ]));
        }

        return true;
    }

    /**
     * @deprecated unused
     * @param $command
     * @param $message
     * @param bool $readResult
     * @return bool|int|string
     */
    protected function sendMessage($command, $message, $readResult = true)
    {
        if (!$this->pipe) {
            return false;
        }
        // $pid = $this->masterPid;

        // 子进程读写管道
        $len = fwrite($this->pipe, json_encode([
            'command' => $command,
            'data' => $message,
        ]));

        if ($len && $readResult) {
            return fread($this->pipe, 1024);
        }

        return $len;
    }
}