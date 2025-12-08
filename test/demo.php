<?php
// 根据你的实际情况，修改下面的路径
require dirname(__DIR__) . "/vendor/autoload.php";

use Kingbes\PebView\Window; // 引入 Window 类
use Kingbes\PebView\Dialog; // 引入 Dialog 类
use Kingbes\PebView\Toast; // 引入 Toast 类


// 创建一个窗口
$win = new Window();

// 创建一个 toast 通知
$toast = new Toast();
$toast->setAppName("Test App")
    ->setAppUserModelId("Test App", "", "");
if (!$toast->initialize()) {
    throw new \Exception("Failed to initialize WinToast.\n");
}

$win->setSize(800, 600) // 设置窗口大小
    ->setIcon(__DIR__ . "/php.ico") // 设置窗口图标
    ->setTitle("PebView") // 设置窗口标题
    ->setCloseCallback(function ($win) { // 设置窗口关闭事件
        $win->hide();
        return false;
    })
    ->tray(__DIR__ . "/php.ico") // 创建托盘
    ->trayMenu([ // 添加托盘菜单
        [
            "text" => "打开窗口",
            "cb" => function ($win) {
                $win->show();
            }
        ],
        [
            "text" => "关闭窗口",
            "cb" => function ($win) {
                $win->terminate();
            }
        ]
    ])
    ->bind("demo", function (...$params) use ($toast) { // 绑定一个事件

        $toast->createTemplate(1)
            ->setFirstLine("First Line")
            ->setSecondLine("Second Line")
            ->setImagePath(__DIR__ . "/icon.png")
            ->show();
        // 这里可以写你要执行的代码
        return "等待结束";
    })
    ->setHtml( // 设置窗口的 HTML 内容
        <<<HTML
    <body style="background-color: #f0f0f0;">
    <h1>hello</h1><button onClick="onBtn()">click</button>
    <script>
    async function onBtn() {
        // 不等待 demo 事件执行完成
        demo();
    }
    </script>
    </body>
HTML
    )
    // 运行窗口
    ->run()
    // 销毁窗口
    ->destroy();
