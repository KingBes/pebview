# PebView 测试与示例

`test/` 下的文件按前缀分成三组，全部平铺在这一层（这样现有 demo 里的
`dirname(__DIR__) . "/vendor/autoload.php"` 和 `__DIR__ . "/php.ico"` 都照常成立）。

| 前缀 | 是什么 | 谁执行 |
| --- | --- | --- |
| `auto-NN-*.php` | 自动断言测试（纯逻辑，不创建窗口） | 无人值守 |
| `auto-win-NN-*.php` | 自动断言测试（会创建真实窗口） | 需要 `--windows` 显式开启 |
| `demo-*.php` | 交互式演示，靠人观察效果 | 人工 |

早先就有的 `demo.php` / `min.php` / `theme.php` 保持原样未动。

---

## 跑法

### 自动组

```bash
# 默认：只跑纯逻辑组，几秒跑完，任何环境都稳定
php -d extension=ffi -d ffi.enable=1 test/auto.php

# 额外把窗口组也跑上（慢，且可能因环境问题超时）
php -d extension=ffi -d ffi.enable=1 test/auto.php --windows
```

退出码：`0` = 全部通过；`1` = 有失败或超时。

**必须带 `-d extension=ffi -d ffi.enable=1`** —— 本机 PHP 默认没开 FFI。
不带也能跑，但涉及动态库的用例会逐条跳过（原因会打印出来）。

单独重跑某一个用例文件：

```bash
php -d extension=ffi -d ffi.enable=1 test/auto-run-one.php auto-03-encode-result.php
```

### 交互组

```bash
php -d extension=ffi -d ffi.enable=1 test/demo-bind.php
php -d extension=ffi -d ffi.enable=1 test/demo-dialog.php
php -d extension=ffi -d ffi.enable=1 test/demo-toast.php
php -d extension=ffi -d ffi.enable=1 test/demo-tray.php
php -d extension=ffi -d ffi.enable=1 test/demo-titlebar.php
php -d extension=ffi -d ffi.enable=1 test/demo-run-loop.php
```

---

## 自动组覆盖了什么

| 文件 | 覆盖内容 |
| --- | --- |
| `auto-01-enums.php` | 4 个枚举的全部取值（直接透传给 C ABI，错一位整条链就错） |
| `auto-02-ffi-contract.php` | C ABI 契约：`Base::ffi()` 能加载 = 头文件里 30 个函数全部解析成功；再拿 `source/exports.def` 逐个探测 |
| `auto-03-encode-result.php` | `bind` 回调返回值编码的全部边界（falsy / 转义 / INF、NAN） |
| `auto-04-parse-rgb.php` | 颜色字符串解析的边界 |
| `auto-05-dialog-contract.php` | `Dialog` 三个方法的公开签名 |
| `auto-06-toast-contract.php` | `Toast::show` 的公开签名 + FFI 符号可调用 |
| `auto-win-01-lifecycle.php` | 窗口创建/配置/销毁、`$tray` 初始状态、旧崩溃路径 |
| `auto-win-02-setters.php` | 各 setter 返回 self、4 个 `WindowHint` |
| `auto-win-03-icon.php` | `setIcon` 正常 + 文件不存在抛异常 |
| `auto-win-04-titlebar.php` | 标题栏换肤的跨平台能力矩阵、非法颜色抛异常 |
| `auto-win-05-bind.php` | `bind` / `unBind` 返回 self、幂等边界 |
| `auto-win-06-tray.php` | 托盘创建、菜单四个字段、空菜单边界 |
| `auto-win-07-error-paths.php` | 错误路径集中档（什么情况抛什么异常） |
| `auto-win-08-multi-window.php` | 多窗口连续性 |
| `auto-win-09-custom-titlebar.php` | 自定义标题栏：非客户区归零 / 还原、最大化状态三边一致、状态回调、beginDrag 契约 |

### 为什么分两组、为什么每个文件独立子进程

本机实测结论（Windows）：

1. **在同一个 PHP 进程里连续创建/销毁窗口，累积到第 7 个左右时 `destroy()` 会随机死锁。**
   原因是 WebView2 异步初始化还没完成窗口就被销毁了。两轮窗口操作之间插入约 150ms
   间隔后，15 个连续实例可以全部跑通（`Harness::$SETTLE_US` 就是干这个的）。
