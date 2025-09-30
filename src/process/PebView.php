<?php

namespace Kingbes\PebView\process;

use Kingbes\PebView\Window;

class PebView
{
    private function getNaviget(): string
    {
        $webman = config("process.webman.listen");
        // 将字符串 0.0.0.0 替换为 127.0.0.1
        $webman = str_replace('0.0.0.0', '127.0.0.1', $webman);
        return $webman;
    }

    public function onWorkerStart()
    {
        // 定义状态文件路径
        $status_file = runtime_path() . DIRECTORY_SEPARATOR . '/windows/status_file';
        // 判断链接是否可访问
        $naviget = $this->getNaviget();
        while (1) {
            if (@fopen($naviget, 'r')) {
                break;
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
