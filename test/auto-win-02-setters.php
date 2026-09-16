<?php

// 严格模式
declare(strict_types=1);

/**
 * auto-win-02 —— 窗口 setter 的正常路径
 *
 * 每个 setter 都只用"链式返回 self + 不抛异常"来判定。
 * 真正让它们生效需要完整的事件循环与 WebView2 渲染，那属于交互组
 * （见 test/demo-run-loop.php），本机环境也验证不了。
 */

use Kingbes\PebView\Window;
use Kingbes\PebView\WindowHint;

/** @var PebTest\Harness $T */

$T->section('auto-win-02-setters.php  (窗口 setter 的正常路径)');

$loadError = $T->ffiLoadError();
if ($loadError !== null) {
    $T->skip('auto-win-02 的全部用例', $loadError);
    return;
}

$win = new Window(false);

$T->check('setTitle 返回 self', function () use ($T, $win) {
    $T->assertSame($win, $win->setTitle('中文标题 with English'));
});

$T->check('setSize(w, h, WindowHint::Fixed) 返回 self', function () use ($T, $win) {
    $T->assertSame($win, $win->setSize(640, 480, WindowHint::Fixed));
});

$T->check('setSize(w, h) 用默认 WindowHint 返回 self', function () use ($T, $win) {
    $T->assertSame($win, $win->setSize(800, 600));
});

$T->check('四个 WindowHint 取值都能接受', function () use ($T, $win) {
    foreach (WindowHint::cases() as $hint) {
        $T->assertSame($win, $win->setSize(400, 300, $hint), "hint={$hint->name}");
    }
});

$T->check('setHtml 返回 self', function () use ($T, $win) {
    $T->assertSame($win, $win->setHtml('<h1>setters</h1>'));
});

$T->check('init 返回 self', function () use ($T, $win) {
    $T->assertSame($win, $win->init('window.__probe = 1;'));
});

$T->check('eval 返回 self', function () use ($T, $win) {
    $T->assertSame($win, $win->eval('1 + 1;'));
});

$T->check('navigate 返回 self', function () use ($T, $win) {
    $T->assertSame($win, $win->navigate('about:blank'));
});

$T->check('show / hide 返回 self', function () use ($T, $win) {
    $T->assertSame($win, $win->show());
    $T->assertSame($win, $win->hide());
});

$T->check('destroy 返回 void 且不抛', function () use ($T) {
    $solo = new Window(false);
    $solo->hide();
    $T->assertNull($solo->destroy(), 'destroy() 应当没有返回值');
});

$win->hide();
$win->destroy();
