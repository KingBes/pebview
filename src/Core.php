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
        $this->pv = self::ffi()->webview_create($debug, null);
    }

    public function destroy(): self
    {
        self::ffi()->webview_destroy($this->pv);
        return $this;
    }

    public function setIcon(string $iconFilePath): self
    {
        $ptr = self::ffi()->webview_get_window($this->pv);
        self::ffi()->set_icon($ptr, $iconFilePath);
        return $this;
    }

    public function run(): self
    {
        self::ffi()->webview_run($this->pv);
        return $this;
    }

    public function setHtml(string $html): self
    {
        self::ffi()->webview_set_html($this->pv, $html);
        return $this;
    }
}
