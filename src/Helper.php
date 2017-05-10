<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017-04-27
 * Time: 14:28
 */

namespace inhere\gearman;

/**
 * Class Helper
 * @package inhere\gearman
 */
class Helper
{
    /**
     * @return bool
     */
    public static function isMac()
    {
        return stripos(PHP_OS, 'Darwin') !== false;
    }

    /**
     * Parses $GLOBALS['argv'] for parameters and assigns them to an array.
     *
     * Supports:
     * -e
     * -e <value>
     * --long-param
     * --long-param=<value>
     * --long-param <value>
     * <value>
     *
     * @link http://php.net/manual/zh/function.getopt.php#83414
     * @param array $noOpts List of parameters without values
     * @return array
     */
    public static function parseParameters($noOpts = [])
    {
        $result = [];
        $params = $GLOBALS['argv'];
        reset($params);

        while (list(, $p) = each($params)) {
            if ($p{0} === '-') {
                $pName = substr($p, 1);
                $value = true;

                if ($pName{0} === '-') {
                    // long-opt (--<param>)
                    $pName = substr($pName, 1);

                    if (strpos($p, '=') !== false) {
                        // value specified inline (--<param>=<value>)
                        list($pName, $value) = explode('=', substr($p, 2), 2);
                    }
                }

                // check if next parameter is a descriptor or a value
                $nxParam = current($params);

                if (!in_array($pName, $noOpts) && $value === true && $nxParam !== false && $nxParam{0} !== '-') {
                    list(, $value) = each($params);
                }

                $result[$pName] = $value;
            } else {
                // param doesn't belong to any option
                $result[] = $p;
            }
        }

        return $result;
    }

    /**
     * like print_r
     */
    public static function printR()
    {
        $args = func_get_args();

        ob_start();

        foreach ($args as $arg) {
            print_r($arg);
        }

        $string = ob_get_clean();

        echo preg_replace("/Array\n\s+\(/", 'Array (', $string);
    }

    private static $_colors = [
        'light_red'    => "[1;31m",
        'light_green'  => "[1;32m",
        'yellow'       => "[1;33m",
        'light_blue'   => "[1;34m",
        'magenta'      => "[1;35m",
        'light_cyan'   => "[1;36m",
        'white'        => "[1;37m",
        'normal'       => "[0m",
        'black'        => "[0;30m",
        'red'          => "[0;31m",
        'green'        => "[0;32m",
        'brown'        => "[0;33m",
        'blue'         => "[0;34m",
        'cyan'         => "[0;36m",
        'bold'         => "[1m",
        'underscore'   => "[4m",
        'reverse'      => "[7m",
    ];

    public static function cliColor($text, $color = 'normal', $return = true)
    {
        $out = self::$_colors[$color];

        if(!isset(self::$_colors[$color])) {
            $out = "[0m";
        }

        $result = chr(27). "$out$text" . chr(27) . chr(27) . "[0m". chr(27);

        if($return ){
            return $result;
        }

        echo $result;
    }
}
