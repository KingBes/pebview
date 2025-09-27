# pebview

> 一个跨平台webview 组件 ,它允许在自身原生 GUI 窗口中显示 HTML 内容。它让您可以在桌面应用程序中使用WEB技术，同时隐藏 GUI 依赖浏览器的事实。

## 安装

```bash
composer require kingbes/pebview
```

### 示例

```PHP
// 根据你的实际情况，修改下面的路径
require "/vendor/autoload.php";

use Kingbes\PebView\Window; // 引入 Window 类

// 创建一个窗口
$win = new Window();
$win->setTitle("PebView") // 设置窗口标题
    ->tray(__DIR__ . "/php.ico") // 创建托盘
    ->setHtml( // 设置窗口的 HTML 内容
        <<<HTML
    <h1>hello PebView!</h1>
HTML)
    // 运行窗口
    ->run()
    // 销毁窗口
    ->destroy();
```