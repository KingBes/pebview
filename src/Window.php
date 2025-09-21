<?php

namespace Kingbes\PebView;

use \FFI\CData;

/**
 * Window 窗口类
 */
class Window extends Base
{
    /**
     * 创建窗口
     *
     * @param boolean $debug 是否开启调试模式
     * @param CData|null $window 窗口指针
     * 
     * @return CData 窗口指针
     */
    public static function create(bool $debug, CData|null $window = null): CData
    {
        $win = self::ffi()->webview_create($debug, $window);
        return $win;
    }

    /**
     * 销毁窗口
     *
     * @param CData $pv 窗口指针
     * @return void
     */
    public static function destroy(CData $pv): void
    {
        self::ffi()->webview_destroy($pv);
    }

    /**
     * 运行窗口
     *
     * @param CData $pv 窗口指针
     * @return void
     */
    public static function run(CData $pv): void
    {
        self::ffi()->webview_run($pv);
    }

    /**
     * 终止窗口
     *
     * @param CData $pv 窗口指针
     * @return void
     */
    public static function terminate(CData $pv): void
    {
        self::ffi()->webview_terminate($pv);
    }

    /**
     * 分发回调函数
     *
     * @param CData $pv 窗口指针
     * @param callable $callable 回调函数
     * @return void
     */
    public static function dispatch(CData $pv, callable $callable): void
    {
        $c_callable = function (CData $pv, CData $arg) use ($callable) {
            $callable($pv, $arg);
        };
        self::ffi()->webview_dispatch($pv, $c_callable, null);
    }

    /**
     * 设置窗口图标
     *
     * @param CData $pv 窗口指针
     * @param string $icon 图标路径
     * @return void
     */
    public static function setIcon(CData $pv, string $icon): void
    {
        $ptr = self::ffi()->webview_get_window($pv);
        self::ffi()->set_icon($ptr, $icon);
    }

    /**
     * 设置窗口标题
     *
     * @param CData $pv
     * @param string $title
     * @return void
     */
    public static function setTitle(CData $pv, string $title): void
    {
        self::ffi()->webview_set_title($pv, $title);
    }

    /**
     * 设置窗口大小
     *
     * @param CData $pv 窗口指针
     * @param int $width 窗口宽度
     * @param int $height 窗口高度
     * @param WindowHint $hint 窗口提示
     * @return void
     */
    public static function setSize(CData $pv, int $width, int $height, WindowHint $hint = WindowHint::None): void
    {
        self::ffi()->webview_set_size($pv, $width, $height, $hint->value);
    }

    /**
     * 初始化窗口
     * 会在window.onload之前加载js代码
     *
     * @param CData $pv
     * @param string $js
     * @return void
     */
    public static function init(CData $pv, string $js): void
    {
        self::ffi()->webview_init($pv, $js);
    }

    /**
     * 执行js代码
     *
     * @param CData $pv
     * @param string $js
     * @return void
     */
    public static function eval(CData $pv, string $js): void
    {
        self::ffi()->webview_eval($pv, $js);
    }

    /**
     * 设置窗口html内容
     *
     * @param CData $pv 窗口指针
     * @param string $html html内容
     * @return void
     */
    public static function setHtml(CData $pv, string $html): void
    {
        self::ffi()->webview_set_html($pv, $html);
    }

    /**
     * 导航窗口到指定url
     *
     * @param CData $pv 窗口指针
     * @param string $url url地址
     * @return void
     */
    public static function navigate(CData $pv, string $url): void
    {
        self::ffi()->webview_navigate($pv, $url);
    }

    /**
     * 绑定js函数到窗口
     *
     * @param CData $pv 窗口指针
     * @param string $name js函数名称
     * @param callable $callable 函数回调
     * @return void
     */
    public static function bind(CData $pv, string $name, callable $callable): void
    {
        $c_callable = function (string $id, string $req, mixed $arg) use ($callable, $pv) {
            $params = json_decode($req, true);
            $value = $callable($pv, ...$params);
            if ($value) {
                if ((is_object($value) || is_array($value))) {
                    self::ffi()->webview_return($pv, $id, 0, json_encode($value, 320));
                } elseif (is_string($value)) {
                    self::ffi()->webview_return($pv, $id, 0, '"' . $value . '"');
                } else if (is_bool($value)) {
                    self::ffi()->webview_return($pv, $id, 0, $value ? 'true' : 'false');
                } else {
                    self::ffi()->webview_return($pv, $id, 0, "{$value}");
                }
            }
        };
        self::ffi()->webview_bind($pv, $name, $c_callable, null);
    }

    /**
     * 解绑js函数
     *
     * @param CData $pv 窗口指针
     * @param string $name js函数名称
     * @return void
     */
    public static function unBind(CData $pv, string $name): void
    {
        self::ffi()->webview_unbind($pv, $name);
    }
}
