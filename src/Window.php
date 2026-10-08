<?php

namespace Kingbes\PebView;

use function Kingbes\PebView\trayMenuList;

/**
 * Window 窗口类
 */
class Window extends Base
{
    /** @var \FFI\CData 窗口指针 */
    private mixed $pv;

    /**
     * @var \FFI\CData|null 托盘指针，未调用 tray() 时为 null
     *                      （C 侧 window_tray_remove / window_tray_add_menu 都判空）
     */
    public mixed $tray = null;

    public function __construct(bool $debug = true)
    {
        // Linux + PHP Fiber 协程栈 = WebKitGTK 必崩（2026-10-08 pebman-pet 实测定位）：
        // Workerman 5.2 / webman v2 会把 worker 回调包进 PHP Fiber（Worker.php:
        // `default => (new \Fiber($callback))->start()`，PHP >= 8.1 无开关），
        // 于是窗口主循环整个跑在 fiber 的 mmap 栈上。页面首次调用 bind 桥时
        // WebKitGTK 在 UI 进程惰性创建 JSC 上下文（JSGlobalContextCreateInGroup），
        // JSC::sanitizeStackForVM 发现当前栈指针不在为该线程缓存的栈边界内 →
        // WTFCrashWithInfo → 无声 SIGABRT（exit 134），master 重启 worker 无限循环。
        // 与其让用户面对神秘崩溃循环，不如显式报错并给出路。
        if (PHP_OS_FAMILY === 'Linux'
            && class_exists(\Fiber::class)
            && \Fiber::getCurrent() !== null
        ) {
            throw new \RuntimeException(
                'PebView 不能在 PHP Fiber 协程栈中创建窗口（Linux）：'
                . 'WebKitGTK 会在 fiber 栈上创建 JSC 上下文并无声 abort（exit 134）。'
                . '出路：①把窗口放到独立 PHP 子进程运行（proc_open，与 worker 用文件/HTTP 通信）——推荐；'
                . '②改用无协程宿主（Workerman 4.x / webman v1 / 普通 CLI）。'
            );
        }
        $this->pv = self::ffi()->webview_create($debug, null);
    }

    /**
     * 销毁窗口
     *
     * @return void
     * @example $win->destroy(); 必须在`run()`后面
     */
    public function destroy(): void
    {
        self::ffi()->webview_destroy($this->pv);
    }

    /**
     * 运行窗口
     *
     * @return self
     * @example $win->run(); 必须在`setHtml()`或者`navigate()`后面
     */
    public function run(): self
    {
        self::ffi()->webview_run($this->pv);
        return $this;
    }

    /**
     * 终止窗口
     *
     * @return void
     * @example $win->terminate(); 
     */
    public function terminate(): void
    {
        $this->trayRemove();
        self::ffi()->webview_terminate($this->pv);
        // 判断是否是webman框架
        /* if (function_exists("runtime_path") && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // 定义状态文件路径
            $status_file = runtime_path() . DIRECTORY_SEPARATOR . '/windows/status_file';
            // 写入状态文件
            file_put_contents($status_file, '0');
        } */
    }

    /**
     * 分发回调函数
     *
     * @param callable $callable 回调函数
     * @return void
     * @example $win->dispatch(function ($win, $arg) {
     *     // 这里可以写你要执行的代码
     * });
     */
    public function dispatch(callable $callable): self
    {
        $win = $this;
        $c_callable = function ($ptr, $arg) use ($callable, $win) {
            $callable($win, $arg);
        };
        self::ffi()->webview_dispatch($this->pv, $c_callable, null);
        return $this;
    }

    /**
     * 设置窗口图标
     *
     * @param string $icon 图标路径
     * @return self
     * @throws \RuntimeException 窗口不存在、图标文件不存在或系统不支持时抛出
     * @example $win->setIcon(图标路径); - windows 要求ico格式 - Linux 要求png格式 - MacOs ico
     */
    public function setIcon(string $icon): self
    {
        $code = self::ffi()->set_icon(self::ffi()->webview_get_window($this->pv), $icon);
        if ($code !== 0) {
            // 返回码见 source/seticon/icon.h 的 SetIconErrorCode
            throw new \RuntimeException(match ($code) {
                1 => "窗口不存在，无法设置图标: {$icon}",
                2 => "图标文件不存在: {$icon}",
                3 => "当前操作系统不支持设置窗口图标",
                default => "设置窗口图标失败（错误码 {$code}）: {$icon}",
            });
        }
        return $this;
    }

