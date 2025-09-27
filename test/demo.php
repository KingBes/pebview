<?php
// 根据你的实际情况，修改下面的路径
require dirname(__DIR__) . "/vendor/autoload.php";

use Kingbes\PebView\Window; // 引入 Window 类
use Kingbes\PebView\Dialog; // 引入 Dialog 类

// 创建一个窗口
$win = new Window();
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
            "cb" => function ($win){
                $win->show();
            }
        ],
        [
            "text" => "关闭窗口",
            "cb" => function ($win){
                $win->terminate();
            }
        ]
    ])
    ->bind("demo", function (...$params) { // 绑定一个事件
        // 这里可以写你要执行的代码
        Dialog::msg("Hello PebView!"); // 弹出一个消息框
    })
    ->setHtml( // 设置窗口的 HTML 内容
        <<<HTML
    <h1>hello</h1><button onClick="onBtn()">click</button>
    <script>
    async function onBtn() {
        await demo();
    }
    </script>
HTML
    )
    // 运行窗口
    ->run()
    // 销毁窗口
    ->destroy();
