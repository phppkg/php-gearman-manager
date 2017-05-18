<?php
/**
 * @from https://github.com/matyhtf/framework/blob/master/libs/Swoole/Queue/MsgQ.php
 */

/**
 * 是对Linux Sysv系统消息队列的封装，单台服务器推荐使用
 * @author Tianfeng.Han
 */
class MsgQ implements \Swoole\IFace\Queue
{
    protected $msgId;
    protected $msgType = 1;

    /**
     * @var resource
     */
    protected $msg;

    public function __construct($config)
    {
        if (!empty($config['msgId'])) {
            $this->msgId = $config['msgId'];
        } else {
            $this->msgId = ftok(__FILE__, 0);
        }

        if (isset($config['msgType'])) {
            $this->msgType = (int)$config['msgType'];
        }

        $this->msg = msg_get_queue($this->msgId);
    }

    public function pop()
    {
        $ret = msg_receive($this->msg, 0, $this->msgType, 65525, $data);

        if ($ret) {
            return $data;
        }

        return false;
    }

    public function push($data)
    {
        return msg_send($this->msg, $this->msgType, $data);
    }

    public function getStat()
    {
        return msg_stat_queue($this->msgId);
    }
}
