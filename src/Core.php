<?php

namespace Kingbes\PebView;

use \FFI\CData;

/**
 * 核心类 Core
 */
class Core extends Base
{
    private CData $pv;

    public function __construct(bool $debug)
    {
        $this->pv = self::ffi()->pv_create($debug);
    }

    public function destroy(): self
    {
        self::ffi()->pv_destroy($this->pv);
        return $this;
    }

    public function run(): self
    {
        self::ffi()->pv_run($this->pv);
        return $this;
    }

    public function terminate(): self
    {
        self::ffi()->pv_terminate($this->pv);
        return $this;
    }

    public function dispatch(callable $func): self
    {
        $c_fn = function () use ($func) {
            $func();
        };
        self::ffi()->pv_dispatch($this->pv, $c_fn);
        return $this;
    }

    public function dispatchCtx(callable $func): self
    {
        $c_fn = function () use ($func) {
            $func();
        };
        self::ffi()->pv_dispatch_ctx($this->pv, $c_fn, null);
        return $this;
    }

    public function getWindow(): CData
    {
        return self::ffi()->pv_get_window($this->pv);
    }

    public function setIcon(string $iconFilePath): self
    {
        self::ffi()->pv_set_icon($this->pv, $iconFilePath);
        return $this;
    }

    public function setTitle(string $title): self
    {
        self::ffi()->pv_set_title($this->pv, $title);
        return $this;
    }

    public function setSize(int $width, int $height, int $hint): self
    {
        self::ffi()->pv_set_size($this->pv, $width, $height, $hint);
        return $this;
    }

    public function navigate(string $url): self
    {
        self::ffi()->pv_navigate($this->pv, $url);
        return $this;
    }

    public function setHtml(string $html): self
    {
        self::ffi()->pv_set_html($this->pv, $html);
        return $this;
    }

    public function __destruct()
    {
        self::ffi()->pv_terminate($this->pv);
    }
}
