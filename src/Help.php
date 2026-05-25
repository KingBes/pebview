<?php

namespace Kingbes\PebView;

// pebview 扩展已加载时，托盘菜单由 C 扩展原生处理，跳过 FFI 实现
if (extension_loaded('pebview')) {
    return;
}

/**
 * @param \FFI $ffi
 */
function trayMenuList($ffi, Window $win, array $menu): void
{
    $i = 1000;
    foreach ($menu as $key => $item) {
        $menu = $ffi->new("struct tray_menu");
        $menu->id = $key + $i;
        $text = $ffi->new("char[" . strlen($item["text"]) + 1 . "]");
        $ffi::memcpy($text, $item["text"], strlen($item["text"]));
        $menu->text = $ffi->cast("char *", $text);
        if (isset($item["disabled"])) {
            $menu->disabled = $item["disabled"];
        }
        if (isset($item["checked"])) {
            $menu->checked = $item["checked"];
        }
        $menu->callback = function ($ptr) use ($item, $win) {
            $item["cb"]($win);
        };
        $ffi->window_tray_add_menu($win->tray, $ffi::addr($menu));
    }
}
