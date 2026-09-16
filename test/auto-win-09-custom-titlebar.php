<?php

// 严格模式
declare(strict_types=1);

/**
 * auto-win-09 —— 自定义标题栏（真正无边框 + JS 驱动缩放）
 *
 * 验证方式刻意不看"有没有抛异常"，而是把窗口状态读回来核对：
 *   - 启用后窗口真正无边框：非客户区厚度（宽差/高差）应为 0，即零顶部默认边距
 *   - 切换标题栏不该改变客户区尺寸（setSize 的语义是内容区尺寸）
 *   - 缩放改由 JS 驱动：beginResize(edge) 三平台统一返回 self；不再依赖系统
 *     WM_NCHITTEST 命中（WebView2 子窗口铺满客户区、吃掉鼠标，留边框也抓不到边缘）
 *   - 关闭后厚度还原成系统默认
 *   - 最大化状态与 C 侧 IsZoomed 一致
 *   - 状态回调只在真的切换时触发一次
 *
 * 属于窗口组（默认不跑），原因见 test/README.md。
 */

use Kingbes\PebView\Base;
use Kingbes\PebView\Window;

/** @var PebTest\Harness $T */

$T->section('auto-win-09-custom-titlebar.php  (自定义标题栏)');

$loadError = $T->ffiLoadError();
if ($loadError !== null) {
    $T->skip('auto-win-09 的全部用例', $loadError);
    return;
}

