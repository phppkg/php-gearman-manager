<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/5/9
 * Time: 下午10:55
 */

namespace inhere\gearman\tools;

class Telnet
{
    const DRIVER_SOCKET = 'socket';
    const DRIVER_STREAM = 'stream';
    const DRIVER_FSOCK = 'fsock';

    /**
     * @var array
     */
    private static $availableDrivers = [
        'socket' => 'socket_create',
        'stream' => 'stream_socket_client',
        'fsock' => 'fsockopen',
    ];

    /**
     * @var resource
     */
    private $sock;

    /**
     * @var string
     */
    private $driver;

    /**
     * @var string
     */
    private $host;

    /**
     * @var int
     */
    private $port = 80;


    /**
     * @var array
     */
    private $config = [
        'driver' => '', // 'fsock' 'stream' 'socket'. if is empry, will auto select.

        // 设置阻塞或者非阻塞模式
        'blocking' => true,

        // 10s
        'timeout' => 10,

        // max wait time 180s, when into the interactive environment
        'max_wait_time' => 180,
    ];

    /**
     * Telnet constructor.
     * @param string $host
     * @param int $port
     */
    public function __construct($host = '127.0.0.1', $port = 80, array $config = [])
    {
        $this->host = $host;
        $this->port = $port;

        $this->setConfig($config);

        $this->connect();
    }

    protected function connect()
    {
        $driver = $this->config['driver'];

        if (!$driver || !isset(self::$availableDrivers[$driver])) {
            foreach (self::$availableDrivers as $name => $funcName) {
                if (function_exists($funcName)) {
                    $driver = $name;
                }
            }
        }

        $this->driver = $driver;
        $host = $this->host;
        $port = $this->port;

        switch ($driver) {
            case self::DRIVER_SOCKET:
                $this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                if ($this->sock === false) {
                    throw new \RuntimeException('socket_create() failed. Reason: ' . socket_strerror(socket_last_error()), -400);
                }

                if (socket_connect($this->sock, $host, $port) === false) {
                    throw new \RuntimeException('socket_connect() failed. Reason: ' . socket_strerror(socket_last_error()), -450);
                }

                if ($this->config['blocking']) {
                    socket_set_block($this->sock);
                } else {
                    socket_set_nonblock($this->sock);
                }

                socket_set_timeout($this->sock, $this->config['timeout'], 0);
                break;

            case self::DRIVER_STREAM:
                $this->sock = stream_socket_client(
                    "tcp://{$host}:{$port}",
                    $errNo,
                    $errStr,
                    $this->config['timeout'],
                    STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT
                );
                break;

            case self::DRIVER_FSOCK:
            default:
                $this->sock = fsockopen($host, $port, $errNo, $errStr, $this->config['timeout']);
                break;
        }

        if (!$this->sock) {
            throw new \RuntimeException("Connect to the {$host}:{$port} failed, ERROR: $errNo - $errStr", -500);
        }

        if ($driver !== self::DRIVER_SOCKET) {
            stream_set_blocking($this->sock, $this->config['blocking'] ? 1 : 0);
            stream_set_timeout($this->sock, $this->config['timeout'], 0);
        }
    }

    /**
     * send command
     * @param  string $command
     * @return string|int|false
     */
    public function command($command, $readResult = true, $readSize = 1024)
    {
        $len = $this->write(trim($command) . "\r\n");

        if ($readResult) {
            return $this->read($readSize);
        }

        return $len;
    }

    /**
     * into interactive environment
     */
    public function interactive()
    {
        $activeTime = time();
        echo "welcome !\n: ";

        while (true) {
            if ($input = fgets(\STDIN)) {
                echo $this->command($input) . PHP_EOL;
            }

            $activeTime = time();
            usleep(50000);
        }
    }

    /**
     * write
     * @param  string $buffer
     * @return int|false
     */
    public function write($buffer)
    {
        if ($this->driver === self::DRIVER_SOCKET) {
            return socket_write($this->sock, $buffer, strlen($buffer));
        } else {
            // $buffer = str_replace(chr(255), chr(255) . chr(255), $buffer);
            return fwrite($this->sock, $buffer);
        }
    }

    /**
     * read
     * @param  integer $size
     * @return string|false
     */
    public function read($size = 1024)
    {
        if ($this->driver === self::DRIVER_SOCKET) {
            return socket_read($this->sock, $size);
        } else {
            return fread($this->sock, $size);
        }
    }

    public function close()
    {
        if ($this->sock) {
            if ($this->driver === self::DRIVER_SOCKET) {
                socket_close($this->sock);
            } else {
                fclose($this->sock);
            }

            $this->sock = null;
        }
    }

    /**
     * @return array
     */
    public function getSock()
    {
        return $this->sock;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param array $config
     */
    public function setConfig(array $config)
    {
        if ($config) {
            $this->config = array_merge($this->config, $config);
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
