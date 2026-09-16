<?php

// 严格模式
declare(strict_types=1);

/**
 * auto-win-08 —— 多窗口连续性
 *
 * 单独成档是有意的：这一档会连续创建/销毁多个窗口，正是本机最容易触发
 * WebView2 销毁死锁的场景（异步初始化还没完成窗口就被销毁）。
 *
 * 父进程给每个用例文件都套了 30 秒超时、且每个文件独占一个子进程，
 * 所以这里卡住只会影响这一档，不会污染其它用例，也能被明确报出来。
 *
 * 两轮窗口之间用 $T->settle() 插入间隔，这是本机实测能稳定跑通的关键。
 */

use Kingbes\PebView\Window;
use Kingbes\PebView\WindowHint;

/** @var PebTest\Harness $T */

$T->section('auto-win-08-multi-window.php  (多窗口连续性)');

$loadError = $T->ffiLoadError();
if ($loadError !== null) {
    $T->skip('auto-win-08 的全部用例', $loadError);
    return;
}

$T->check('连续创建/配置/销毁 5 个实例都正常', function () use ($T) {
    $done = 0;
    for ($i = 0; $i < 5; $i++) {
        $win = new Window(false);
        $win->setTitle("cycle {$i}")->setSize(320, 240, WindowHint::Max);
        $win->hide();
        $win->destroy();
        $done++;
        $T->settle(); // 同一条用例内连续操作窗口，必须手动插入间隔
    }
    $T->assertSame(5, $done);
});

$T->check('销毁一个窗口后能立刻再建一个（$tray 状态各自独立）', function () use ($T) {
    $first = new Window(false);
    $first->hide();
    $first->destroy();
    $T->settle();

    $second = new Window(false);
    try {
        $T->assertInstanceOf(Window::class, $second);
        $T->assertNull($second->tray, '新实例的 $tray 应重新为 null');
    } finally {
        $second->hide();
        $second->destroy();
    }
});