    /**
     * 设置标题栏外观（浅色 / 深色与配色）
     *
     * 各平台支持范围不同，不支持的组合会抛异常而不是静默无效：
     *   - Windows：浅/深色 + 配色（配色需要 Win11，Win10 只认浅/深色）
     *   - macOS  ：只支持浅/深色（$dark），标题栏由系统绘制，无法指定配色
     *   - Linux  ：只支持配色（$caption / $text），会切换为 CSD 自绘标题栏；
     *              只传 $dark 会抛异常，因为 GTK 无法在不影响全局的前提下
     *              单独调整标题栏深浅
     *
     * @param bool|null $dark true=深色标题栏，false=浅色，null=跟随系统
     * @param string|null $caption 标题栏底色，'#RRGGBB' 或 'RRGGBB'；null=系统默认
     * @param string|null $text 标题栏文字色，格式同上；null=按深色/浅色自动选择
     * @return self
     * @throws \RuntimeException 窗口不存在或当前平台不支持该组合时抛出
     * @throws \InvalidArgumentException 颜色不是合法的 RGB 十六进制时抛出
     * @example $win->setTitlebarTheme(dark: true); // 深色标题栏
     * @example $win->setTitlebarTheme(caption: '#1F2430', text: '#FFFFFF');
     */
    public function setTitlebarTheme(
        ?bool $dark = null,
        ?string $caption = null,
        ?string $text = null
    ): self {
        $code = self::ffi()->window_set_titlebar_theme(
            self::ffi()->webview_get_window($this->pv),
            $dark === null ? -1 : ($dark ? 1 : 0),
            self::parseRgb($caption),
            self::parseRgb($text)
        );
        if ($code !== 0) {
            // 返回码见 source/window/window.h 中 window_set_titlebar_theme 的说明
            throw new \RuntimeException(match ($code) {
                1 => '窗口不存在，无法设置标题栏外观',
                3 => '当前平台不支持这组标题栏配置（配色仅 Windows 11 / Linux CSD 可用，macOS 只支持浅深色）',
                default => "设置标题栏外观失败（错误码 {$code}）",
            });
        }
        return $this;
    }

    /**
     * 窗口透明背景（窗口层 + webview 渲染层）
     *
     * 两层一起开：窗口层进入逐像素合成（各平台配方不同），渲染层把默认背景
     * alpha 设 0 —— 页面未绘制区域输出透明像素，配 CSS 背景透明即可透出桌面。
     *
     * 各平台：
     *   - Windows：WS_EX_LAYERED + DWM 边框扩展 + WebView2 默认背景 alpha=0，
     *              随时可调；运行时过老（无 ICoreWebView2Controller2）抛异常
     *   - macOS  ：NSWindow.opaque=NO + WKWebView drawsBackground=NO（KVC），
     *              随时可调
     *   - Linux  ：需要桌面合成器，且必须在 run() 之前调用 —— GTK 的 rgba
     *              visual 只能在窗口 realize（首次显示）之前挂上，
     *              之后调用返回不支持而不是静默无效
     *
     * @param bool $enable true=开启透明，false=关闭、回到不透明
     * @return self
     * @throws \RuntimeException 窗口不存在、运行时缺能力或当前平台不支持时抛出
     * @example $win->setTransparent(true); // 在 run() 之前调用（Linux 必须）
     */
    public function setTransparent(bool $enable = true): self
    {
        $ffi = self::ffi();
        $flag = $enable ? 1 : 0;

        $code = $ffi->window_set_transparent($ffi->webview_get_window($this->pv), $flag);
        if ($code !== 0) {
            throw new \RuntimeException(match ($code) {
                1 => '窗口不存在，无法设置透明背景',
                3 => '当前平台不支持窗口透明（Linux 需要桌面合成器，且必须在 run() 之前调用）',
                default => "设置透明背景失败（错误码 {$code}）",
            });
        }

        $code = $ffi->webview_set_transparent($this->pv, $flag);
        if ($code !== 0) {
            throw new \RuntimeException(match ($code) {
                -3 => 'webview 状态无效，无法设置渲染层透明',
                -5 => '当前 WebView 运行时缺透明背景能力（Windows 需要较新的 WebView2 Runtime）',
                default => "设置渲染层透明失败（错误码 {$code}）",
            });
        }
        return $this;
    }

