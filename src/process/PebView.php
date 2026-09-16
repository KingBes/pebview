<?php

namespace Kingbes\PebView\process;

use Kingbes\PebView\Window;

class PebView
{
    /** 等待 webman 的 HTTP 服务就绪的最长秒数 */
    private const READY_TIMEOUT = 30;

    /**
     * 取出 webman 的监听地址，并换成可用作客户端连接的形式
     *
     * @return string 例如 http://127.0.0.1:8787
     * @throws \RuntimeException 配置缺失或不是非空字符串时抛出
     */
    private function getNaviget(): string
    {
        $listen = config("process.webman.listen");
        if (!is_string($listen) || $listen === '') {
            throw new \RuntimeException(
                'PebView 进程取不到 webman 的监听地址（config("process.webman.listen")），'
                . '请确认 webman 进程名与配置路径一致。'
            );
        }
        // 0.0.0.0 不能作为客户端连接地址，换成 127.0.0.1
        return str_replace('0.0.0.0', '127.0.0.1', $listen);
    }

    public function onWorkerStart()
    {
        // 定义状态文件路径
        $status_file = runtime_path() . DIRECTORY_SEPARATOR . '/windows/status_file';

        // 等 webman 的 HTTP 服务就绪后再开窗，避免窗口加载到一个还没起来的服务。
        // 必须带超时：地址写错时原来的 while(1) 会静默死循环，既不报错也没有日志。
        $naviget = $this->getNaviget();
        $deadline = time() + self::READY_TIMEOUT;
        while (true) {
            if (@fopen($naviget, 'r')) {
                break;
            }
            if (time() >= $deadline) {
                throw new \RuntimeException(
                    "等待 {$naviget} 就绪超时（" . self::READY_TIMEOUT . ' 秒），PebView 窗口未启动。'
                );
            }
            sleep(1);
        }
        $config = config("plugin.kingbes.pebview.pebview");
        $win = new Window($config["debug"]);
        if (trim($config["init"]) !== "") {
            $win->init($config["init"]);
        }
        $win->setTitle($config["title"])
            ->setSize($config["size"][0], $config["size"][1], $config["size"][2])
            ->setIcon($config["icon"])
            ->setCloseCallback($config["closeCallback"])
            ->tray($config["tray"]["icon"])
            ->trayMenu($config["tray"]["menu"]);
        foreach ($config["bind"] as $bind) {
            $win->bind($bind["name"], $bind["cb"]);
        }
        $win->navigate($naviget)
            ->run()
            ->destroy();
        // 判断是否windows系统
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            file_put_contents($status_file, '0');
        } else {
            posix_kill(posix_getppid(), SIGINT);
        }
    }
}
