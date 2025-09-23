<?php

namespace Kingbes\PebView;

use \FFI\CData;

class Tray extends Base
{
    public static function create(array $data): bool
    {
        $tray = self::ffi()->new('struct tray');
        $c_icon = self::ffi()->new("char[" . strlen($data['icon']) . "]");
        self::ffi()::memcpy($c_icon, $data['icon'], strlen($data['icon']));
        $tray->icon = self::ffi()->cast('char *', $c_icon);

        if (isset($data['menu'])) {
            $menu_list = self::ffi()->new('struct tray_menu[' . count($data['menu']) . ']');
            foreach ($data['menu'] as $k => $v) {
                $ctext = self::ffi()->new("char[" . strlen($v['text']) . "]");
                self::ffi()::memcpy($ctext, $v['text'], strlen($v['text']));
                $menu_list[$k]->text = self::ffi()->cast('char *', $ctext);
                if (isset($v['cb'])) {
                    $menu_list[$k]->cb = function ($menu) use ($v) {
                        $v['cb']($menu);
                    };
                }
            }
            $tray->menu = self::ffi()->cast('struct tray_menu *', $menu_list);
        }
        var_dump($tray);
        $c_tray = self::ffi()::addr($tray);
        return self::ffi()->tray_create($c_tray);
    }

    public static function loop(int $block): bool
    {
        return self::ffi()->tray_loops($block);
    }

    public static function close(): void
    {
        self::ffi()->tray_close();
    }
}
