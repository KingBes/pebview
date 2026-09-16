<?php

// 严格模式
declare(strict_types=1);

/**
 * auto-09 —— Dialog 公开签名契约
 *
 * Dialog 的三个方法都会弹出**模态**原生对话框，会阻塞到用户点击为止，
 * 无人值守跑必然永久挂起。所以真实弹窗演示放在 test/demo-dialog.php，
 * 这里只锁公开签名（静态性、可见性、参数个数、默认值、返回类型）。
 *
 * 签名是能静态验证的，而它一旦写错，用户代码会直接报错 —— 值得锁。
 */

use Kingbes\PebView\Dialog;
use Kingbes\PebView\DialogBtn;
use Kingbes\PebView\DialogLevel;

/** @var PebTest\Harness $T */

$T->section('auto-05-dialog-contract.php  (Dialog 公开签名契约)');

/**
 * @return array{0: bool, 1: bool, 2: int, 3: array<int, mixed>, 4: string}
 *         [isStatic, isPublic, 参数个数, 默认值列表, 返回类型]
 */
$inspect = static function (string $method): array {
    $ref = new ReflectionMethod(Dialog::class, $method);
    $defaults = [];
    foreach ($ref->getParameters() as $p) {
        $defaults[] = $p->isDefaultValueAvailable() ? $p->getDefaultValue() : '(required)';
    }
    return [
        $ref->isStatic(),
        $ref->isPublic(),
        count($ref->getParameters()),
        $defaults,
        (string) $ref->getReturnType(),
    ];
};

$T->check('Dialog::msg(string, DialogLevel=Info, DialogBtn=Ok): bool', function () use ($T, $inspect) {
    [$isStatic, $isPublic, $count, $defaults, $return] = $inspect('msg');

    $T->assertTrue($isStatic, 'msg 应当是静态方法');
    $T->assertTrue($isPublic, 'msg 应当是 public');
    $T->assertSame(3, $count, '参数个数');
    $T->assertSame('(required)', $defaults[0], 'message 必填');
    $T->assertSame(DialogLevel::Info, $defaults[1], '第二个参数默认应为 DialogLevel::Info');
    $T->assertSame(DialogBtn::Ok, $defaults[2], '第三个参数默认应为 DialogBtn::Ok');
    $T->assertSame('bool', $return);
});

$T->check('Dialog::prompt(string, DialogLevel=Info, string=""): string', function () use ($T, $inspect) {
    [$isStatic, $isPublic, $count, $defaults, $return] = $inspect('prompt');

    $T->assertTrue($isStatic, 'prompt 应当是静态方法');
    $T->assertTrue($isPublic, 'prompt 应当是 public');
    $T->assertSame(3, $count, '参数个数');
    $T->assertSame('(required)', $defaults[0], 'message 必填');
    $T->assertSame(DialogLevel::Info, $defaults[1]);
    $T->assertSame('', $defaults[2], '第三个参数默认应为空串');
    $T->assertSame('string', $return);
});

$T->check('Dialog::file(string, string, FileAction, string=""): string', function () use ($T, $inspect) {
    [$isStatic, $isPublic, $count, $defaults, $return] = $inspect('file');

    $T->assertTrue($isStatic, 'file 应当是静态方法');
    $T->assertTrue($isPublic, 'file 应当是 public');
    $T->assertSame(4, $count, '参数个数');
    $T->assertSame('(required)', $defaults[0], 'dir 必填');
    $T->assertSame('(required)', $defaults[1], 'filename 必填');
    $T->assertSame('(required)', $defaults[2], 'FileAction 没有默认值');
    $T->assertSame('', $defaults[3], 'filters 默认应为空串');
    $T->assertSame('string', $return);
});

$T->skip(
    'Dialog::msg / prompt / file 的真实调用',
    'osdialog_* 是模态对话框，会阻塞到用户点击为止，无人值守会永久挂起。'
        . '真实弹窗演示见 test/demo-dialog.php'
);
