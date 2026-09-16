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
