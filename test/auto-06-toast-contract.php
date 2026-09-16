<?php

// 严格模式
declare(strict_types=1);

/**
 * auto-10 —— Toast 公开签名契约
 *
 * 这里刻意**不**调用 Toast::show()，原因是它有两个问题：
 *   1. 有副作用 —— 每跑一次测试就真的弹一条系统通知、往通知中心里塞东西；
 *   2. 断言不了 —— 返回值只有 bool，无人值守时无法确认"通知确实出现过"。
 * 所以真实通知演示放在 test/demo-toast.php，由人工执行。
 */

use Kingbes\PebView\Base;
use Kingbes\PebView\Toast;

/** @var PebTest\Harness $T */

$T->section('auto-06-toast-contract.php  (Toast 公开签名契约)');

$T->check('Toast::show(string, string, string, string=""): bool 签名正确', function () use ($T) {
    $ref = new ReflectionMethod(Toast::class, 'show');
    $params = $ref->getParameters();

    $T->assertTrue($ref->isStatic(), 'show 应当是静态方法');
    $T->assertTrue($ref->isPublic(), 'show 应当是 public');
    $T->assertSame(4, count($params), '参数个数');
    $T->assertSame('bool', (string) $ref->getReturnType());

    foreach ([0, 1, 2] as $i) {
        $T->assertFalse(
            $params[$i]->isDefaultValueAvailable(),
            "第 " . ($i + 1) . " 个参数（{$params[$i]->getName()}）不应有默认值"
        );
    }
    $T->assertSame('', $params[3]->getDefaultValue(), '第四个参数 icon 默认应为空串');
});

$T->check('FFI 契约里 toastShow 可调用', function () use ($T) {
    $err = $T->ffiLoadError();
    // 注意要显式转 string：strict_types 下参数类型在**调用时**就校验，
    // 传 null 进来哪怕条件为 false 也会先抛 TypeError。
    $T->skipIf($err !== null, (string) $err);

    $ffi = Base::ffi();
    $T->assertTrue(is_callable($ffi->toastShow), 'toastShow 应当可调用');
});

$T->skip(
    'Toast::show 的真实调用',
    '有副作用：每跑一次就真的弹一条系统通知，且无人值守时无法断言"通知出现了"。'
        . '真实通知演示见 test/demo-toast.php'
);
