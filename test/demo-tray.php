<?php

// 根据你的实际情况，修改下面的路径
require dirname(__DIR__) . "/vendor/autoload.php";

/**
 * 交互组 —— 托盘图标与托盘菜单
 *
 * 跑法：
 *     php -d extension=ffi -d ffi.enable=1 test/demo-tray.php
 *
 * 覆盖 Window 的 tray() / trayMenu() / show() / hide() / terminate()，
 * 以及 trayMenu 的 text / disabled / checked / cb 四个字段。
 *
 * 观察点：
 *   1. 系统托盘区是否出现图标（Windows 右下角，可能要展开隐藏图标）
 *   2. 右键（或左键）图标弹出的菜单项
 *   3. "已勾选项"前面是否有勾；"禁用项"是否是灰的、点不动
 *   4. 点「隐藏窗口」「显示窗口」后窗口是否真的消失 / 出现
 *   5. 点「退出」后进程是否正常结束（terminate 会把托盘一起清掉）
 *
 * 退出方式（三重保底，不会挂死）：
 *   1. 托盘菜单里的「退出」——最正规的退出方式
 *   2. 直接关窗口——本 demo 的 setCloseCallback 返回 true，关了就真关
 *   3. 20 秒后自动退出——万一前两条都没走，JS 定时器会兜底
 *      （注意：本机 WebView2 不渲染页面时这个定时器可能不触发，
 *        那就需要人工走第 1 或第 2 条）
 */

use Kingbes\PebView\Window;
use Kingbes\PebView\WindowHint;

$icon = __DIR__ . '/php.ico';

echo "=== PebView 交互演示：托盘 ===\n";
echo "托盘图标: {$icon} (" . (is_file($icon) ? '存在' : '缺失！') . ")\n";
echo "退出方式：托盘菜单「退出」，或直接关窗口。\n\n";

$win = new Window(false);

$win->setTitle('PebView 托盘演示')
    ->setSize(640, 420, WindowHint::None)
    ->setIcon($icon);

// 关窗就真关（本 demo 不拦截关闭，避免无人值守时挂死）
$win->setCloseCallback(function () {
    echo "[事件] 窗口关闭回调触发，允许关闭。\n";
    return true;
});

$win->tray($icon);

if ($win->tray === null) {
    echo "[警告] 本平台 window_tray 返回了空 —— 当前桌面环境不提供托盘，演示无法继续。\n";
    $win->destroy();
    exit(0);
}

$win->trayMenu([
    [
        'text' => '显示窗口',
        'cb' => function (Window $w) {
            echo "[菜单] 显示窗口\n";
            $w->show();
        },
    ],
    [
        'text' => '隐藏窗口',
        'cb' => function (Window $w) {
            echo "[菜单] 隐藏窗口（点托盘图标可以再显示）\n";
            $w->hide();
        },
    ],
    [
        'text' => '已勾选项（checked = 1）',
        'checked' => 1,
        'cb' => function () {
            echo "[菜单] 点了已勾选项\n";
        },
    ],
    [
        'text' => '禁用项（disabled = 1，应当点不动）',
        'disabled' => 1,
        'cb' => function () {
            echo "[菜单] 如果你看到这行，说明 disabled 没生效！\n";
        },
    ],
    [
        'text' => '通知一条',
        'cb' => function () {
            echo "[菜单] 弹一条通知\n";
            \Kingbes\PebView\Toast::show('PebView Demo', '来自托盘', '你点了托盘菜单里的「通知一条」。');
        },
    ],
    [
        'text' => '退出',
        'cb' => function (Window $w) {
            echo "[菜单] 退出 —— terminate() 会把托盘一起移除\n";
            $w->terminate();
        },
    ],
]);

// 兜底：20 秒后自动退出（正常环境下 JS 会触发；本机可能不触发，属已知限制）
$win->init('setTimeout(function () { if (typeof pebview_quit === "function") pebview_quit(); }, 20000);');
$win->bind('pebview_quit', function () use ($win) {
    echo "[定时器] 20 秒到，自动退出。\n";
    $win->terminate();
    return true;
});

$win->setHtml(<<<'HTML'
<body style="font-family: system-ui, sans-serif; background:#f7f7f8; margin:0; padding:32px">
<h1 style="font-size:20px">托盘演示</h1>
<p>窗口里的内容只是陪衬，重点在系统托盘。</p>
<ol style="line-height:1.8">
  <li>找到托盘图标，右键（或左键）弹出菜单</li>
  <li>看「已勾选项」有没有勾、「禁用项」是不是灰的</li>
  <li>试试「隐藏窗口」和「显示窗口」</li>
  <li>最后点「退出」结束</li>
</ol>
<p style="color:#888;font-size:13px">20 秒后会尝试自动退出；若本机 WebView2 没渲染页面，这个定时器不会触发。</p>
</body>
HTML);

echo "[提示] 窗口已显示，去系统托盘找图标吧。\n";

$win->run();
$win->destroy();

echo "\n=== 演示结束 ===\n";
