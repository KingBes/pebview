<?php

// 根据你的实际情况，修改下面的路径
require dirname(__DIR__) . "/vendor/autoload.php";

/**
 * 交互组 —— 系统对话框（Dialog）
 *
 * 跑法：
 *     php -d extension=ffi -d ffi.enable=1 test/demo-dialog.php
 *
 * 这一档覆盖 Dialog 的 3 个方法 × 全部枚举取值：
 *   - msg()    3 个 level × 3 组按钮，看按钮组合与返回值
 *   - prompt() 看输入框能否回读用户输入
 *   - file()   3 个 FileAction（打开文件 / 选目录 / 保存），并带上 filters
 *
 * 观察点：
 *   1. 每个对话框的图标/语气是否随 DialogLevel 变化（Info / Warning / Error）
 *   2. 点不同按钮时打印的返回值是否正确（确认→true，取消→false）
 *   3. prompt 里输入的文本是否原样回读
 *   4. file 返回的路径是否正确；filters 是否真的过滤了扩展名
 *
 * 退出方式：没有事件循环，每个对话框点掉就往下走，跑完自动结束。
 *          如果想中途退出，直接 Ctrl+C —— 不会留下窗口。
 *
 * 本机已知：这台机器的 WebView2 环境不渲染页面（与对话框无关），
 *          但 osdialog 走的是系统原生对话框，不受影响。
 */

use Kingbes\PebView\Dialog;
use Kingbes\PebView\DialogBtn;
use Kingbes\PebView\DialogLevel;
use Kingbes\PebView\FileAction;

function step(string $msg): void
{
    echo "\n[执行] {$msg}\n";
}

function result(string $label, mixed $value): void
{
    echo "       -> {$label} = " . var_export($value, true) . "\n";
}

echo "=== PebView 交互演示：系统对话框 ===\n";

// ---------------------------------------------------------------- msg：3 个 level

step('Dialog::msg —— Info + Ok（最简单的消息框）');
$r = Dialog::msg('这是一条 Info 级别的消息。', DialogLevel::Info, DialogBtn::Ok);
result('返回值（点了确定就是 true）', $r);

step('Dialog::msg —— Warning + OkCancel');
$r = Dialog::msg('这是一条 Warning 级别的消息，有两个按钮。', DialogLevel::Warning, DialogBtn::OkCancel);
result('返回值（确定 true / 取消 false）', $r);

step('Dialog::msg —— Error + YesNo');
$r = Dialog::msg('这是一条 Error 级别的消息，问你是或否。', DialogLevel::Error, DialogBtn::YesNo);
result('返回值（是 true / 否 false）', $r);

// ---------------------------------------------------------------- prompt

step('Dialog::prompt —— 带默认文本的输入框');
$text = Dialog::prompt('请输入点什么（下面有默认值，可以改）', DialogLevel::Info, '默认文本');
result('用户输入（点取消通常是空串）', $text);

step('Dialog::prompt —— 不带默认文本');
$text2 = Dialog::prompt('再输一次，这次没有默认值');
result('用户输入', $text2);

// ---------------------------------------------------------------- file：3 个 action

step('Dialog::file —— 打开文件（带 filters：图片 / 文本）');
$picked = Dialog::file('', '', FileAction::Open, 'Images:png,jpg,jpeg,gif,ico;Text:txt,md,php');
result('选中的文件路径（取消是空串）', $picked);

step('Dialog::file —— 选择目录');
$dir = Dialog::file('', '', FileAction::OpenDir);
result('选中的目录（取消是空串）', $dir);

step('Dialog::file —— 保存文件（预填文件名）');
$save = Dialog::file('', 'pebview-demo.txt', FileAction::Save, 'Text:txt,md');
result('保存目标路径（取消是空串）', $save);

echo "\n=== 演示结束 ===\n";
echo "对照 doc/Chinese/Dialog.md 看返回值是否符合预期。\n";
