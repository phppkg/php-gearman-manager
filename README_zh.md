# gearman worker manager

php 的 gearman workers 管理工具。

可同时启动并管理多个gearman worker,可以设置每个worker的最大执行时间和最大job执行数量，到达设定值后。会自动重启worker，防止进程僵死

学习并参考自项目 **[brianlmoon/GearmanManager](https://github.com/brianlmoon/GearmanManager)**, 非常感谢这个项目.

add some feature:

- Code is easier to read and understand
- Can support `reload` `restart` `stop` `status` command

> 只支持 linux 环境， 需要php的 `pcntl` `posix` 扩展

## 基本命令

### 启动

```bash
// 启动
php bin/manager.php 
// 强制后台运行
php bin/manager.php --daemon 
```

### 停止 

```bash 
php bin/manager.php stop
```

### 重启

```bash
php bin/manager.php restart
```

### 其他

```bash
// 查看帮助信息 可看到更多的可用选项
php bin/manager.php --help

// 打印manager配置信息
php bin/manager.php -D
```

## 命令以及选项说明

在命令行里使用 `php examples/gwm.php -h` 可以查看到所有的命令和选项信息

```
root@php5-dev:/var/www/phplang/library/gearman-manager# php examples/gwm.php -h
Gearman worker manager(gwm) script tool. Version 0.1.0

USAGE:
  php examples/gwm.php {COMMAND} -c CONFIG [-v LEVEL] [-l LOG_FILE] [-d] [-w] [-p PID_FILE]
  php examples/gwm.php -h
  php examples/gwm.php -D

COMMANDS:
  start             Start gearman worker manager(default)
  stop              Stop running's gearman worker manager
  restart           Restart running's gearman worker manager
  reload            Reload all running workers of the manager
  status            Get gearman worker manager runtime status

SPECIAL OPTIONS:
  start/restart
    -w,--watch         Automatically watch and reload when 'loader_file' has been modify
    -d,--daemon        Daemon, detach and run in the background
       --jobs          Only register the assigned jobs, multi job name separated by commas(',')
       --no-test       Not add test handler, when job name prefix is 'test'.(eg: test_job)

  status
    --cmd COMMAND      Send command when connect to the job server. allow:status,workers.(default:status)
    --watch-status     Watch status command, will auto refresh status.

PUBLIC OPTIONS:
  -c CONFIG          Load a custom worker manager configuration file
  -s HOST[:PORT]     Connect to server HOST and optional PORT, multi server separated by commas(',')

  -n NUMBER          Start NUMBER workers that do all jobs

  -u USERNAME        Run workers as USERNAME
  -g GROUP_NAME      Run workers as user's GROUP NAME

  -l LOG_FILE        Log output to LOG_FILE or use keyword 'syslog' for syslog support
  -p PID_FILE        File to write master process ID out to

  -r NUMBER          Maximum run job iterations per worker
  -x SECONDS         Maximum seconds for a worker to live
  -t SECONDS         Number of seconds gearmand server should wait for a worker to complete work before timing out

  -v [LEVEL]         Increase verbosity level by one. (eg: -v vv | -v vvv)

  -h,--help          Shows this help information
  -V,--version       Display the version of the manager
  -D,--dump [all]    Parse the command line and config file then dump it to the screen and exit.
```


## 添加job handler(工作处理器)

方法原型：

```php
bool WorkerManager::addHandler(string $name, mixed $handler, array $opts = [])
bool WorkerManager::addFunction(string $name, mixed $handler, array $opts = []) // addHandler 的别名方法
```

参数：

- `$name` string 名称，给此工作命名
- `$handler` mixed 此工作的处理器。 可以是 函数名称，类名称，对象实例，闭包
    - 使用类名称或对象实例 时，必须是 实现了 `__invoke` 方法 或者 实现了接口 `app\gearman\JobInterface`
- `$opts` array 对当前工作的一些设置
    - `timeout` int 超时 秒
    - `worker_num` int 需要多少个worker来处理此工作
    - `focus_on` bool 需要上面的worker专注此工作，即只负责它，不再接其他的job

示例：

```php
// $mgr 是 inhere\gearman\GwManager 的实例

$mgr->addHandler('echo_job', \inhere\gearman\examples\jobs\EchoJob::class);

/**
 * test
 */
$mgr->addFunction('test_reverse', function ($workload)
{
    return ucwords(strtolower($workload));
});

```


## License

BSD