    /**
     * 窗口置顶
     *
     * 进入 / 退出置顶层，窗口位置、大小、激活状态都不变。
     *
     * @param bool $enable true 进入置顶层，false 回到普通 Z 层（默认 true）
     * @return self
     * @throws \RuntimeException 窗口不存在时抛出
     * @example $win->setAlwaysOnTop(true);
     */
    public function setAlwaysOnTop(bool $enable = true): self
    {
        $code = self::ffi()->window_set_always_on_top(
            self::ffi()->webview_get_window($this->pv),
            $enable ? 1 : 0
        );
        if ($code !== 0) {
            throw new \RuntimeException('窗口不存在，无法设置窗口置顶');
        }
        return $this;
    }

    /**
     * 窗口定位
     *
     * 把窗口左上角移动到屏幕坐标 (x, y)，尺寸不变。
     *
     * 坐标语义：Windows / Linux 为系统屏幕坐标；macOS 内部按主屏坐标系换算，
     * 多显示器且窗口位于副屏时可能存在偏差（已知限制，见文档）。
     *
     * @param int $x 屏幕坐标 X（窗口左上角）
     * @param int $y 屏幕坐标 Y（窗口左上角）
     * @return self
     * @throws \RuntimeException 窗口不存在时抛出
     * @example $win->setPosition(100, 100);
     */
    public function setPosition(int $x, int $y): self
    {
        $code = self::ffi()->window_set_position(
            self::ffi()->webview_get_window($this->pv),
            $x,
            $y
        );
        if ($code !== 0) {
            throw new \RuntimeException('窗口不存在，无法设置窗口位置');
        }
        return $this;
    }

    /**
     * 整窗点击穿透
     *
     * 开启后整个窗口不再接收鼠标事件（事件落到下层窗口），关闭后恢复。
     * 与 setTransparent 相互独立：两者各自翻转自己的标志位、互不影响。
     * 与区域白名单模式（setClickThroughRegions）互斥覆盖：本方法调用会
     * 退出区域模式，最后调用者获胜。
     *
     * 各平台：
     *   - Windows / macOS：随时可调
     *   - Linux：必须在窗口显示（run()）之后调用 —— GTK 的 input shape
     *     只能对已 realize 的窗口设置，提前调用抛异常而不是静默无效
     *
     * @param bool $enable true 开启穿透，false 关闭（默认 true）
     * @return self
     * @throws \RuntimeException 窗口不存在或平台 / 时机不支持时抛出
     * @example $win->setClickThrough(true);
     */
    public function setClickThrough(bool $enable = true): self
    {
        $code = self::ffi()->window_set_click_through(
            self::ffi()->webview_get_window($this->pv),
            $enable ? 1 : 0
        );
        if ($code !== 0) {
            throw new \RuntimeException(match ($code) {
                1 => '窗口不存在，无法设置点击穿透',
                3 => '当前平台或时机不支持点击穿透（Linux 需在 run() 窗口显示之后调用）',
                default => "设置点击穿透失败（错误码 {$code}）",
            });
        }
        return $this;
    }

