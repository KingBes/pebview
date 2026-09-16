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
