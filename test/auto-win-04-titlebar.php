<?php

// 严格模式
declare(strict_types=1);

/**
 * auto-win-04 —— 标题栏外观（setTitlebarTheme）
 *
 * 颜色字符串解析（parseRgb）的边界已经独立在 auto-04-parse-rgb.php 里测过，
 * 那是纯函数、不需要窗口。这里只验证需要真实窗口的部分。
 *
 * 各平台能力不同，契约就是"做不到的组合要抛异常，不许静默无效"，
 * 所以断言必须按 PHP_OS_FAMILY 分支写 —— 否则换个平台跑就会假失败。
 *
 * 能力矩阵（与 doc/Chinese/Window.md 一致）：
 *                  浅/深色        自定义配色
 *   Windows        支持           支持（配色需要 Win11）
 *   macOS          支持           不支持 —— 标题栏由系统绘制，AppKit 无公开接口
 *   Linux          不支持         支持 —— 走 CSD 自绘标题栏（GtkHeaderBar + CSS）
 */

use Kingbes\PebView\Window;

/** @var PebTest\Harness $T */

$T->section('auto-win-04-titlebar.php  (标题栏外观)');

$loadError = $T->ffiLoadError();
if ($loadError !== null) {
    $T->skip('auto-06 的全部用例', $loadError);
    return;
}

$newWin = static fn(): Window => new Window(false);

$family = PHP_OS_FAMILY;

// ------------------------------------------------------------ 平台能力矩阵

$T->check("setTitlebarTheme(dark: true) 在 {$family} 上的行为", function () use ($T, $family, $newWin) {
    $T->skipIf(!in_array($family, ['Windows', 'Darwin', 'Linux'], true), "不支持的平台: {$family}");

    $win = $newWin();
    try {
        if ($family === 'Linux') {
            // GTK 改主题是全局的，做不到"只调标题栏深浅"
            $T->assertThrows(\RuntimeException::class, fn() => $win->setTitlebarTheme(dark: true));
        } else {
            $T->assertSame($win, $win->setTitlebarTheme(dark: true));
        }
    } finally {
        $win->hide();
        $win->destroy();
    }
});

$T->check("setTitlebarTheme(dark: false) 在 {$family} 上的行为", function () use ($T, $family, $newWin) {
    $T->skipIf(!in_array($family, ['Windows', 'Darwin', 'Linux'], true), "不支持的平台: {$family}");

    $win = $newWin();
    try {
        if ($family === 'Linux') {
            $T->assertThrows(\RuntimeException::class, fn() => $win->setTitlebarTheme(dark: false));
        } else {
            $T->assertSame($win, $win->setTitlebarTheme(dark: false));
        }
    } finally {
        $win->hide();
        $win->destroy();
    }
});

$T->check("setTitlebarTheme(caption, text) 在 {$family} 上的行为", function () use ($T, $family, $newWin) {
    $T->skipIf(!in_array($family, ['Windows', 'Darwin', 'Linux'], true), "不支持的平台: {$family}");

    $win = $newWin();
    try {
        if ($family === 'Darwin') {
            // AppKit 没有改标题栏配色的接口 → 契约要求抛异常而不是静默无效
            $T->assertThrows(
                \RuntimeException::class,
                fn() => $win->setTitlebarTheme(dark: true, caption: '#1F2430', text: '#FFFFFF')
            );
        } else {
            $T->assertSame($win, $win->setTitlebarTheme(dark: true, caption: '#1F2430', text: '#FFFFFF'));
        }
    } finally {
        $win->hide();
        $win->destroy();
    }
});

$T->check('setTitlebarTheme() 全 null（跟随系统）返回 self', function () use ($T, $family, $newWin) {
    // Linux 上"只跟随系统深浅、不动配色"无法表达，会返回不支持
    $T->skipIf($family === 'Linux', 'Linux 上无法只改标题栏深浅，该调用会抛异常');

    $win = $newWin();
    try {
        $T->assertSame($win, $win->setTitlebarTheme());
    } finally {
        $win->hide();
        $win->destroy();
    }
});

// ------------------------------------------------------------ 颜色校验（需要窗口）

$T->check('非法颜色抛 InvalidArgumentException（不会被静默忽略）', function () use ($T, $newWin) {
    $T->skipIf(!in_array(PHP_OS_FAMILY, ['Windows', 'Linux'], true), '本平台不支持配色，进入不了颜色解析这一步');

    $win = $newWin();
    try {
        $T->assertThrows(\InvalidArgumentException::class, fn() => $win->setTitlebarTheme(caption: '#ZZZZZZ'));
    } finally {
        $win->hide();
        $win->destroy();
    }
});
