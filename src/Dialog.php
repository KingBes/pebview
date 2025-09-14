<?php

namespace Kingbes\PebView;

/**
 * 对话框类 Dialog
 */
class Dialog extends Base
{
    public static function msg(string $message, int $level, int $buttons): int
    {
        return self::ffi()->osdialog_message($level, $buttons, $message);
    }
}