2. 即便如此，**窗口操作仍然慢且偶发卡住**。所以：
   - 默认只跑不创建窗口的纯逻辑组 —— 5 秒内跑完，稳定；
   - 每个用例文件都在**独立子进程**里执行，并带 30 秒超时 —— 单个文件卡住只影响它自己，
     而且会被明确报成"超时"而不是整体静默挂死。

---

## 交互组各自看什么

| 文件 | 演示什么 | 观察点 | 退出方式 |
| --- | --- | --- | --- |
| `demo-dialog.php` | `Dialog::msg`（3 level × 3 按钮）、`prompt`、`file`（3 个 action + filters） | 按钮返回值、prompt 回读、文件路径、filters 是否生效 | 逐个点掉，自然结束 |
| `demo-toast.php` | 真实系统通知（带/不带图标、中文、错误图标路径） | 通知是否出现、标题正文图标、返回值 | 无阻塞 |
| `demo-tray.php` | 托盘 + 菜单（显示/隐藏/勾选项/禁用项/通知/退出） | 托盘图标、勾选态、禁用项是否点不动 | 托盘「退出」或直接关窗 |
| `demo-titlebar.php` | 5 种标题栏**颜色**配置，每 3 秒切一次 | 标题栏颜色是否真的变（Windows 上最完整） | 约 15 秒后自动结束 |
| `demo-custom-titlebar.php` | **自绘标题栏**：HTML 画一条 36px 的栏 + 三个按钮，接到 minimize/toggleMaximize/close | 系统标题栏是否消失、能否拖动/双击最大化/贴边、按钮是否可点、最大化时状态是否同步 | 点右上角关闭按钮 |
| `demo-run-loop.php` | `run()` + `setCloseCallback`（第一次拒绝关闭）+ `dispatch` | 关闭被拒绝、dispatch 是否执行 | 连点两次 X，或托盘退出 |
| `demo-bind.php` | JS 真的 await 各种返回值 | 哪些 resolve、哪些 reject、有没有一直 pending 的 | 直接关窗 |

交互组普遍采用**三重保底**退出（避免"关窗被拦截导致挂死"）：
关窗默认放行 → 托盘菜单必带「退出」→ JS 定时器兜底自动退出。

> 文档里刻意**不**推荐用 `timeout` 命令包一层 —— 在 Git Bash 下 `timeout`
> 会撞上 Windows 自带的 `timeout.exe`，行为完全不同。

---

## 本机已知限制（如实标注，不是给自己开脱）

| 现象 | 说明 |
| --- | --- |
| WebView2 不渲染页面内容 | 这台机器的既有环境问题。改动前的旧动态库对照过，行为一致，与库本身无关。表现为：窗口能显示、标题栏正常，但 HTML 内容区是空白、页面里的按钮点不到。 |
| `run()` 不返回 | 同上。所以自动组完全不调用 `run()`，相关验证都在交互组。 |
| 窗口操作慢且偶发卡死 | 见上文"为什么分两组"。这也是窗口组默认不跑的原因。 |
| `theme.php` 在本机跑不通 | 它靠 `exec('reg query ...')` 读注册表，而本机安全策略拦截 `reg.exe`。这个脚本与 PebView 无关，属既有文件，保持原样。 |
| 弹出窗口会闪 | 自动组每次 `new Window()` 都会短暂显示窗口（`webview_create` 内部就 `ShowWindow(SW_SHOW)`）。用例里创建后会立刻 `hide()`，但仍可能闪一下。 |

**在 WebView2 正常的桌面环境上**，上面这几条多半都不成立，`--windows` 与交互组
应该都能顺畅跑通。如果你在别的机器上跑出不同结果，那正是我们需要知道的信号。

---

## 加新用例的约定

1. **一个文件只负责一个功能域**，别把几十条断言堆在一起（想加就新建 `auto-NN-*.php`，
   编号接着往后排；需要窗口的用 `auto-win-NN-*.php`）。
2. **跳过必须写原因**：`skip()` 的 `$reason` 是必填参数，省略不了。
   跳过而不写原因，等于把没验证的东西伪装成通过。
3. **跨平台断言要按 `PHP_OS_FAMILY` 分支**，否则换个平台跑就是假失败。
4. **不要碰 `run()`**，也不要真弹模态对话框 / 真发系统通知 —— 那些放交互组。
5. **窗口操作之间要留间隔**：同一条用例里连续操作多个窗口时调 `$T->settle()`。