$user32 = FFI::cdef("
    typedef void* HWND;
    typedef struct { long left; long top; long right; long bottom; } RECT;
    typedef struct { long x; long y; } POINT;
    int       GetWindowRect(HWND hwnd, RECT* r);
    int       GetClientRect(HWND hwnd, RECT* r);
    int       ClientToScreen(HWND hwnd, POINT* p);
    int       GetClassNameA(HWND hwnd, char* buf, int max);
    HWND      GetWindow(HWND hwnd, unsigned int cmd);
    HWND      GetAncestor(HWND hwnd, unsigned int flags);
    HWND      WindowFromPoint(POINT pt);
    long long SendMessageW(HWND hwnd, unsigned int msg, unsigned long long wp, long long lp);
    int       IsZoomed(HWND hwnd);
    int       IsIconic(HWND hwnd);
", "user32.dll");

/** 拿到窗口的原生句柄 */
$hwndOf = static function (Window $w) {
    $ref = new ReflectionProperty(Window::class, 'pv');
    $ref->setAccessible(true);
    return Base::ffi()->webview_get_window($ref->getValue($w));
};

/** 非客户区厚度 [宽差, 高差]；高差就是系统标题栏的高度 */
$ncThickness = static function ($hwnd) use ($user32): array {
    $wr = $user32->new("RECT");
    $cr = $user32->new("RECT");
    $user32->GetWindowRect($hwnd, FFI::addr($wr));
    $user32->GetClientRect($hwnd, FFI::addr($cr));

    return [
        ($wr->right - $wr->left) - ($cr->right - $cr->left),
        ($wr->bottom - $wr->top) - ($cr->bottom - $cr->top),
    ];
};

/** 客户区尺寸 [宽, 高] */
$clientSize = static function ($hwnd) use ($user32): array {
    $cr = $user32->new("RECT");
    $user32->GetClientRect($hwnd, FFI::addr($cr));
    return [$cr->right, $cr->bottom];
};

$T->check('启用后窗口真正无边框（零非客户区厚度、零顶部边距）', function () use ($T, $hwndOf, $ncThickness) {
    $win = new Window(false);
    try {
        $win->setSize(600, 400)->show();
        $T->settle();

        $hwnd = $hwndOf($win);
        [$beforeW, $beforeH] = $ncThickness($hwnd);
        $T->assertTrue($beforeH > $beforeW, "系统标题栏下高差应大于宽差，实际 {$beforeH} vs {$beforeW}");

        $win->setCustomTitlebar(36, 138);
        $T->settle();

        // WM_NCCALCSIZE 直接 return 0：客户区 = 整个窗口，没有任何非客户区
        // （标题栏 / 边框都不要），所以宽差、高差都应为 0 —— 正是"无顶部默认边距"。
        [$afterW, $afterH] = $ncThickness($hwnd);
        $T->assertSame(0, $afterW, '启用后左右不应有非客户区（无边框）');
        $T->assertSame(0, $afterH, '启用后上下不应有非客户区（零顶部边距）');
    } finally {
        $win->hide();
        $win->destroy();
    }
});

$T->check('切换自定义标题栏不改变客户区尺寸', function () use ($T, $hwndOf, $clientSize) {
    $win = new Window(false);
    try {
        $win->setSize(600, 400)->show();
        $T->settle();
        $hwnd = $hwndOf($win);
        $before = $clientSize($hwnd);

        $win->setCustomTitlebar(36, 138);
        $T->settle();
        $T->assertSame($before, $clientSize($hwnd), '启用后客户区尺寸不该变（setSize 说的是内容区）');

        $win->setCustomTitlebar(null);
        $T->settle();
        $T->assertSame($before, $clientSize($hwnd), '关闭后客户区尺寸也不该变');
    } finally {
        $win->hide();
        $win->destroy();
    }
});

$T->check('缩放由 JS 驱动：beginResize(各边/角) 三平台返回 self、不抛异常', function () use ($T) {
    // 窗口无边框后，WebView2 子窗口铺满整窗、吃掉鼠标，顶层窗口在边缘收不到
    // WM_NCHITTEST —— 所以"边缘能拖大"不再靠系统命中，而是 JS 在 mousedown 里判定
    // 边缘、调 beginResize(edge) 显式发起。这里验证这条统一的对外 API 在三平台都可用。
    $win = new Window(false);
    try {
        $win->setCustomTitlebar(36, 138);
        $T->settle();

        // edge 编码 1..8 = WMSZ_*（左/右/上/左上/右上/下/左下/右下）
        foreach ([1, 2, 3, 4, 5, 6, 7, 8] as $edge) {
            // 没有真实鼠标按下时走"左键已松开"分支（C 侧返回 4），PHP 侧直接忽略、
            // 返回 self —— 不抛异常、也不依赖平台。真正的缩放由 JS 在 mousedown 里发起。
            $T->assertSame($win, $win->beginResize($edge), "beginResize({$edge}) 应返回 self");
        }
    } finally {
        $win->hide();
        $win->destroy();
    }
});

$T->check('关闭自定义标题栏后非客户区还原', function () use ($T, $hwndOf, $ncThickness) {
    $win = new Window(false);
    try {
        $win->setSize(600, 400)->show();
        $T->settle();
        $hwnd = $hwndOf($win);
        [$w0, $h0] = $ncThickness($hwnd);

        $win->setCustomTitlebar(36, 138);
        $T->settle();
        $win->setCustomTitlebar(null);
        $T->settle();

        [$w1, $h1] = $ncThickness($hwnd);
        $T->assertSame($w0, $w1, '宽度方向的非客户区应当还原');
        $T->assertSame($h0, $h1, '关闭后标题栏高度应当还原成系统默认');
    } finally {
        $win->hide();
        $win->destroy();
    }
});

$T->check('toggleMaximize 与 isMaximized / IsZoomed 三边一致', function () use ($T, $hwndOf, $user32) {
    $win = new Window(false);
    try {
        $win->setSize(600, 400)->show();
        $T->settle();

        $hwnd = $hwndOf($win);
        $T->assertFalse($win->isMaximized(), '初始不该是最大化');

        $T->assertTrue($win->toggleMaximize(), '第一次切换应当变成最大化');
        $T->settle();
        $T->assertTrue($win->isMaximized(), 'isMaximized 应当为 true');
        $T->assertSame(1, $user32->IsZoomed($hwnd), 'C 侧 IsZoomed 应当一致');

        $T->assertFalse($win->toggleMaximize(), '再切换应当还原');
        $T->settle();
        $T->assertFalse($win->isMaximized());
        $T->assertSame(0, $user32->IsZoomed($hwnd));
    } finally {
        $win->hide();
        $win->destroy();
    }
});

$T->check('状态回调只在真的切换时各触发一次', function () use ($T) {
    $win = new Window(false);
    try {
        $win->setSize(600, 400)->show();
        $T->settle();

        $events = [];
        $win->onStateChange(function ($w, bool $maximized) use (&$events) {
            $events[] = $maximized ? 'max' : 'restore';
        });

        $win->toggleMaximize();
        $T->settle();
        $win->toggleMaximize();
        $T->settle();

        $T->assertSame(['max', 'restore'], $events, '应当恰好收到一次最大化 + 一次还原');
    } finally {
        $win->hide();
        $win->destroy();
    }
});

$T->check('minimize() 返回 self 且窗口进入最小化', function () use ($T, $hwndOf, $user32) {
    $win = new Window(false);
    try {
        $win->setSize(600, 400)->show();
        $T->settle();

        $T->assertSame($win, $win->minimize());
        $T->settle();

        $T->assertSame(1, $user32->IsIconic($hwndOf($win)), '最小化后 IsIconic 应当为 1');

        $win->show();
        $T->settle();
    } finally {
        $win->hide();
        $win->destroy();
    }
});

$T->check('beginDrag() 三平台统一：窗口存在时不抛异常、返回 self', function () use ($T) {
    $win = new Window(false);
    try {
        // 统一的写法：页面里一句 beginDrag(screenX, screenY) 三平台通用，不按平台分支。
        // 没有真实鼠标按下时走"左键已松开"分支（C 侧返回 4），PHP 侧直接忽略、
        // 返回 self —— 不抛异常、也不依赖平台。真正的拖动由 JS 在 mousedown 里发起。
        $T->assertSame($win, $win->beginDrag(100, 100));
        $T->assertSame($win, $win->beginDrag(0, 0));
    } finally {
        $win->hide();
        $win->destroy();
    }
});
