## 窗口类

> use Kingbes\PebView\Window;

## 方法

### 创建一个窗口对象

构造函数
 - `new Window` 创建一个窗口对象。
    参数
  - `bool` `$debug` 是否开启debug模式 默认：true
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

### 设置标题栏外观

设置原生标题栏的浅色 / 深色以及配色。**不调用时保持系统默认外观，行为与以前完全一致。**

各平台支持范围不同，不支持的组合会抛异常，而不是静默无效：

| 平台 | 浅色 / 深色 | 自定义配色 |
| --- | --- | --- |
| Windows | 支持 | 支持（需要 Win11；Win10 只认浅色 / 深色） |
| macOS | 支持 | 不支持，标题栏由系统绘制，AppKit 没有公开接口 |
| Linux | 不支持，无法只改标题栏深浅（GTK 改主题是全局的） | 支持，会切换为 CSD 自绘标题栏（GtkHeaderBar + CSS） |

公共函数
 - `setTitlebarTheme` 设置标题栏外观
    参数
  - `?bool` `$dark` true 深色，false 浅色，null 跟随系统（默认 null）
  - `?string` `$caption` 标题栏底色，`#RRGGBB` 或 `RRGGBB`，null 用系统默认（默认 null）
  - `?string` `$text` 标题栏文字色，格式同上，null 按深色 / 浅色自动选择（默认 null）
    返回
  - `Window` 返回窗口对象

用法：
```PHP
// 跟随系统
$win->setTitlebarTheme();

// 只要深色标题栏
$win->setTitlebarTheme(dark: true);

// 自定义配色（Windows 11 / Linux CSD）
$win->setTitlebarTheme(dark: true, caption: "#1F2430", text: "#FFD166");
```

颜色格式非法会抛 `InvalidArgumentException`；窗口不存在或当前平台不支持该组合会抛 `RuntimeException`。

### 自定义标题栏（真正无边框 + JS 驱动缩放）

**这一节和上一节是两回事**：上一节只改标题栏的**颜色 / 深浅**，标题栏本身还是系统画的；
这一节把标题栏**让出来**，由你用 HTML 自己画。

打开之后，窗口顶部 `$height` 像素改由你用 HTML 绘制，系统不再画标题栏；
窗口**真正无边框**——`WM_NCCALCSIZE` 直接 return 0，客户区填满整个窗口，内容贴到窗口顶边、
**零顶部默认边距**（不留任何会自动缩放的边框）。边缘调整大小改由 JS 在 `mousedown` 里
判定边缘、调 `beginResize()` 发起（见下），因为 WebView2 子窗口吃掉鼠标、系统命中测试抓不到边缘。

切换自定义标题栏**不会改变客户区尺寸**：`setSize()` 说的始终是内容区尺寸，
开/关前后拿到的客户区一样大（内部按 caption 的差值补偿了窗口外框）。

各平台实现与差异：

| 平台 | 做法 | 拖动 | 说明 |
| --- | --- | --- | --- |
| Windows | `WM_NCCALCSIZE` return 0，客户区填满整窗（真正无边框、零顶部边距）| 由 `beginDrag()` 统一发起 | 边缘缩放由 `beginResize()` 统一发起（JS 判定边缘）；双击最大化 / Aero 贴边原生生效 |
| macOS | 标题栏透明化 + 内容下延（`FullSizeContentView`） | 系统处理 | 红绿灯与拖动都是原生的；`$controlsWidth` 无意义（红绿灯位置由系统决定） |
| Linux | `gtk_window_set_decorated(FALSE)` | **需要 JS 配合** | GTK 收不到被 webview 吃掉的鼠标事件，要在 `mousedown` 里调 `beginDrag()` |