    /**
     * 区域白名单点击穿透
     *
     * $rects 内的矩形区域正常接收鼠标，其余区域穿透到下层窗口 ——
     * 透明悬浮挂件的"真实点击穿透"就用它：内容可点、周围透明区穿透。
     * 坐标是页面 CSS px（与 DOM 布局一致）。每个矩形支持两种写法，可混用：
     *   - 列表形式：[[10, 20, 200, 40], ...]
     *   - 关联形式：[['x' => 10, 'y' => 20, 'w' => 200, 'h' => 40], ...]
     * 传空数组退出区域模式、恢复正常交互。浮点坐标自动取整（容忍
     * getBoundingClientRect 的小数）；宽高为负直接抛异常，不做 abs。
     *
     * 与 setClickThrough 互斥覆盖：任一方的调用都会撤销另一方（最后调用者获胜）。
     * 动态页面通常由 JS 采集可交互元素矩形后经 bind() 上报（见文档「区域点击穿透」）。
     *
     * 各平台生效方式：
     *   - Windows：约 30ms 轮询光标位置翻转穿透位，行为近似实时（≤50ms）
     *   - Linux  ：input shape 原生多矩形支持，即时生效；必须在 run() 之后调用
     *   - macOS  ：约 30ms 轮询（未真机验证）
     *
     * 已知局限（诚实标注）：
     *   - 矩形近似：圆角、抗锯齿边缘按外接矩形处理
     *   - canvas / video / iframe 内容不会被自动识别，需自行上报矩形
     *   - JS 上报间隔内的 DOM 变化存在竞态（按旧矩形判定点击）
     *   - 浏览器缩放非 100% 时 Windows 侧坐标换算会有偏差
     *
     * @param array $rects 矩形数组（见上），空数组 = 退出区域模式
     * @return self
     * @throws \InvalidArgumentException 矩形格式非法时抛出
     * @throws \RuntimeException 窗口不存在或平台 / 时机不支持时抛出
     * @example $win->setClickThroughRegions([['x' => 8, 'y' => 8, 'w' => 344, 'h' => 36]]);
     */
    public function setClickThroughRegions(array $rects): self
    {
        $flat = self::normalizeRects($rects);
        $n = intdiv(count($flat), 4);
        if ($n === 0) {
            $code = self::ffi()->window_set_click_through_regions(
                self::ffi()->webview_get_window($this->pv),
                null,
                0
            );
        } else {
            $total = $n * 4;
            $arr = self::ffi()->new("int[{$total}]");
            foreach ($flat as $i => $v) {
                $arr[$i] = $v;
            }
            $code = self::ffi()->window_set_click_through_regions(
                self::ffi()->webview_get_window($this->pv),
                $arr,
                $n
            );
        }
        if ($code !== 0) {
            throw new \RuntimeException(match ($code) {
                1 => '窗口不存在，无法设置区域点击穿透',
                3 => '当前平台或时机不支持区域点击穿透（Linux 需在 run() 窗口显示之后调用）',
                4 => '区域点击穿透参数非法',
                default => "设置区域点击穿透失败（错误码 {$code}）",
            });
        }
        return $this;
    }

    /**
     * 把矩形数组规范化为扁平 int 一维数组 [x,y,w,h,...]
     *
     * 接受列表形式 [x,y,w,h] 与关联形式 ['x'=>..,'y'=>..,'w'=>..,'h'=>..]，
     * 可混用；浮点四舍五入取整；宽高为负抛 InvalidArgumentException（不做 abs，
     * 边界情况显式报错而不是静默纠正）。
     *
     * @param array $rects 原始矩形数组
     * @return list<int>   扁平数组，长度 = 4 * 矩形个数
     * @throws \InvalidArgumentException 格式非法时抛出
     */
    private static function normalizeRects(array $rects): array
    {
        $flat = [];
        foreach ($rects as $i => $r) {
            if (is_array($r) && array_keys($r) === [0, 1, 2, 3]) {
                $v = [$r[0], $r[1], $r[2], $r[3]];
            } elseif (is_array($r) && isset($r['x'], $r['y'], $r['w'], $r['h'])) {
                $v = [$r['x'], $r['y'], $r['w'], $r['h']];
            } else {
                throw new \InvalidArgumentException(
                    "setClickThroughRegions 第 {$i} 个矩形格式非法（应为 [x,y,w,h] 或 x/y/w/h 关联数组）"
                );
            }
            foreach ($v as $k => $num) {
                if (!is_numeric($num)) {
                    throw new \InvalidArgumentException(
                        "setClickThroughRegions 第 {$i} 个矩形的第 {$k} 个坐标不是数字"
                    );
                }
                $flat[] = (int) round((float) $num);
            }
            if ($v[2] < 0 || $v[3] < 0) {
                throw new \InvalidArgumentException(
                    "setClickThroughRegions 第 {$i} 个矩形宽高不能为负"
                );
            }
        }
        return $flat;
    }

