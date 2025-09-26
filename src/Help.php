<?php

namespace Kingbes\PebView;

use \FFI\CData;

function trayMenuList(\FFI $ffi, CData $tray, array $menu): void
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
        $menu->callback = function ($ptr) use ($item) {
            $item["cb"]($ptr);
        };
        $ffi->window_tray_add_menu($tray, $ffi::addr($menu));
    }
}
