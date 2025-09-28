## 窗口类

> use Kingbes\PebView\Window;

## 方法

### 创建一个窗口对象

构造函数
 - `new Window` 创建一个窗口对象。
    参数
  - `bool` `$debug` 是否开启debug模式 默认：false
    返回
  - `Window` 返回一个窗口对象

用法：
```PHP
$win = new Window(true);
```

### 销毁窗口

公共函数
 - `destroy` 销毁窗口
    参数
  - 无
    返回
  - `void` 无返回值

用法：
```PHP
// 必须在 run() 方法之后调用
$win->destroy();
```

### 运行窗口

公共函数
 - `run` 运行窗口
    参数
  - 无
    返回
  - `Window` 返回窗口对象

用法：
```PHP
// 必须在 setHtml() 或者 navigate() 方法之后调用
$win->run();
```

### 终止窗口

公共函数
 - `terminate` 终止窗口
    参数
  - 无
    返回
  - `void` 无返回值

用法：
```PHP
// 用于关闭应用程序
$win->terminate();
```

### 分发

在具有运行/事件循环的线程上安排要调用的函数。
使用此功能例如可以用于与库或原生对象进行交互。

公共函数
 - `dispatch` 分发函数
    参数
  - `callable` `$callback` 要调用的函数
    返回
  - `Window` 返回窗口对象

用法：
```PHP
// 用于与库或原生对象进行交互
$win->dispatch(function($win, $arg) {
    // 与库或原生对象进行交互
    // $win 是窗口对象
    // $arg 是传递给 dispatch() 方法的参数
});
```

### 设置窗口图标

windows 要求ico格式;Linux 要求png格式;MacOs 要求ico格式
一般只有Windows 系统会奏效，Linux和macox并没有这个效果
 
公共函数
 - `setIcon` 设置窗口图标
    参数
  - `string` `$path` 图标文件路径
    返回
  - `Window` 返回窗口对象

用法：
```PHP
// 必须在 run() 方法之前调用
$win->setIcon("path/to/icon.ico");
```

### 设置窗口标题

公共函数
 - `setTitle` 设置窗口标题
    参数
  - `string` `$title` 窗口标题名称
    返回
  - `Window` 返回窗口对象

用法：
```PHP
// 必须在 run() 方法之前调用
$win->setTitle("PebView");
```

### 设置窗口大小

公共函数
 - `setSize` 设置窗口大小
    参数
  - `int` `$width` 窗口宽度
  - `int` `$height` 窗口高度
  - `WindowHint` `$hint` 窗口提示 默认 - WindowHint::None
    返回
  - `Window` 返回窗口对象

用法：
```PHP
// 必须在 run() 方法之前调用
$win->setSize(800, 600, WindowHint::None);
```

### 初始化js

会在window.onload之前加载js代码

公共函数
 - `init` 初始化js
    参数
  - `string` `$js` js代码
    返回
  - `Window` 返回窗口对象

用法：
```PHP
// 必须在 run() 方法之前调用
$win->init("console.log('hello PebView!');");
```

### 执行js代码

公共函数
 - `eval` 执行js代码
    参数
  - `string` `$js` js代码
    返回
  - `Window` 返回窗口对象

用法：
```PHP
$win->eval("console.log('hello PebView!');");
```

### 设置窗口HTML内容

公共函数
 - `setHtml` 设置窗口HTML内容
    参数
  - `string` `$html` HTML内容
    返回
  - `Window` 返回窗口对象

用法：
```PHP
// 必须在 run() 方法之前调用 和 navigate() 方法 只能选一个
$win->setHtml("<h1>hello PebView!</h1>");
```

### 导航窗口到指定url

公共函数
 - `navigate` 导航窗口到指定url
    参数
  - `string` `$url` url地址
    返回
  - `Window` 返回窗口对象

用法：
```PHP
// 必须在 run() 方法之前调用 和 setHtml() 方法 只能选一个
$win->navigate("https://www.baidu.com");
```

### 绑定js函数

公共函数
 - `bind` 绑定js函数
    参数
  - `string` `$name` 函数名称
  - `callable` `$callback` 要调用的函数
    返回
  - `Window` 返回窗口对象

用法：
```PHP
$win->bind("hello", function(...$params) {
    // $win 是窗口对象
    // $params 是传递给 hello() 方法的参数
    // 可以使用 $params[0], $params[1], ... 来访问参数
    // 例如：$params[0] 是第一个参数，$params[1] 是第二个参数，以此类推
    return $params[0] . " " . $params[1];
});
```

### 解绑js函数

公共函数
 - `unbind` 解绑js函数
    参数
  - `string` `$name` 函数名称
    返回
  - `Window` 返回窗口对象

用法：
```PHP
$win->unbind("hello");
```

### 设置窗口关闭事件

公共函数
 - `setCloseCallback` 设置窗口关闭事件
    参数
  - `callable` `$callback` 要调用的函数
    返回
  - `Window` 返回窗口对象

用法：
```PHP
$win->setCloseCallback(function($win) {
    // $win 是窗口对象
    // 可以在关闭窗口时执行一些操作
    return true; // 返回 true 表示允许关闭窗口
    return false; // 返回 false 表示不允许关闭窗口
});
```

### 窗口显示

公共函数
 - `show` 窗口显示
    参数
  - 无
    返回
  - `Window` 返回窗口对象

用法：
```PHP
$win->show();
```

### 窗口隐藏

公共函数
 - `hide` 窗口隐藏
    参数
  - 无
    返回
  - `Window` 返回窗口对象

用法：
```PHP
$win->hide();
```

### 创建托盘

windows 要求ico格式;Linux 要求png格式;MacOs 要求ico格式

公共函数
 - `tray` 创建托盘
    参数
  - `string` `$path` 图标文件路径
    返回
  - `Window` 返回窗口对象

用法：
```PHP
// 必须在 run() 方法之前调用
$win->tray("path/to/icon.ico");
```

### 托盘菜单

公共函数
 - `trayMenu` 创建托盘菜单
    参数
  - `array` `$menu` 菜单数组
    返回
  - `Window` 返回窗口对象

用法：
```PHP
// 必须在 run() 方法之前调用
$win->trayMenu([
    "text" => "menu1", // 菜单名称
    "disabled" => 0, // 是否可点击 0可点击
    "cb" => function($win) { // 点击菜单时调用的函数
        // $win 是窗口对象
        // 可以在点击菜单时执行一些操作
    },
    "text" => "menu2",
    "cb" => function($win) {
        // $win 是窗口对象
        // 可以在点击菜单时执行一些操作
    },
    ...
]);
```