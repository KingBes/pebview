<?php

// 严格模式
declare(strict_types=1);

/**
 * auto-win-10 —— 区域白名单点击穿透（setClickThroughRegions）
 *
 * 本文件只做契约层验证，不依赖真实鼠标移动：
 *   - 两种矩形格式（列表 / 关联）与混用、浮点坐标都能被接受（返回 self）；
 *     Linux 在 run() 之前按契约抛"不支持"（input shape 的 realize 时机约束）
 *   - 非法输入（格式错、坐标非数字、宽高为负）在 PHP 侧就抛 InvalidArgumentException
 *   - 与整窗穿透的互切链全程不崩（最后调用者获胜）
 *   - FFI 直测原生返回码（Windows）：设区域 = 0、count=-1 = 4、rects=NULL 且 count>0 = 4、
 *     退出 = 0。真实"透明区穿透、内容可点"是交互行为，交给 demo-click-through.php 人工验收
 *
 * 属于窗口组（默认不跑），原因见 test/README.md。
 */

use Kingbes\PebView\Base;
use Kingbes\PebView\Window;

/** @var PebTest\Harness $T */

$T->section('auto-win-10-click-through-regions.php  (区域白名单点击穿透)');

$loadError = $T->ffiLoadError();
if ($loadError !== null) {
    $T->skip('auto-win-10 的全部用例', $loadError);
    return;
}

/** 拿到窗口的原生句柄（与 auto-win-09 同款） */
$hwndOf = static function (Window $w) {
    $ref = new ReflectionProperty(Window::class, 'pv');
    $ref->setAccessible(true);
    return Base::ffi()->webview_get_window($ref->getValue($w));
};

$win = new Window(false);

$T->check('空数组退出区域模式：返回 self 或按契约抛"不支持"', function () use ($T, $win) {
    try {
        $T->assertSame($win, $win->setClickThroughRegions([]));
    } catch (\RuntimeException $e) {
        if (!str_contains($e->getMessage(), '不支持')) {
            throw $e;
        }
        $T->assertSame(true, true, '本平台时机不支持（Linux 需 run() 后），按契约抛 RuntimeException（非静默）');
    }
});

$T->check('列表格式矩形返回 self 或按契约抛"不支持"', function () use ($T, $win) {
    try {
        $T->assertSame($win, $win->setClickThroughRegions([[10, 10, 100, 40]]));
    } catch (\RuntimeException $e) {
        if (!str_contains($e->getMessage(), '不支持')) {
            throw $e;
        }
        $T->assertSame(true, true, '本平台时机不支持，按契约抛 RuntimeException（非静默）');
    }
});

$T->check('关联格式矩形返回 self 或按契约抛"不支持"', function () use ($T, $win) {
    try {
        $T->assertSame($win, $win->setClickThroughRegions([['x' => 5, 'y' => 5, 'w' => 50, 'h' => 30]]));
    } catch (\RuntimeException $e) {
        if (!str_contains($e->getMessage(), '不支持')) {
            throw $e;
        }
        $T->assertSame(true, true, '本平台时机不支持，按契约抛 RuntimeException（非静默）');
    }
});

$T->check('混合格式 + 浮点坐标返回 self 或按契约抛"不支持"', function () use ($T, $win) {
    try {
        $T->assertSame($win, $win->setClickThroughRegions([
            [1.5, 2, 30, 20],
            ['x' => 0, 'y' => 0, 'w' => 10.5, 'h' => 10],
        ]));
    } catch (\RuntimeException $e) {
        if (!str_contains($e->getMessage(), '不支持')) {
            throw $e;
        }
        $T->assertSame(true, true, '本平台时机不支持，按契约抛 RuntimeException（非静默）');
    }
});

$T->check('非法输入：PHP 侧就抛 InvalidArgumentException（不进 FFI）', function () use ($T, $win) {
    $cases = [
        '缺坐标的列表' => [[1, 2, 3]],
        '关联缺键' => [['x' => 1, 'y' => 2, 'w' => 3]],
        '坐标非数字' => [[1, 2, 'x', 4]],
        '负宽度' => [[0, 0, -5, 10]],
        '负高度' => [['x' => 0, 'y' => 0, 'w' => 10, 'h' => -1]],
    ];
    foreach ($cases as $name => $rects) {
        try {
            $win->setClickThroughRegions($rects);
            $T->assertTrue(false, "{$name} 应当抛 InvalidArgumentException，却正常返回了");
        } catch (\InvalidArgumentException) {
            $T->assertSame(true, true, "{$name} 按契约抛 InvalidArgumentException");
        }
    }
});

$T->check('互切链 区域 → 整窗 → 关闭 → 退出 全程不崩', function () use ($T, $win) {
    try {
        $win->setClickThroughRegions([[0, 0, 50, 50]]);
        $win->setClickThrough(true);
        $win->setClickThrough(false);
        $T->assertSame($win, $win->setClickThroughRegions([]));
    } catch (\RuntimeException $e) {
        if (!str_contains($e->getMessage(), '不支持')) {
            throw $e;
        }
        $T->assertSame(true, true, '本平台时机不支持，按契约抛 RuntimeException（非静默）');
    }
});

$T->check('FFI 直测原生返回码（Win32 语义）', function () use ($T, $win, $hwndOf) {
    $T->skipIf(PHP_OS_FAMILY !== 'Windows', '返回码断言按 Win32 语义（Linux 的 realize 约束已由上面按契约覆盖）');

    $hwnd = $hwndOf($win);
    $ffi = Base::ffi();

    // 正常设一个矩形 = 0
    $arr = $ffi->new('int[4]');
    $arr[0] = 0;
    $arr[1] = 0;
    $arr[2] = 100;
    $arr[3] = 40;
    $T->assertSame(0, (int) $ffi->window_set_click_through_regions($hwnd, $arr, 1), '设区域应返回 0');

    // count = -1 -> 参数非法 4
    $T->assertSame(4, (int) $ffi->window_set_click_through_regions($hwnd, $arr, -1), 'count<0 应返回 4');

    // count>0 但 rects=NULL -> 参数非法 4
    $T->assertSame(4, (int) $ffi->window_set_click_through_regions($hwnd, null, 2), 'count>0 且 rects=NULL 应返回 4');

    // 退出（count=0）-> 0
    $T->assertSame(0, (int) $ffi->window_set_click_through_regions($hwnd, null, 0), '退出区域模式应返回 0');
});

$win->hide();
$win->destroy();
