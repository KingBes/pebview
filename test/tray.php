<?php

// 根据你的实际情况，修改下面的路径
require dirname(__DIR__) . "/vendor/autoload.php";

use Kingbes\PebView\Tray;

$tray = Tray::create([
    'icon' => __DIR__ . DIRECTORY_SEPARATOR . 'php.ico',
    'menu' => [
        [
            'text' => '退出',
            'cb' => function ($menu) {
                Tray::close();
            }
        ],
    ]
]);

// var_dump($tray);

while (Tray::loop(1)) {
    // 处理事件
    echo "loop\n";
}
