<?php

namespace Kingbes\PebView;

// pebview 扩展已加载时，类已在 C 层注册，跳过 PHP FFI 定义
if (extension_loaded('pebview')) {
    return;
}

/**
 *  toast 提示类 Toast
 */
class Toast extends Base
{
    /**
     * 显示 toast 提示
     *
     * @param string $app 应用名称
     * @param string $title 标题
     * @param string $msg 消息内容
     * @param string $icon 图标路径（可选）
     * @return bool 是否成功显示 toast 提示
     */
    public static function show(
        string $app,
        string $title,
        string $msg,
        string $icon = ""
    ): bool {
        return self::ffi()["Toast"]->toastShow(
            $app,
            $title,
            $msg,
            $icon
        );
    }
}
