<?php
// 根据你的实际情况，修改下面的路径
require dirname(__DIR__) . "/vendor/autoload.php";

use Kingbes\PebView\Window; // 引入 Window 类
use Kingbes\PebView\Dialog; // 引入 Dialog 类

// 创建一个窗口
$pv = Window::create(true);
Window::setIcon($pv, __DIR__ . "/php.ico"); // 设置窗口图标
// 绑定一个事件
Window::bind($pv, "demo", function (...$params) {
    // 这里可以写你要执行的代码
    Dialog::msg("Hello PebView!"); // 弹出一个消息框
});

// 设置窗口的 HTML 内容
Window::setHtml(
    $pv,
    <<<HTML
    <h1>hello</h1><button onClick="onBtn()">click</button>
    <script>
    function onBtn() {
    demo();
    }
    </script>
HTML
);

// 运行窗口
Window::run($pv);
// 销毁窗口
Window::destroy($pv);
