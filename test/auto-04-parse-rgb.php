<?php

// 严格模式
declare(strict_types=1);

/**
 * auto-04 —— 颜色字符串解析（parseRgb）
 *
 * 用反射的理由：parseRgb 是 Window 的 private static，只被 setTitlebarTheme()
 * 内部调用，没有公开入口能单独喂它一个颜色字符串。它是纯函数（字符串 → int），
 * 隔离单测最直接，也最快。
 *
 * 锁住的行为：null / 空串表示"C 侧不覆盖该颜色"（-1），其余非法输入必须抛
 * InvalidArgumentException，而不是悄悄退回系统默认值 —— 后者会让用户以为颜色设上了。
 */

use Kingbes\PebView\Window;

/** @var PebTest\Harness $T */

$T->section('auto-04-parse-rgb.php  (颜色字符串解析)');

$parseRgb = $T->reflect(Window::class, 'parseRgb');

$T->check('null / 空串 → -1（C 侧语义是"不覆盖该颜色"）', function () use ($T, $parseRgb) {
    $T->assertSame(-1, $parseRgb->invoke(null, null));
    $T->assertSame(-1, $parseRgb->invoke(null, ''));
});

$T->check('#RRGGBB 与 RRGGBB 都能解析成同一个 int', function () use ($T, $parseRgb) {
    $T->assertSame(0x1F2430, $parseRgb->invoke(null, '#1F2430'));
    $T->assertSame(0x1F2430, $parseRgb->invoke(null, '1F2430'), '不带 # 也要能解析');
    $T->assertSame(0xABCDEF, $parseRgb->invoke(null, '#abcdef'), '小写要能解析');
    $T->assertSame(0xFF0000, $parseRgb->invoke(null, 'ff0000'));
    $T->assertSame(0x000000, $parseRgb->invoke(null, '#000000'), '全黑是合法值');
    $T->assertSame(0xFFFFFF, $parseRgb->invoke(null, '#FFFFFF'), '全白是合法值');
});

$T->check('非法颜色一律抛 InvalidArgumentException', function () use ($T, $parseRgb) {
    foreach (['#12345', '#GGGGGG', '红色', '#1234567', '#'] as $bad) {
        $T->assertThrows(
            \InvalidArgumentException::class,
            fn() => $parseRgb->invoke(null, $bad),
            null,
            "input={$bad}"
        );
    }
});
