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
        return self::ffi()->osdialog_message($level->value, $buttons->value, $message) === 1;
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
        return self::ffi()->osdialog_prompt($level->value, $message, $text) ?? '';
    }

    /**
     * 文件选择对话框
     *
     * @param string $dir 对话框默认目录
     * @param string $filename 对话框默认文件名
     * @param FileAction $action 文件操作类型
     * @param string $filters 文件过滤器 默认为空 格式: "显示名:扩展名,扩展名;显示名:扩展名" Images:png,jpg,gif;Text:txt,md
     * @return string 用户选择的文件路径
     */
    public static function file(string $dir, string $filename, FileAction $action, string $filters = ''): string
    {
        $ffi = self::ffi();
        $c_filters = $ffi->osdialog_filters_parse($filters);
        $path = $ffi->osdialog_file($action->value, $dir, $filename, $c_filters);
        // filters 由 osdialog_filters_parse 分配，必须在用完后归还（该函数判空，传 null 安全）
        $ffi->osdialog_filters_free($c_filters);
        return $path ?? '';
    }
}
