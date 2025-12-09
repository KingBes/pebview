<?php

namespace Kingbes\PebView;

/**
 * 对话框类 Dialog
 */
class Dialog extends Base
{
    /**
     * 消息对话框
     *
     * @param string $message 对话框消息
     * @param DialogLevel $level 对话框级别 默认为 DialogLevel::Info
     * @param DialogBtn $buttons 对话框按钮 默认为 DialogBtn::Ok
     * @return bool 是否点击了确定按钮
     */
    public static function msg(string $message, DialogLevel $level = DialogLevel::Info, DialogBtn $buttons = DialogBtn::Ok): bool
    {
        return self::ffi()["PebView"]->osdialog_message($level->value, $buttons->value, $message) === 1;
    }

    /**
     * 输入对话框
     *
     * @param string $message 对话框消息
     * @param string $text 对话框默认文本
     * @param DialogLevel $level 对话框级别 默认为 DialogLevel::Info
     * @return string 用户输入的文本
     */
    public static function prompt(string $message, DialogLevel $level = DialogLevel::Info, string $text = ''): string
    {
        return self::ffi()["PebView"]->osdialog_prompt($level->value, $message, $text) ?? '';
    }

    /**
     * 文件选择对话框
     *
     * @param string $dir 对话框默认目录
     * @param string $filename 对话框默认文件名
     * @param FileAction $action 文件操作类型
     * @return string 用户选择的文件路径
     */
    public static function file(string $dir, string $filename, FileAction $action): string
    {
        return self::ffi()["PebView"]->osdialog_file($action->value, $dir, $filename, null) ?? '';
    }
}