    /**
     * 启用自定义标题栏（真正无边框 + JS 驱动缩放）
     *
     * 注意这**不是**改颜色（那是 setTitlebarTheme），而是把标题栏让出来给你自己画：
     * 顶部 $height 像素不再由系统绘制，但系统仍然把它当"标题栏区域"对待 ——
     * 拖动、双击最大化、贴边这些还是系统行为，你只需在 HTML 里画一条同高的条、
     * 摆上自己的按钮。
     *
     * 各平台做法与能力不同：
     *   - Windows：WM_NCCALCSIZE 直接 return 0，客户区=整个窗口 —— 真正无边框，内容贴到
     *              窗口顶边、零顶部默认边距（不留任何会自动缩放的边框）。边缘调整大小改由
     *              JS 判定边缘后调 beginResize() 发起（见下），因为 WebView2 子窗口吃掉鼠标，
     *              顶层窗口在边缘收不到 WM_NCHITTEST、留边框也抓不到。拖动统一由 beginDrag() 发起。
     *              $controlsWidth 仅作保留参数；真正的按钮排除在 webview 侧用 stopPropagation 处理。
     *   - macOS  ：标题栏透明化 + 内容下延。红绿灯与拖动都是原生的，
     *              $controlsWidth 无意义（红绿灯位置由系统决定）。
     *   - Linux  ：gtk_window_set_decorated(FALSE) 去掉装饰。**拖动需要 JS 配合** ——
     *              GTK 收不到被 webview 吃掉的鼠标事件，要在 mousedown 里调 beginDrag()。
     *
     * @param int|null $height 标题栏高度（px）。null 或 <= 0 表示关闭、退回系统标题栏
     * @param int $controlsWidth 右侧按钮区宽度（px），该区域内不响应拖动；0 表示整条可拖
     * @param bool $drag 标题栏区域是否可拖动
     * @return self
     * @throws \RuntimeException 窗口不存在或当前平台不支持时抛出
     * @example $win->setCustomTitlebar(36, 138); // 36px 高，右侧 138px 放三个按钮
     */
    public function setCustomTitlebar(?int $height, int $controlsWidth = 0, bool $drag = true): self
    {
        $code = self::ffi()->window_set_titlebar_geometry(
            self::ffi()->webview_get_window($this->pv),
            $height ?? 0,
            $controlsWidth,
            $drag ? 1 : 0
        );
        if ($code !== 0) {
            throw new \RuntimeException(match ($code) {
                1 => '窗口不存在，无法设置自定义标题栏',
                3 => '当前平台不支持自定义标题栏',
                default => "设置自定义标题栏失败（错误码 {$code}）",
            });
        }
        return $this;
    }

    /**
     * 最小化窗口
     *
     * @return self
     * @throws \RuntimeException 窗口不存在时抛出
     */
    public function minimize(): self
    {
        $code = self::ffi()->window_minimize(self::ffi()->webview_get_window($this->pv));
        if ($code !== 0) {
            throw new \RuntimeException('窗口不存在，无法最小化');
        }
        return $this;
    }

    /**
     * 最大化 / 还原（切换）
     *
     * @return bool 切换之后是否处于最大化
     * @throws \RuntimeException 窗口不存在时抛出
     */
    public function toggleMaximize(): bool
    {
        $ptr = self::ffi()->webview_get_window($this->pv);
        if ($ptr === null) {
            throw new \RuntimeException('窗口不存在，无法切换最大化');
        }
        return self::ffi()->window_toggle_maximize($ptr) === 1;
    }

    /**
     * 当前是否处于最大化
     *
     * @return bool
     */
    public function isMaximized(): bool
    {
        return self::ffi()->window_is_maximized(self::ffi()->webview_get_window($this->pv)) === 1;
    }

