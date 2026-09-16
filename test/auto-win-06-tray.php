<?php

// 严格模式
declare(strict_types=1);

/**
 * auto-win-06 —— 托盘与托盘菜单
 *
 * 托盘图标是否真的出现在系统托盘里，要看桌面环境（属于交互组 test/demo-tray.php）。
 * 这里只验证调用层：返回 self、不抛、$tray 句柄状态符合预期。
 *
 * 注意清理：destroy() 只销毁 webview，**不会**移除托盘图标
 * （只有 terminate() 内部会调 trayRemove()）。所以本档统一用反射调私有 trayRemove()
 * 收尾，避免测试跑完在系统托盘里留下一堆图标。
 */

use Kingbes\PebView\Window;

/** @var PebTest\Harness $T */

$T->section('auto-win-06-tray.php  (托盘与托盘菜单)');

$loadError = $T->ffiLoadError();
if ($loadError !== null) {
    $T->skip('auto-win-06 的全部用例', $loadError);
    return;
}

$icon = PHP_OS_FAMILY === 'Linux' ? __DIR__ . '/icon.png' : __DIR__ . '/php.ico';
$removeTray = $T->reflect(Window::class, 'trayRemove');

/** 建窗口、跑断言、无论成败都清掉托盘并销毁窗口 */
$withTray = static function (callable $fn) use ($removeTray): void {
    $win = new Window(false);
    try {
        $fn($win);
    } finally {
        $removeTray->invoke($win); // 清托盘图标（$tray 为 null 时 C 侧判空，安全）
        $win->hide();
        $win->destroy();
    }
};

$T->check('新建实例的 $tray 为 null', function () use ($T, $withTray) {
    $withTray(function (Window $win) use ($T) {
        $T->assertNull($win->tray);
    });
});

$T->check('tray(图标) 返回 self 并拿到托盘句柄', function () use ($T, $withTray, $icon) {
    $withTray(function (Window $win) use ($T, $icon) {
        $T->assertSame($win, $win->tray($icon));
        // 某些平台 / 桌面环境不提供托盘，window_tray 会返回 NULL —— 那是环境限制，不是失败
        $T->skipIf($win->tray === null, '本平台 window_tray 返回空（无托盘支持），无法继续验证菜单');
        $T->assertNotNull($win->tray);
    });
});

$T->check('trayMenu 接受 text / disabled / checked / cb 四个字段', function () use ($T, $withTray, $icon) {
    $withTray(function (Window $win) use ($T, $icon) {
        $win->tray($icon);
        $T->skipIf($win->tray === null, '本平台 window_tray 返回空（无托盘支持）');

        $clicked = false;
        $ret = $win->trayMenu([
            ['text' => '打开窗口', 'cb' => fn($w) => $w->show()],
            ['text' => '已勾选项', 'checked' => 1, 'cb' => fn($w) => null],
            ['text' => '禁用项', 'disabled' => 1, 'cb' => fn($w) => null],
            ['text' => '退出', 'disabled' => 0, 'checked' => 0, 'cb' => function ($w) use (&$clicked) {
                $clicked = true;
            }],
        ]);

        $T->assertSame($win, $ret, 'trayMenu 应返回 self');
        $T->assertFalse($clicked, '回调不应在注册阶段就被调用');
    });
});

$T->check('trayMenu 空数组不抛（边界）', function () use ($T, $withTray, $icon) {
    $withTray(function (Window $win) use ($T, $icon) {
        $win->tray($icon);
        $T->skipIf($win->tray === null, '本平台 window_tray 返回空（无托盘支持）');
        $T->assertSame($win, $win->trayMenu([]));
    });
});
