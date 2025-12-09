<?php

namespace Kingbes\PebView;

function trayMenuList(\FFI $ffi, Window $win, array $menu): void
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
        $ffi["PebView"]->window_tray_add_menu($win->tray, $ffi::addr($menu));
    }
}
