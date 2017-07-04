<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/5/11
 * Time: 下午11:01
 */

namespace inhere\gearman\tools;

/**
 * Class FileLogger
 *
 * @usage create job logger in entry file
 *
 * ```
 * FileLogger::create(__DIR__ . '/logs/jobs', FileLogger::SPLIT_DAY);
 *
 * ... in logic file
 *
 * FileLogger::info('message', ['data'], 'test_job');
 * FileLogger::err('message', ['data'], 'test_job');
 * ```
 *
 * @package inhere\gearman\tools
 *
 * @method static debug($msg, array $data = [], $filename = 'default.log')
 * @method static info($msg, array $data = [], $filename = 'default.log')
 * @method static notice($msg, array $data = [], $filename = 'default.log')
 * @method static warning($msg, array $data = [], $filename = 'default.log')
 * @method static error($msg, array $data = [], $filename = 'default.log')
 */
class FileLogger
{
    /**
     * Log file save type.
     */
    const SPLIT_NO = '';
    const SPLIT_DAY = 'day';
    const SPLIT_HOUR = 'hour';

    /**
     * @var static
     */
    private static $instance;

    /**
     * @var array
     */
    protected static $allow = ['debug', 'info', 'notice', 'warning', 'error'];

    /**
     * @var array
     */
    private static $cache = [];

    /**
     * @var int
     */
    private static $count = 0;

    /**
     * @var bool
     */
    private static $shutdownRegistered = false;

    /**
     * @param string $basePath
     * @param string $splitType
     * @return FileLogger|static
     */
    public static function create($basePath, $splitType = '')
    {
        if (!self::$instance) {
            self::$instance = new self($basePath, $splitType);
        }

        return self::$instance;
    }

    /**
     * @return FileLogger
     */
    public static function instance()
    {
        return self::$instance;
    }

    /**
     * @param string $method
     * @param array $args
     * @return bool|int
     */
    public static function __callStatic($method, array $args)
    {
        if (!self::$instance) {
            throw new \RuntimeException('Please init logger instance on before usage.');
        }

        if (in_array($method, static::$allow)) {
            $data = isset($args[1]) ? $args[1] : [];
            $filename = isset($args[2]) ? $args[2] : 'default.log';

            return self::$instance->log($args[0], $data, $filename, $method);
        }

        throw new \RuntimeException("Call unknown static method: $method.");
    }

    /**
     * alias of `warning()`
     * @param string $msg
     * @param array $data
     * @param string $filename
     * @return bool|int
     */
    public static function warn($msg, array $data = [], $filename = 'default.log')
    {
        return self::$instance->log($msg, $data, $filename, 'warning');
    }

    /**
     * alias of `error()`
     * @param string $msg
     * @param array $data
     * @param string $filename
     * @return bool|int
     */
    public static function err($msg, array $data = [], $filename = 'default.log')
    {
        return self::$instance->log($msg, $data, $filename, 'error');
    }

    /**
     * @var string
     */
    private $basePath;

    /**
     * @var string
     */
    private $splitType = '';

    /**
     * 日志写入阀值
     *  即是除了手动调用 self::flush() 之外，当 self::$cache 存储到了阀值时，就会自动写入一次
     *  设为 0 则是每次记录都立即写入文件
     * @var int
     */
    private $threshold = 100;

    /**
     * FileLogger constructor.
     * @param string $basePath
     * @param string $splitType
     */
    private function __construct($basePath, $threshold = 100, $splitType = '')
    {
        $this->basePath = $basePath;

        if ($splitType && !in_array($splitType, [self::SPLIT_DAY, self::SPLIT_HOUR])) {
            $splitType = self::SPLIT_DAY;
        }

        $this->threshold = (int)$threshold;
        $this->splitType = $splitType;

        // register shutdown function
        if (!self::$shutdownRegistered) {
            register_shutdown_function(function () {
                // make regular flush before other shutdown functions, which allows session data collection and so on
                self::flush();

                // make sure log entries written by shutdown functions are also flushed
                // ensure "flush()" is called last when there are multiple shutdown functions
                register_shutdown_function([self::class, 'flush'], true);
            });

            self::$shutdownRegistered = true;
        }
    }

    /**
     * log data to file
     * @param $msg
     * @param array $data
     * @param string $filename
     * @param string $type
     * @return bool|int
     */
    public function log($msg, array $data = [], $filename = 'default.log', $type = 'info')
    {
        $log = sprintf(
            '[%s] [%s] %s %s' . PHP_EOL,
            date('Y-m-d H:i:s'), strtoupper($type), $msg, $data ? json_encode($data) : ''
        );

        self::$cache[$filename][] = $log;
        self::$count++;

        if (self::$count >= $this->threshold) {
            self::flush();
        }

        return true;
    }

    /**
     * flush log to file
     * @return bool
     */
    public static function flush()
    {
        if (!self::$instance || !self::$cache) {
            return true;
        }

        foreach (self::$cache as $filename => $logs) {
            $file = self::$instance->genLogFile($filename, true);
            $content = '';

            foreach ($logs as $log) {
                $content .= $log;
            }

            $fileHandle = fopen($file, 'ab+');

            if (flock($fileHandle, LOCK_EX)) {
                fwrite($fileHandle, $content);
                flock($fileHandle, LOCK_UN); // release the lock
            }

            fclose($fileHandle);
        }

        self::$cache = [];
        self::$count = 0;
        return true;
    }

    /**
     * gen real LogFile
     * @param string $filename
     * @param bool $createDir
     * @return string
     */
    protected function genLogFile($filename, $createDir = false)
    {
        $file = $this->basePath . '/' . $filename;

        // log split type
        if (!$type = $this->splitType) {
            return $file;
        }

        $info = pathinfo($file);
        $dir = $info['dirname'];
        $name = isset($info['filename']) ? $info['filename'] : 'default';
        $ext = isset($info['extension']) ? $info['extension'] : 'log';

        if ($type === self::SPLIT_DAY) {
            $str = date('Ymd');
        } else {
            $str = date('Ymd_H');
        }

        if ($createDir && !is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return "{$dir}/{$name}_{$str}.{$ext}";
    }
}
