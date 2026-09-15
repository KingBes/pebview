<?php

namespace Kingbes\PebView;

/**
 * 构造并添加托盘菜单项
 *
 * @param \FFI $ffi PebView 动态库的 FFI 实例
 * @param Window $win 窗口对象
 * @param array $menu 菜单项数组，每项支持 text / disabled / checked / cb
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
