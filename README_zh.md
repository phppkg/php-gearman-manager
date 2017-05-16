# gearman worker manager

php 的 gearman workers 管理工具。

可同时启动并管理多个gearman worker,可以设置每个worker的最大执行时间和最大job执行数量，到达设定值后。会自动重启worker，防止进程僵死

学习并参考自项目 **[brianlmoon/GearmanManager](https://github.com/brianlmoon/GearmanManager)**, 非常感谢这个项目.

add some feature:

- Code is easier to read and understand
- Can support `reload` `restart` `stop` command

> 只支持 linux 环境， 需要php的 `pcntl` `posix` 扩展

## 工具命令

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
