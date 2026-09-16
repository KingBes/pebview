<?php

// 严格模式
declare(strict_types=1);

/**
 * auto-02 —— C ABI 契约与动态库符号
 *
 * 这一档是整套测试里性价比最高的，靠的是 FFI 的一个性质：
 * FFI::cdef() 解析动态库时，只要有一个声明的函数在库里找不到，就会立刻抛
 * "Failed resolving C function"。所以「Base::ffi() 成功返回」本身就等价于
 * 「include/PebView.h 里那 30 个函数在动态库里全部解析成功」。
 *
 * 再拿 source/exports.def 里的名字逐个探测，链条就补全成：
 *     exports.def  ⊆  PebView.h  ⊆  动态库实际导出
 * 任何一环漏符号都会在这里 FAIL 并报出具体名字。
 *
 * 这正是之前踩过的坑：exports.def 少写一个名字时链接并不报错（那个函数没人引用，
 * 被链接器当死代码丢掉），DLL 从 646KB 掉到 294KB，功能已坏但一切"看起来正常"。
 */

use Kingbes\PebView\Base;

/** @var PebTest\Harness $T */

$T->section('auto-02-ffi-contract.php  (C ABI 契约与动态库符号)');

$ffi = null;
$loadError = $T->ffiLoadError();

if ($loadError !== null) {
    $T->skip('Base::ffi() 能加载动态库', $loadError);
} else {
    $T->check('Base::ffi() 能加载动态库（头文件里全部函数都解析成功）', function () use ($T, &$ffi) {
        $ffi = Base::ffi();
        $T->assertInstanceOf(\FFI::class, $ffi);
    });
}

$defPath = dirname(__DIR__) . '/source/exports.def';

$T->check('导出清单里的每个名字都能在 FFI 契约里找到', function () use ($T, &$ffi, $defPath) {
    $T->skipIf($ffi === null, 'FFI 不可用，跳过（原因见上一条）');

    if (!is_file($defPath)) {
        throw new PebTest\AssertionFailed('找不到导出清单: ' . $defPath);
    }

    $names = [];
    foreach (file($defPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === ';') {
            continue; // 注释行
        }
        if (stripos($line, 'LIBRARY') === 0 || stripos($line, 'EXPORTS') === 0) {
            continue; // 段头
        }
        $names[] = $line;
    }

    // 防"解析逻辑坏掉 → 列表为空 → 静默通过"这种空跑假通过
    $T->assertTrue(
        count($names) >= 30,
        '从 exports.def 解析出的名字应 >= 30，实际 ' . count($names) . ' 个 —— 解析逻辑可能坏了'
    );

    $missing = [];
    foreach ($names as $name) {
        try {
            $ffi->$name; // 访问契约里不存在的名字会抛 FFI\Exception
        } catch (\Throwable $e) {
            $missing[] = $name;
        }
    }

    if ($missing !== []) {
        throw new PebTest\AssertionFailed(
            '这些名字在 exports.def 里有，但 include/PebView.h 里找不到：' . implode(', ', $missing)
        );
    }
});

$T->check('Base::ffi() 是单例（两次调用返回同一实例）', function () use ($T) {
    $T->skipIf($T->ffiLoadError() !== null, 'FFI 不可用');
    $T->assertSame(Base::ffi(), Base::ffi());
});