> **三平台同一句 JS**：拖动由 `beginDrag()` 发起、缩放由 `beginResize(edge)` 发起，页面侧都不按平台分支。
> WebView2 把网页内容放在一串铺满客户区的子窗口里（`webview_widget → Chrome_WidgetWin_* →
> Chrome_RenderWidgetHostHWND`），鼠标事件被子窗口认领，宿主窗口的 `WM_NCHITTEST` 根本不会被系统问到
> —— 所以不能靠命中测试认领标题栏 / 边缘区域。JS 在标题栏 `mousedown` 里把 `event.screenX / screenY`
> 传给 `beginDrag()`，在四条边/角 `mousedown` 里把判定出的 `edge` 传给 `beginResize()`，由各平台原生侧
> 按最合适的方式发起（Windows 拖动等价合成 `WM_NCLBUTTONDOWN(HTCAPTION)`、缩放发 `WM_SYSCOMMAND(SC_SIZE|edge)`，
> 都进入系统模态循环，贴边 / 双击最大化原生）。按钮区用 `stopPropagation` 挡掉 `mousedown`，`$controlsWidth` 仅作保留参数。

公共函数
 - `setCustomTitlebar` 启用自定义标题栏
    参数
  - `?int` `$height` 标题栏高度（px）。`null` 或 `<= 0` 表示关闭、退回系统标题栏
  - `int` `$controlsWidth` 右侧按钮区宽度（px），该区域内不响应拖动；0 表示整条可拖。
    仅作保留参数，按钮排除由 webview 侧 `stopPropagation` 处理
  - `bool` `$drag` 标题栏区域是否可拖动
    返回
  - `Window` 返回窗口对象

 - `minimize` 最小化窗口
    返回
  - `Window` 返回窗口对象

 - `toggleMaximize` 最大化 / 还原（切换）
    返回
  - `bool` 切换之后是否处于最大化

 - `isMaximized` 当前是否处于最大化
    返回
  - `bool`

 - `onStateChange` 监听最大化 / 还原（只在状态真的变化时回调一次）
    参数
  - `callable` `$callable` 形如 `function (Window $win, bool $maximized): void`
    返回
  - `Window` 返回窗口对象

 - `beginDrag` 发起窗口拖动（屏幕坐标）。三平台统一：Windows 合成 `WM_NCLBUTTONDOWN(HTCAPTION)`，
   Linux 用 `gdk_window_begin_move_drag`，macOS 按光标位移搬窗口。返回 `self`，窗口存在时不会抛异常；
   左键已松开返回 `4`（当作 no-op）。
    参数
  - `int` `$x` 屏幕坐标 X
  - `int` `$y` 屏幕坐标 Y

 - `beginResize` 发起窗口缩放（边/角编码）。与 `beginDrag` 同一思路，因为 WebView2 子窗口吃掉鼠标、
   系统命中测试抓不到边缘，所以缩放由 JS 在 `mousedown` 里判定边缘后发起。三平台统一：Windows 发
   `WM_SYSCOMMAND(SC_SIZE | edge)`，Linux 用 `gdk_window_begin_resize_drag`，macOS 按光标位移改窗口 frame。
   返回 `self`，窗口存在时不会抛异常；左键已松开返回 `4`（当作 no-op）。
    参数
  - `int` `$edge` 边/角编码（WMSZ_*）：1=左 2=右 3=上 4=左上 5=右上 6=下 7=左下 8=右下

用法：

