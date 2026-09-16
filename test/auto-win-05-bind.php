<?php

// 严格模式
declare(strict_types=1);

/**
 * auto-win-05 —— bind / unBind 的公开入口
 *
 * encodeResult() 的边界（falsy / 转义 / INF、NAN）已经独立在 auto-03-encode-result.php
 * 里用反射测过了 —— 它是纯函数、不依赖窗口。这里只验证需要真实窗口的那部分。
 *
 * 注意：webview_bind 内部会往页面注入 JS，所以它依赖 WebView2 真的能工作。
 * 本机环境下窗口操作不稳定，本文件属于窗口组（默认不跑）。
 * JS ↔ PHP 双向调用的真实效果请看交互组 test/demo-bind.php。
 */

use Kingbes\PebView\Window;

/** @var PebTest\Harness $T */

$T->section('auto-win-05-bind.php  (bind / unBind 公开入口)');

$loadError = $T->ffiLoadError();
if ($loadError !== null) {
    $T->skip('auto-win-05 的全部用例', $loadError);
    return;
}

$T->check('bind() 返回 self 且不抛', function () use ($T) {
    $win = new Window(false);
    try {
        $T->assertSame($win, $win->bind('php_probe', fn(...$args) => 'ok'));
    } finally {
        $win->hide();
        $win->destroy();
    }
});

$T->check('unBind() 返回 self 且不抛', function () use ($T) {
    $win = new Window(false);
    try {
        $win->bind('php_probe', fn(...$args) => 'ok');
        $T->assertSame($win, $win->unBind('php_probe'));
    } finally {
        $win->hide();
        $win->destroy();
    }
});

$T->check('解绑一个从没绑定过的名字不抛（幂等边界）', function () use ($T) {
    $win = new Window(false);
    try {
        $T->assertSame($win, $win->unBind('never_bound'));
    } finally {
        $win->hide();
        $win->destroy();
    }
});
