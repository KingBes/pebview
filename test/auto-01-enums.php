<?php

// 严格模式
declare(strict_types=1);

/**
 * auto-01 —— 四个枚举的全部取值
 *
 * 纯函数，不需要 FFI（FFI 没开时这一档照样全跑）。
 * 枚举的 int 值直接透传给 C ABI，改错会让整条调用链的参数错位，所以逐个锁死。
 */

use Kingbes\PebView\DialogBtn;
use Kingbes\PebView\DialogLevel;
use Kingbes\PebView\FileAction;
use Kingbes\PebView\WindowHint;

/** @var PebTest\Harness $T */

$T->section('auto-01-enums.php  (四个枚举的全部取值)');

$T->check('WindowHint: None/Min/Max/Fixed = 0/1/2/3', function () use ($T) {
    $T->assertSame(0, WindowHint::None->value);
    $T->assertSame(1, WindowHint::Min->value);
    $T->assertSame(2, WindowHint::Max->value);
    $T->assertSame(3, WindowHint::Fixed->value);
});

$T->check('DialogLevel: Info/Warning/Error = 0/1/2', function () use ($T) {
    $T->assertSame(0, DialogLevel::Info->value);
    $T->assertSame(1, DialogLevel::Warning->value);
    $T->assertSame(2, DialogLevel::Error->value);
});

$T->check('DialogBtn: Ok/OkCancel/YesNo = 0/1/2', function () use ($T) {
    $T->assertSame(0, DialogBtn::Ok->value);
    $T->assertSame(1, DialogBtn::OkCancel->value);
    $T->assertSame(2, DialogBtn::YesNo->value);
});

$T->check('FileAction: Open/OpenDir/Save = 0/1/2', function () use ($T) {
    $T->assertSame(0, FileAction::Open->value);
    $T->assertSame(1, FileAction::OpenDir->value);
    $T->assertSame(2, FileAction::Save->value);
});
