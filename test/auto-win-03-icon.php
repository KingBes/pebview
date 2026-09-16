<?php

// 严格模式
declare(strict_types=1);

/**
 * auto-win-03 —— 窗口图标
 *
 * 图标格式各平台要求不同：Windows / macOS 要 ico，Linux 要 png。
 * 错误路径（文件不存在）必须抛异常 —— 之前它是"静默无事发生"，图标没设上也没有任何提示。
 */

use Kingbes\PebView\Window;

/** @var PebTest\Harness $T */

$T->section('auto-win-03-icon.php  (setIcon 正常与错误路径)');

$loadError = $T->ffiLoadError();
if ($loadError !== null) {
    $T->skip('auto-win-03 的全部用例', $loadError);
    return;
}

$icon = PHP_OS_FAMILY === 'Linux' ? __DIR__ . '/icon.png' : __DIR__ . '/php.ico';

$T->check('setIcon(' . basename($icon) . ') 返回 self', function () use ($T, $icon) {
    $T->assertTrue(is_file($icon), '测试用图标文件存在: ' . $icon);

    $win = new Window(false);
    try {
        $T->assertSame($win, $win->setIcon($icon));
    } finally {
        $win->hide();
        $win->destroy();
    }
});

$T->check('setIcon(不存在的文件) 抛 RuntimeException', function () use ($T) {
    $win = new Window(false);
    try {
        $T->assertThrows(
            \RuntimeException::class,
            fn() => $win->setIcon(__DIR__ . '/__nope__.ico'),
            '图标文件不存在'
        );
    } finally {
        $win->hide();
        $win->destroy();
    }
});
