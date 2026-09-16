<?php

// 严格模式
declare(strict_types=1);

/**
 * auto-win-01 —— Window 生命周期
 *
 * 约定：开窗用例创建后立刻 hide()（webview_create 内部就已经 ShowWindow(SW_SHOW)），
 * 用完 destroy()。
 * 任何用例都不调用 run() —— 本机 webview_run 不返回，一调就挂死整套测试。
 */

use Kingbes\PebView\Window;
use Kingbes\PebView\WindowHint;

/** @var PebTest\Harness $T */

$T->section('auto-win-01-lifecycle.php  (Window 生命周期)');

$loadError = $T->ffiLoadError();
if ($loadError !== null) {
    $T->skip('auto-win-01 的全部用例', $loadError);
    return;
}

$T->check('new Window() 创建实例', function () use ($T) {
    $win = new Window(false);
    try {
        $T->assertInstanceOf(Window::class, $win);
    } finally {
        $win->hide();
        $win->destroy();
    }
});

$T->check('新建实例的 $tray 是 null 且已初始化', function () use ($T) {
    $win = new Window(false);
    try {
        // 曾经的 P0：public mixed $tray; 没有默认值。没调 tray() 就读它
        // （terminate() 里第一件事就是 trayRemove()）会抛
        // "Typed property must not be accessed before initialization"。
        $ref = new ReflectionProperty(Window::class, 'tray');
        $T->assertTrue($ref->isInitialized($win), '$tray 应当已初始化（有默认值）');
        $T->assertNull($win->tray);
    } finally {
        $win->hide();
        $win->destroy();
    }
});

$T->check('未调 tray() 就直接走 trayRemove() 不抛（旧崩溃路径）', function () use ($T) {
    $win = new Window(false);
    try {
        $remove = $T->reflect(Window::class, 'trayRemove');
        $remove->invoke($win);
    } finally {
        $win->hide();
        $win->destroy();
    }
});

$T->check('setTitle/setSize/setHtml/init/eval/navigate 后销毁不抛', function () use ($T) {
    $win = new Window(false);
    try {
        $win->setTitle('生命周期')
            ->setSize(640, 480, WindowHint::None)
            ->setHtml('<h1>lifecycle</h1>')
            ->init('1')
            ->eval('1')
            ->navigate('about:blank');
    } finally {
        $win->hide();
        $win->destroy();
    }
});
