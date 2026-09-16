<?php

// 严格模式
declare(strict_types=1);

/**
 * auto-win-07 —— 错误路径集中档
 *
 * 与 auto-04 / 05 / 06 / 07 是有意重复的：那几个文件按功能域组织，
 * 而这一档把"什么情况会抛什么异常"集中成一张能一眼扫完的清单。
 *
 * 边界情况必须显式报错，静默无效是最难查的那类 bug ——
 * 这个项目里已经踩过两次：图标路径写错时无声无息（setIcon 原来是丢弃返回码），
 * 文件对话框的 filters 从不释放。
 */

use Kingbes\PebView\Window;

/** @var PebTest\Harness $T */

$T->section('auto-win-07-error-paths.php  (错误路径集中档)');

$loadError = $T->ffiLoadError();

$T->check('setIcon(不存在的文件) → RuntimeException', function () use ($T, $loadError) {
    $T->skipIf($loadError !== null, (string) $loadError);

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

$T->check('setIcon 抛的是 RuntimeException，不是参数校验类异常', function () use ($T, $loadError) {
    $T->skipIf($loadError !== null, (string) $loadError);

    $win = new Window(false);
    try {
        $thrown = null;
        try {
            $win->setIcon(__DIR__ . '/__nope__.ico');
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $T->assertInstanceOf(\RuntimeException::class, $thrown);
        $T->assertFalse($thrown instanceof \InvalidArgumentException, '不应当是参数校验类异常');
    } finally {
        $win->hide();
        $win->destroy();
    }
});

$T->check('setTitlebarTheme(非法颜色) → InvalidArgumentException', function () use ($T, $loadError) {
    $T->skipIf($loadError !== null, (string) $loadError);

    $win = new Window(false);
    try {
        // 修复前若静默用系统默认值继续跑，用户会以为颜色设上了
        $T->assertThrows(
            \InvalidArgumentException::class,
            fn() => $win->setTitlebarTheme(caption: 'not-a-color')
        );
    } finally {
        $win->hide();
        $win->destroy();
    }
});

$T->check('setTitlebarTheme(平台不支持的颜色组合) → RuntimeException', function () use ($T, $loadError) {
    $T->skipIf($loadError !== null, (string) $loadError);
    $T->skipIf(
        PHP_OS_FAMILY !== 'Darwin',
        '只有 macOS 存在"支持深浅色但不支持配色"这种组合，当前平台 ' . PHP_OS_FAMILY . ' 不适用'
    );

    $win = new Window(false);
    try {
        $T->assertThrows(
            \RuntimeException::class,
            fn() => $win->setTitlebarTheme(caption: '#1F2430')
        );
    } finally {
        $win->hide();
        $win->destroy();
    }
});

$T->check('encodeResult(INF / NAN) → status=1（既不抛异常也不是 0）', function () use ($T) {
    $encode = $T->reflect(Window::class, 'encodeResult');

    foreach ([INF, NAN] as $input) {
        $label = is_nan($input) ? 'NAN' : 'INF';
        [$status, $payload] = $encode->invoke(null, $input);

        $T->assertSame(1, $status, "input={$label}；status=0 会让 JS 侧静默 resolve(undefined)");
        $T->assertTrue($payload !== '', "input={$label} 的 payload 不应为空串");
    }
});
