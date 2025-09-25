<?php

namespace Kingbes\PebView;

use \FFI\CData;

function trayMenuList(\FFI $ffi, array $menu): CData
{
    $c_menu_list = $ffi->new("struct tray_menu[" . count($menu) . "]");
    foreach ($menu as $key => $item) {
        $c_menu = $ffi->new("struct tray_menu");
        $char = $ffi->new("char[" . strlen($item["text"]) + 1 . "]");
        $ffi::memcpy($char, $item["text"], strlen($item["text"]));
        var_dump($char);
        $c_menu->text = $ffi->cast("char *", $char);
        $c_menu->callback = function () use ($item) {
            $item["cb"];
        };
        $c_menu->disabled = $item["disabled"] ?? 0;
        $c_menu->checked = $item["checked"] ?? 0;
        $c_menu_list[$key] = $c_menu;
    }
    return $c_menu_list;
}
