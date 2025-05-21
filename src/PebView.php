<?php

// 严格模式
declare(strict_types=1);

use \FFI\CData;

/**
 * PebView类
 */
class PebView
{
    private CData $pv;
    private \FFI $ffi;

    /**
     * 构造函数
     *
     * @param boolean $debug 是否开启调试模式，默认false
     * @param CData|null $window 窗口句柄，默认null
     * @throws \Exception 加载库失败时抛出异常
     */
    public function __construct(bool $debug = false, CData|null $window = null)
    {
        $header = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . "PebView.h");
        try {
            $this->ffi = \FFI::cdef($header, $this->getLibFile());
        } catch (\Exception $e) {
            throw new \Exception("Failed to load PebView library: {$e->getMessage()}");
        }
        $this->pv = $this->ffi->webview_create($debug ? 1 : 0, $window);
    }

    /**
     * 销毁PebView实例
     *
     * @return self
     */
    public function destroy(): self
    {
        $this->getError($this->ffi->webview_destroy($this->pv));
        return $this;
    }

    /**
     * 运行PebView实例
     *
     * @return self
     */
    public function run(): self
    {
        $this->getError($this->ffi->webview_run($this->pv));
        return $this;
    }

    /**
     * 终止PebView实例
     *
     * @return self
     */
    public function terminate(): self
    {
        $this->getError($this->ffi->webview_terminate($this->pv));
        return $this;
    }

    /**
     * 调度任务
     * 一般用于在其他线程中调用PebView实例的方法
     *
     * @return self
     */
    public function dispatch(callable $callable): self
    {
        $c_fn = function ($v, $d) use ($callable) {
            $callable($this);
        };
        $this->getError($this->ffi->webview_dispatch($this->pv, $c_fn, null));
        return $this;
    }

    /**
     * 获取窗口句柄
     *
     * @return CData 窗口句柄
     */
    public function getWindow(): CData
    {
        return $this->ffi->webview_get_window($this->pv);
    }

    /**
     * 获取原生句柄
     *
     * @param integer $kind 原生句柄类型，0表示窗口句柄，1表示小部件，2表示浏览器
     * @return CData 原生句柄
     */
    public function getNativeHandle(int $kind): CData
    {
        return $this->ffi->webview_get_native_handle($this->pv, $kind);
    }

    /**
     * 设置标题
     *
     * @param string $title 标题
     * @return self
     */
    public function setTitle(string $title): self
    {
        $this->getError($this->ffi->webview_set_title($this->pv, $title));
        return $this;
    }

    /**
     * 设置大小
     *
     * @param integer $width 宽度
     * @param integer $height 高度
     * @param integer $hints 0表示不指定，1表示最小化，2表示最大化，3表示固定大小
     * @return self
     */
    public function setSize(int $width, int $height, int $hints = 0): self
    {
        $this->getError($this->ffi->webview_set_size($this->pv, $width, $height, $hints));
        return $this;
    }

    /**
     * 导航到指定URL
     *
     * @param string $url URL
     * @return self
     */
    public function navigate(string $url): self
    {
        $this->getError($this->ffi->webview_navigate($this->pv, $url));
        return $this;
    }

    /**
     * 加载html到内容
     *
     * @param string $html html字符串
     * @return self
     */
    public function setHtml(string $html): self
    {
        $this->getError($this->ffi->webview_set_html($this->pv, $html));
        return $this;
    }

    /**
     * 注入JavaScript代码在加载页面时立即执行
     * 代码将在 window.onload之前执行
     *
     * @param string $js
     * @return self
     */
    public function init(string $js): self
    {
        $this->getError($this->ffi->webview_init($this->pv, $js));
        return $this;
    }

    /**
     * 立即执行JavaScript代码
     *
     * @param string $js JavaScript代码
     * @return self
     */
    public function eval(string $js): self
    {
        $this->getError($this->ffi->webview_eval($this->pv, $js));
        return $this;
    }

    /**
     * 绑定JavaScript函数
     *
     * @param string $name 函数名
     * @param Closure $function 回调函数
     * @return self
     */
    public function bind(string $name, Closure $function): self
    {
        $c_fn = function ($id, $req, $arg) use ($function) {
            $res = $function($this, $id, json_decode($req, true), $arg);
            if ($res && (is_object($res) || is_array($res))) {
                $this->getError($this->ffi->webview_return($this->pv, $id, 0, json_encode($res, 320)));
            }
        };
        $this->getError($this->ffi->webview_bind($this->pv, $name, $c_fn, null));
        return $this;
    }

    /**
     * 卸载JavaScript函数
     *
     * @param string $name 函数名
     * @return self
     */
    public function unbind(string $name): self
    {
        $this->getError($this->ffi->webview_unbind($this->pv, $name));
        return $this;
    }

    /**
     * 响应JavaScript函数调用
     *
     * @param CData $id 函数调用ID
     * @param integer $status 0表示成功，其他值表示失败
     * @param array|object $result 响应结果，必须是数组或对象
     * @return self
     */
    public function value(CData $id, int $status, array|object $result): self
    {
        $this->getError($this->ffi->webview_return($this->pv, $id, $status, json_encode($result, 320)));
        return $this;
    }

    /**
     * 获取版本信息
     *
     * @return CData
     */
    public function version(): CData
    {
        return $this->ffi->webview_version();
    }

    public function alert(string $message): self
    {
        $this->ffi->osdialog_message(0, 0, $message);
        return $this;
    }

    /**
     * 获取错误信息
     *
     * @param integer $code 错误码
     */
    private function getError(int $code): void
    {
        switch ($code) {
            case -5:
                throw new \Exception("窗口未找到");
            case -4:
                throw new \Exception("操作已被用户取消");
            case -3:
                throw new \Exception("无效状态检测到");
            case -2:
                throw new \Exception("一个或多个无效参数已指定，例如在函数调用中。");
            case -1:
                throw new \Exception("发生了未指定的错误。可能需要更多特定的错误代码");
            case 1:
                throw new \Exception("内存分配失败");
            case 2:
                throw new \Exception("操作失败");
            default:
        }
    }

    /**
     * 获取库文件路径
     *
     * @return string
     */
    private function getLibFile(): string
    {
        $os = PHP_OS_FAMILY;
        switch ($os) {
            case "Windows":
                $suffix = ".dll";
                break;
            case "Darwin":
                $suffix = ".dylib";
                break;
            case "Linux":
                $suffix = ".so";
                break;
            default:
                throw new \Exception("Unsupported OS: {$os}");
        }
        $libFile = dirname(__DIR__)  . DIRECTORY_SEPARATOR
            . "lib" . DIRECTORY_SEPARATOR
            . $os . DIRECTORY_SEPARATOR
            . "PebView{$suffix}";
        return $libFile;
    }
}