    /**
     * 监听窗口状态变化（最大化 / 还原）
     *
     * 自绘标题栏必须知道这个：最大化时窗口会有圆角与内缩，标题栏要跟着调整，
     * 不接状态事件是做不对的。
     *
     * 注意只在状态**发生变化**时回调一次（内部会比对上一步的值），
     * 不是每次尺寸变化都触发。
     *
     * @param callable $callable 形如 function (Window $win, bool $maximized): void
     * @return self
     * @throws \RuntimeException 窗口不存在或当前平台不支持时抛出
     * @example $win->onStateChange(function ($win, $maximized) {
     *     echo $maximized ? '已最大化' : '已还原';
     * });
     */
    public function onStateChange(callable $callable): self
    {
        $win = $this;
        $c_callable = function ($ptr, int $state) use ($callable, $win): void {
            $callable($win, $state === 1);
        };

        $code = self::ffi()->window_set_state_callback(
            self::ffi()->webview_get_window($this->pv),
            $c_callable
        );
        if ($code !== 0) {
            throw new \RuntimeException(match ($code) {
                1 => '窗口不存在，无法注册状态回调',
                3 => '当前平台不支持窗口状态回调',
                default => "注册窗口状态回调失败（错误码 {$code}）",
            });
        }
        return $this;
    }

    /**
     * 发起窗口拖动（屏幕坐标）
     *
     * **三个平台写法一致**，页面里不需要按平台分支：在标题栏的 `mousedown` 里把
     * `event.screenX / screenY` 传进来即可。各平台内部各自用最合适的方式发起拖动：
     *
     * - Windows  合成 `WM_NCLBUTTONDOWN(HTCAPTION)`，进入系统的模态移动循环
     *            （贴边、双击最大化等原生行为都在）
     * - Linux    `gdk_window_begin_move_drag`
     * - macOS    按光标位移搬窗口（自绘标题栏比系统标题栏高时，高出来的那截也能拖）
     *
     * 只会同步调用一次，不会阻塞到拖动结束。
     * 若触发时左键已经松开（JS 与原生之间的正常竞态），本方法直接返回、不做任何事——
     * 那不是错误，也不该抛异常。
     *
     * @param int $x 屏幕坐标 X（JS 里通常是 event.screenX）
     * @param int $y 屏幕坐标 Y
     * @return self
     * @throws \RuntimeException 窗口不存在时抛出
     * @example // JS 侧（三平台同一句）：
     *          // bar.addEventListener('mousedown', e => phpBeginDrag(e.screenX, e.screenY));
     */
    public function beginDrag(int $x, int $y): self
    {
        $code = self::ffi()->window_begin_move_drag(
            self::ffi()->webview_get_window($this->pv),
            $x,
            $y
        );

        // 4 = 调用时左键已松开。什么都不做才是正确行为，不是"静默无效"。
        if ($code === 4) {
            return $this;
        }

        if ($code !== 0) {
            throw new \RuntimeException(match ($code) {
                1 => '窗口不存在，无法发起拖动',
                3 => '当前平台不支持由外部发起窗口拖动',
                default => "发起窗口拖动失败（错误码 {$code}）",
            });
        }
        return $this;
    }

    /**
     * 从外部发起窗口缩放
     *
     * 与 beginDrag 同一思路：这扇窗是 WebView2 宿主，子窗口铺满客户区、吃掉了鼠标，
     * 顶层窗口在边缘收不到 WM_NCHITTEST，留系统边框也抓不到边缘。所以缩放由 JS 在
     * mousedown 里判定边缘、把 $edge 传进来，三平台同一句 JS。
     *
     * @param int $edge 边/角编码（WMSZ_*）：1=左 2=右 3=上 4=左上 5=右上 6=下 7=左下 8=右下
     * @return self
     * @throws \RuntimeException 窗口不存在或边/角编码非法时抛出
     * @example // JS 侧（三平台同一句）：
     *          // bar.addEventListener('mousedown', e => {
     *          //   if (e.button !== 0) return;
     *          //   if (nearEdge(e)) tbBeginResize(edgeOf(e));
     *          // });
     */
    public function beginResize(int $edge): self
    {
        $code = self::ffi()->window_begin_resize_drag(
            self::ffi()->webview_get_window($this->pv),
            $edge
        );

        // 4 = 调用时左键已松开。什么都不做才是正确行为，不是"静默无效"。
        if ($code === 4) {
            return $this;
        }

        if ($code !== 0) {
            throw new \RuntimeException(match ($code) {
                1 => '窗口不存在或边/角编码非法，无法发起缩放',
                3 => '当前平台不支持由外部发起窗口缩放',
                default => "发起窗口缩放失败（错误码 {$code}）",
            });
        }
        return $this;
    }

