<?php
/**
 * Created by PhpStorm.
 * User: inhere
 * Date: 2017/5/1
 * Time: 下午4:25
 */


spl_autoload_register(function($class)
{
    // e.g. "inhere\validate\ValidationTrait"
    if (0 === strpos($class,'inhere\\gearman\\')) {
        $path = str_replace('\\', '/', substr($class, strlen('inhere\\gearman\\')));
        $file = dirname(__DIR__) . "/src/{$path}.php";

        if (is_file($file)) {
            include $file;
        }
    }
});
