<?php

/**
 * Windows平台专用启动文件
 * 此文件负责在Windows环境下初始化和启动webman应用及其进程
 */

// 将当前工作目录切换到../../所在目录
chdir(dirname(dirname(dirname(__DIR__))));
// 加载Composer自动加载文件
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

// 引入必要的类
use Dotenv\Dotenv;
use support\App;
use Workerman\Worker;

// 设置PHP错误显示和报告级别
ini_set('display_errors', 'on');
error_reporting(E_ALL);

// 如果存在Dotenv类且.env文件存在，则加载环境变量
if (class_exists('Dotenv\Dotenv') && file_exists(base_path() . '/.env')) {
    // 根据Dotenv版本选择不同的加载方式
    if (method_exists('Dotenv\Dotenv', 'createUnsafeImmutable')) {
        Dotenv::createUnsafeImmutable(base_path())->load();
    } else {
        Dotenv::createMutable(base_path())->load();
    }
}

// 加载所有配置，排除'route'配置（路由配置会在各进程中单独加载）
App::loadAllConfig(['route']);

// 根据应用配置调整错误报告级别
$errorReporting = config('app.error_reporting');
if (isset($errorReporting)) {
    error_reporting($errorReporting);
}

// 定义运行时目录路径
$runtimeProcessPath = runtime_path() . DIRECTORY_SEPARATOR . '/windows';
// 需要创建的目录列表
$paths = [
    $runtimeProcessPath,     // Windows进程文件目录
    runtime_path('logs'),    // 日志目录
    runtime_path('views')    // 视图缓存目录
];

// 确保所有必要的目录存在，如果不存在则创建
foreach ($paths as $path) {
    if (!is_dir($path)) {
        mkdir($path, 0777, true); // 创建目录，递归创建父目录，权限设为0777
    }
}

// 存储需要启动的进程文件列表
$processFiles = [];

// 如果服务器配置了监听地址，则添加主启动文件
if (config('server.listen')) {
    $processFiles[] = __DIR__ . DIRECTORY_SEPARATOR . 'start.php';
}

// 处理配置文件中定义的普通进程
foreach (config('process', []) as $processName => $config) {
    $processFiles[] = write_process_file($runtimeProcessPath, $processName, '');
}

// 处理插件中的进程配置
foreach (config('plugin', []) as $firm => $projects) {
    // 处理二级插件结构 (firm.name)
    foreach ($projects as $name => $project) {
        if (!is_array($project)) {
            continue;
        }
        foreach ($project['process'] ?? [] as $processName => $config) {
            $processFiles[] = write_process_file($runtimeProcessPath, $processName, "$firm.$name");
        }
    }
    // 处理一级插件结构 (firm)
    foreach ($projects['process'] ?? [] as $processName => $config) {
        $processFiles[] = write_process_file($runtimeProcessPath, $processName, $firm);
    }
}

/**
 * 生成进程启动文件
 * 
 * @param string $runtimeProcessPath 运行时进程文件目录路径
 * @param string $processName 进程名称
 * @param string $firm 插件厂商/名称标识
 * @return string 生成的进程文件路径
 */
function write_process_file($runtimeProcessPath, $processName, $firm): string
{
    // 构建进程参数名，根据是否有厂商标识决定格式
    $processParam = $firm ? "plugin.$firm.$processName" : $processName;
    // 构建配置获取表达式
    $configParam = $firm ? "config('plugin.$firm.process')['$processName']" : "config('process')['$processName']";

    // 生成进程文件内容
    $fileContent = <<<EOF
<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Webman\Config;
use support\App;

ini_set('display_errors', 'on');
error_reporting(E_ALL);

// 如果OPcache可用，则重置OPcache缓存，确保加载最新代码
if (is_callable('opcache_reset')) {
    opcache_reset();
}

// 确保应用配置文件存在
if (!\$appConfigFile = config_path('app.php')) {
    throw new RuntimeException('Config file not found: app.php');
}
// 加载应用配置
\$appConfig = require \$appConfigFile;
// 设置默认时区
if (\$timezone = \$appConfig['default_timezone'] ?? '') {
    date_default_timezone_set(\$timezone);
}

// 加载所有配置，排除'route'配置
App::loadAllConfig(['route']);

// 启动工作进程
worker_start('$processParam', $configParam);

// 在Windows环境下（目录分隔符不是/），设置日志文件和最大包大小
if (DIRECTORY_SEPARATOR != "/") {
    Worker::\$logFile = config('server')['log_file'] ?? Worker::\$logFile;
    TcpConnection::\$defaultMaxPackageSize = config('server')['max_package_size'] ?? 10*1024*1024;
}

// 运行所有Worker
Worker::runAll();

EOF;

    // 定义进程文件路径
    $processFile = $runtimeProcessPath . DIRECTORY_SEPARATOR . "start_$processParam.php";
    // 写入进程文件内容
    file_put_contents($processFile, $fileContent);
    // 返回生成的进程文件路径
    return $processFile;
}

// 如果配置了监控进程，则创建监控实例
if ($monitorConfig = config('process.monitor.constructor')) {
    $monitorHandler = config('process.monitor.handler');
    $monitor = new $monitorHandler(...array_values($monitorConfig));
}

/**
 * 启动进程文件
 * 
 * @param array $processFiles 进程文件路径数组
 * @return resource 进程资源句柄
 */
function popen_processes($processFiles)
{
    // 构建启动命令，使用PHP二进制文件执行所有进程文件
    $cmd = '"' . PHP_BINARY . '" ' . implode(' ', $processFiles);
    // 定义文件描述符规范，将标准输入、输出和错误输出重定向
    $descriptorspec = [STDIN, STDOUT, STDOUT];
    // 启动进程，使用proc_open以避免shell解析带来的安全问题
    $resource = proc_open($cmd, $descriptorspec, $pipes, null, null, ['bypass_shell' => true]);
    // 如果进程启动失败，则退出并显示错误信息
    if (!$resource) {
        exit("Can not execute $cmd\r\n");
    }
    // 返回进程资源句柄
    return $resource;
}

// 定义Windows环境下的状态文件路径
$status_file = runtime_path() . DIRECTORY_SEPARATOR . '/windows/status_file';
// 设置状态文件权限为可读写
chmod($status_file, 0666);
// 初始化状态文件内容为空
file_put_contents($status_file, '1');

// 启动所有进程
$resource = popen_processes($processFiles);
echo "\r\n";

// 进入监控循环，每秒检查一次文件变化
while (1) {
    sleep(1);
    // 检查状态文件内容是否为0
    if (file_get_contents($status_file) == '0') {
        // 获取进程状态
        $status = proc_get_status($resource);
        $pid = $status['pid'];
        // 强制终止进程及其子进程（Windows特有命令）
        shell_exec("taskkill /F /T /PID $pid");
        // 关闭进程资源
        proc_close($resource);
        exit;
    }
    // 如果存在监控实例且检测到文件变化
    if (!empty($monitor) && $monitor->checkAllFilesChange()) {
        // 获取进程状态
        $status = proc_get_status($resource);
        $pid = $status['pid'];
        // 强制终止进程及其子进程（Windows特有命令）
        shell_exec("taskkill /F /T /PID $pid");
        // 关闭进程资源
        proc_close($resource);
        // 重新启动所有进程
        $resource = popen_processes($processFiles);
    }
}