    /**
     * 把 '#RRGGBB' / 'RRGGBB' 解析成 0xRRGGBB
     *
     * @param string|null $color
     * @return int null / 空串返回 -1，即"C 侧不覆盖该颜色"
     * @throws \InvalidArgumentException
     */
    private static function parseRgb(?string $color): int
    {
        if ($color === null || $color === '') {
            return -1;
        }
        $hex = ltrim($color, '#');
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            throw new \InvalidArgumentException("颜色格式无效: {$color}，应为 #RRGGBB");
        }
        return (int) hexdec($hex);
    }

    /**
     * 设置窗口标题
     *
     * @param string $title
     * @return self
     * @example $win->setTitle(窗口标题名称);
     */
    public function setTitle(string $title): self
    {
        self::ffi()->webview_set_title($this->pv, $title);
        return $this;
    }

    /**
     * 设置窗口大小
     *
     * @param int $width 窗口宽度
     * @param int $height 窗口高度
     * @param WindowHint $hint 窗口提示
     * @return self
     * @example $win->setSize(窗口宽度, 窗口高度, 窗口提示);
     */
    public function setSize(int $width, int $height, WindowHint $hint = WindowHint::None): self
    {
        self::ffi()->webview_set_size($this->pv, $width, $height, $hint->value);
        return $this;
    }

    /**
     * 初始化窗口
     * 会在window.onload之前加载js代码
     *
     * @param string $js
     * @return self
     * @example $win->init(js代码);
     */
    public function init(string $js): self
    {
        self::ffi()->webview_init($this->pv, $js);
        return $this;
    }

    /**
     * 执行js代码
     *
     * @param string $js
     * @return self
     * @example $win->eval(js代码);
     */
    public function eval(string $js): self
    {
        self::ffi()->webview_eval($this->pv, $js);
        return $this;
    }

    /**
     * 设置窗口html内容
     *
     * @param string $html html内容
     * @return self
     * @example $win->setHtml(html内容);
     */
    public function setHtml(string $html): self
    {
        self::ffi()->webview_set_html($this->pv, $html);
        return $this;
    }

    /**
     * 导航窗口到指定url
     *
     * @param string $url url地址
     * @return self
     * @example $win->navigate(url地址);
     */
    public function navigate(string $url): self
    {
        self::ffi()->webview_navigate($this->pv, $url);
        return $this;
    }

    /**
     * 绑定js函数到窗口
     *
     * @param string $name js函数名称
     * @param callable $callable 函数回调
     * @return self
     * @example $win->bind(js函数名称, function (...$params) {
     *     // 这里可以写你要执行的代码
     *     return "hello"; // 如需要返回数据给js则可用 return
     * });
     */
    public function bind(string $name, callable $callable): self
    {
        $pv = $this->pv;
        $c_callable = function (string $id, string $req, mixed $arg) use ($callable, $pv): void {
            // 桥函数传的是 JSON.stringify(Array.prototype.slice.call(arguments))，
            // 正常情况恒为 JSON 数组；兜一层避免畸形输入直接抛 TypeError
            $params = json_decode($req, true) ?? [];
            [$status, $result] = self::encodeResult($callable(...$params));
            self::ffi()->webview_return($pv, $id, $status, $result);
        };
        self::ffi()->webview_bind($this->pv, $name, $c_callable, null);
        return $this;
    }

    /**
     * 把 PHP 回调的返回值编码成 webview 需要的 (status, result)
     *
     * webview 的 JS 桥对任何非 undefined 的 result 都会做 JSON.parse（见 webview.h 的
     * onReply）：status=0 时用它 resolve，status!=0 时同样解析后用于 reject。
     * 所以 result 必须是合法 JSON —— 手工拼引号会让含引号 / 反斜杠 / 换行的字符串
     * 在 JS 侧变成 "Failed to parse binding result as JSON" 的 reject。
     *
     * 另外我原来的写法用 if ($value) 做守卫，return null / false / 0 / '' / []
     * 时根本不会调用 webview_return，JS 侧的 promise 会永久挂起。这里改成总是回传。
     *
     * 注意 json_encode() 对 INF / NAN / 非法 UTF-8 返回 false，而 PHP 会把 false 转成
     * 空字符串；空串在 webview 里表示 undefined，会变成静默的 resolve(undefined)。
     * 所以这里显式转成 status=1，让 JS 侧走 reject 而不是拿到一个安静的错误值。
     *
     * @param mixed $value 回调返回值
     * @return array{0: int, 1: string} [status, JSON 字符串]
     */
    private static function encodeResult(mixed $value): array
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return [1, json_encode([
                'error' => 'PHP callback result is not JSON encodable',
                'type' => get_debug_type($value),
            ])];
        }
        return [0, $json];
    }

    /**
     * 解绑js函数
     *
     * @param string $name js函数名称
     * @return self
     * @example $win->unBind(js函数名称);
     */
    public function unBind(string $name): self
    {
        self::ffi()->webview_unbind($this->pv, $name);
        return $this;
    }

    /**
     * 设置窗口关闭事件
     *
     * @param callable $callable<CData: bool> 关闭事件回调 返回 true 关闭，false 不关闭
     * @return self
     * @example $win->setCloseCallback(function ($win) {
     *     // 这里可以写你要执行的代码
     *     return false; // 返回 true 关闭窗口，false 不关闭
     * });
     */
    public function setCloseCallback(callable $callable): self
    {
        $win = $this;
        $c_callable = function () use ($callable, $win) {
            $cb =  $callable($win);
            return $cb ? 1 : 0;
        };
        self::ffi()->webview_set_close_callback($this->pv, $c_callable);
        return $this;
    }

    /**
     * 窗口显示
     *
     * @return self
     * @example $win->show();
     */
    public function show(): self
    {
        self::ffi()->window_show(self::ffi()->webview_get_window($this->pv));
        return $this;
    }

    /**
     * 窗口隐藏
     *
     * @return self
     * @example $win->hide();
     */
    public function hide(): self
    {
        self::ffi()->window_hide(self::ffi()->webview_get_window($this->pv));
        return $this;
    }

    /**
     * 创建托盘
     *
     * Linux 注意：GtkStatusIcon 走 legacy XEmbed 协议（X11-only）。Wayland 会话
     * 或没有托盘管理器的桌面（如 GNOME 3.26+ 默认无）上托盘无法嵌入——此时本方法
     * 优雅降级返回 self（$this->tray 为 null，托盘菜单不会注册），不会像直接创建
     * 那样在 ~10 秒后爆 GTK 断言。KDE/MATE/XFCE 等带托盘管理器的环境正常显示。
     *
     * @param string $icon 托盘图标
     * @return self
     * @example $win->tray(托盘图标); - windows 要求ico格式 - Linux 要求png格式 - MacOs ico
     */
    public function tray(string $icon): self
    {
        $this->tray = self::ffi()->window_tray(self::ffi()->webview_get_window($this->pv), $icon);
        return $this;
    }

    /**
     * 托盘菜单
     *
     * @param array<array{text: string, disabled: int, cb: callable}> $menu 菜单数组
     * @return self
     * @example $win->trayMenu([
     *     [
     *         "text" => "打开窗口",
     *         "cb" => function ($win){
     *             $win->show();
     *         }
     *     ],
     *     [
     *         "text" => "关闭窗口",
     *         "cb" => function ($win){
     *             $win->terminate();
     *         }
     *     ]
     * ]); // disabled 为1表示禁用0表示不禁用,默认0
     */
    public function trayMenu(array $menu): self
    {
        trayMenuList(self::ffi(), $this, $menu);
        return $this;
    }

    /**
     * 移除托盘菜单
     *
     * @return void
     */
    private function trayRemove(): void
    {
        self::ffi()->window_tray_remove($this->tray);
    }
}