```PHP
$win->setCustomTitlebar(36, 138);   // 36px 高，右侧 138px 留给三个按钮

// 按钮直接走现有的 bind，不需要新 API
$win->bind('tbMinimize', fn() => $win->minimize());
$win->bind('tbMaximize', fn() => $win->toggleMaximize());
$win->bind('tbClose',    fn() => $win->terminate());
$win->bind('tbIsMax',    fn() => $win->isMaximized());

// 最大化时窗口会有圆角与内缩，标题栏要跟着调整
$win->onStateChange(function ($win, $maximized) {
    $win->eval('window.__onMaximized(' . ($maximized ? 'true' : 'false') . ')');
});

// 拖动统一由 JS 发起（三平台同一句），这里转调 beginDrag
$win->bind('tbBeginDrag', function (int $x, int $y) use ($win) {
    $win->beginDrag($x, $y);   // 在页面 mousedown 里传 event.screenX / screenY
    return true;
});

// 缩放也由 JS 发起：页面在四条边/角 mousedown 里判定出 edge，这里转调 beginResize
$win->bind('tbBeginResize', function (int $edge) use ($win) {
    $win->beginResize($edge);  // edge 编码 1..8（WMSZ_*：左/右/上/左上/右上/下/左下/右下）
    return true;
});
```

页面里那条标题栏（三平台都能拖，按钮用 `stopPropagation` 退出拖动）；四条边/角在 `mousedown` 里
判定 edge 后调 `tbBeginResize(edge)`（缩放也由 JS 统一发起，不按平台分支）：

```HTML
<!-- 高度要等于 setCustomTitlebar() 的 $height -->
<div id="bar" style="height:36px; cursor:move">
    <span>自己的标题</span>

    <!-- 按钮区：在 mousedown 上 stopPropagation 退出拖动/缩放（见 demo） -->
    <div id="controls">
        <button onclick="tbMinimize()">－</button>
        <button onclick="tbMaximize()">□</button>
        <button onclick="tbClose()">×</button>
    </div>
</div>

<script>
  const M = 6; // 缩放区宽度（px）
  // 由坐标算出落在哪条边/角（WMSZ_* 编码，与 C 侧一致）：0=不在边缘
  function edgeOf(x, y) {
    const w = innerWidth, h = innerHeight;
    const L = x < M, R = x > w - M, T = y < M, B = y > h - M;
    if (T && L) return 4; if (T && R) return 5; if (B && L) return 7; if (B && R) return 8;
    if (L) return 1; if (R) return 2; if (T) return 3; if (B) return 6; return 0;
  }
  // 标题栏 mousedown 拖动；四条边/角 mousedown 缩放（按钮区已 stopPropagation）
  document.body.addEventListener('mousedown', e => {
    if (e.button !== 0) return;
    if (e.target.closest('#bar') || e.target.closest('#controls')) return;
    const edge = edgeOf(e.clientX, e.clientY);
    if (edge) tbBeginResize(edge);
  });
  const bar = document.getElementById('bar');
  bar.addEventListener('mousedown', e => { if (e.button === 0) tbBeginDrag(e.screenX, e.screenY); });
  bar.addEventListener('dblclick', () => tbMaximize());
</script>
```

完整示例见 `test/demo-custom-titlebar.php`。

> `$height` 必须和页面里那条标题栏的实际高度一致 —— 这个数字决定系统让出多少像素。
>
> 拖动走 `beginDrag()`、缩放走 `beginResize(edge)`，页面侧都不按平台分支；`$controlsWidth` 只是保留参数，
> 按钮排除在 webview 侧用 `stopPropagation` 处理。关闭（`$height` 传 null）后窗口
> 会完整退回系统标题栏，可以随时开关。

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
 - `unBind` 解绑js函数
    参数
  - `string` `$name` 函数名称
    返回
  - `Window` 返回窗口对象

用法：
```PHP
$win->unBind("hello");
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
// 注意：每个菜单项本身是一个数组，整体是"数组的数组"
$win->trayMenu([
    [
        "text" => "menu1", // 菜单名称
        "disabled" => 0, // 0 可点击，1 禁用
        "checked" => 0, // 0 未勾选，1 勾选
        "cb" => function ($win) { // 点击菜单时调用的函数
            // $win 是窗口对象
            // 可以在点击菜单时执行一些操作
        },
    ],
    [
        "text" => "menu2",
        "cb" => function ($win) {
            // $win 是窗口对象
            // 可以在点击菜单时执行一些操作
        },
    ],
]);
```