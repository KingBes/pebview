<?php

// 根据你的实际情况，修改下面的路径
require dirname(__DIR__) . "/vendor/autoload.php";

/**
 * 交互组 —— 标题栏外观（setTitlebarTheme）
 *
 * 跑法：
 *     php -d extension=ffi -d ffi.enable=1 test/demo-titlebar.php
 *
 * 依次应用 5 种标题栏配置，每种停留 3 秒，盯着窗口标题栏看颜色是否跟着变。
 *
 * 各平台能看到的效果：
 *   Windows  全部 5 种（浅色 / 深色 / 自定义配色需要 Win11）
 *   macOS    只有浅色 / 深色（自定义配色会抛异常，脚本里已按平台跳过）
 *   Linux    自定义配色那两种（会切换成 CSD 自绘标题栏）
 *
 * 观察点：
 *   1. 「跟随系统」是不是和系统当前主题一致
 *   2. 浅色 / 深色 两次切换，标题栏底色和文字色是否都反过来了
 *   3. 自定义配色两条，标题栏是否变成了指定的颜色（深蓝灰底+琥珀字 / 洋红底+白字）
 *
 * 退出方式：没有事件循环，5 种配置各停 3 秒后自动结束（约 15 秒）。
 *          中途 Ctrl+C 也可以。
 *
 * 注意：本机 WebView2 不渲染页面内容，但标题栏是原生绘制的，不受影响 ——
 *      这台机器上实测过像素采样，配色是真实生效的。
 */

use Kingbes\PebView\Window;
use Kingbes\PebView\WindowHint;

$family = PHP_OS_FAMILY;

echo "=== PebView 交互演示：标题栏外观 ===\n";
echo "当前平台: {$family}\n";
echo "接下来每 3 秒换一种配置，注意看窗口最上面那条。\n";

$win = new Window(false);

$win->setTitle('标题栏演示')
    ->setSize(760, 480, WindowHint::None)
    ->setIcon(PHP_OS_FAMILY === 'Linux' ? __DIR__ . '/icon.png' : __DIR__ . '/php.ico')
    ->setHtml(<<<'HTML'
<body style="font-family: system-ui, sans-serif; margin:0; padding:28px; background:#fff">
<h1 style="font-size:20px; margin:0 0 12px">标题栏外观演示</h1>
<p>下面这条记录的是「本次要应用的配置」，对照窗口标题栏看是否一致。</p>
<div id="now" style="font: 14px/1.8 ui-monospace, monospace; padding:12px 14px;
     background:#f4f6f8; border-radius:8px; white-space:pre-wrap"></div>
<script>
// 由 PHP 侧通过 eval 写入当前配置说明
window.__setNow = function (text) { document.getElementById('now').textContent = text; };
</script>
</body>
HTML);

// 关窗就真关，避免挂死
$win->setCloseCallback(function () {
    echo "[事件] 关闭回调触发，允许关闭。\n";
    return true;
});

/** 应用一种配置并等待观察 */
$apply = function (string $label, callable $fn) use ($win): void {
    echo "\n[配置] {$label}\n";
    try {
        $fn();
        echo "       已应用。\n";
    } catch (\Throwable $e) {
        echo "       [本平台不支持] " . get_class($e) . ': ' . $e->getMessage() . "\n";
        return;
    }
    $win->eval('window.__setNow && window.__setNow(' . json_encode($label, JSON_UNESCAPED_UNICODE) . ');');
    sleep(3);
};

echo "\n（每步停留 3 秒）\n";

// 1
$apply('1/5 跟随系统（不传参数，等价于跟随系统主题）', function () use ($win) {
    $win->setTitlebarTheme();
});

// 2
$apply('2/5 浅色标题栏（dark: false）', function () use ($win) {
    $win->setTitlebarTheme(dark: false);
});

// 3
$apply('3/5 深色标题栏（dark: true）', function () use ($win) {
    $win->setTitlebarTheme(dark: true);
});

// 4
$apply('4/5 自定义配色 A：深蓝灰底 #1F2430 + 琥珀字 #FFD166', function () use ($win) {
    $win->setTitlebarTheme(dark: true, caption: '#1F2430', text: '#FFD166');
});

// 5
$apply('5/5 自定义配色 B：洋红底 #C2185B + 白字 #FFFFFF', function () use ($win) {
    $win->setTitlebarTheme(dark: true, caption: '#C2185B', text: '#FFFFFF');
});

// 非法颜色应当抛异常（顺便演示边界行为）
$apply('附加：非法颜色 #ZZZZZZ（应当抛 InvalidArgumentException，而不是静默用默认色）', function () use ($win) {
    $win->setTitlebarTheme(caption: '#ZZZZZZ');
});

echo "\n=== 演示结束 ===\n";
echo "如果 4/5、5/5 两步颜色没变化：Windows 上说明系统低于 Win11（只有浅/深色可用），\n";
echo "这在文档里是如实标注的；Linux/macOS 的能力矩阵见 doc/Chinese/Window.md。\n";

$win->destroy();
