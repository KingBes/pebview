<?php

// 严格模式
declare(strict_types=1);

/**
 * auto-03 —— bind 回调返回值的编码（重点回归锁）
 *
 * 这是整套测试里最有价值的一档：它锁住的是刚刚修过的一组真实回归。
 *
 * 为什么用反射测私有方法：
 *   encodeResult() 是 private static，只在 bind() 的 C 桥回调被 JS 触发时调用。
 *   本机 webview_run() 不返回、WebView2 也不渲染页面，JS 根本触发不到这个回调，
 *   没有任何公开入口能覆盖它。而它是个纯函数（mixed → [status, json]），
 *   隔离单测就能把契约完整锁住。
 *
 * 三个已修复的坑：
 *   - 原来的 if ($value) 守卫会让 null/false/0/''/[] 根本不回传 → JS 侧 promise 永久挂起
 *   - 原来手工拼 '"' . $value . '"' → 含引号 / 反斜杠 / 换行的字符串产出非法 JSON → JS 侧 reject
 *   - json_encode(INF/NAN) 返回 false，PHP 会把 false 转成空串，而空串在 webview 的
 *     resolve() 里等价于 undefined → 从"reject"退化成"静默 resolve(undefined)"，
 *     所以必须显式判 false 并改成 status=1
 */

use Kingbes\PebView\Window;

/** @var PebTest\Harness $T */

$T->section('auto-03-encode-result.php  (bind 回调返回值编码)');

$encode = $T->reflect(Window::class, 'encodeResult');

/** 断言 payload 是合法 JSON 并返回解码结果 */
$decode = static function (string $json): mixed {
    $value = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new PebTest\AssertionFailed(
            'payload 不是合法 JSON: ' . var_export($json, true) . ' —— ' . json_last_error_msg()
        );
    }
    return $value;
};

$T->check('falsy 值也要回传（修复前 JS 侧 promise 会永久挂起）', function () use ($T, $encode, $decode) {
    foreach ([
        [null, 'null'],
        [false, 'false'],
        [0, '0'],
        ['', '""'],
        [[], '[]'],
    ] as [$input, $expectedJson]) {
        $note = 'input=' . var_export($input, true);
        [$status, $payload] = $encode->invoke(null, $input);
        $T->assertSame(0, $status, $note);
        $T->assertSame($expectedJson, $payload, $note);
        $T->assertSame($input, $decode($payload), '往返应还原原值 | ' . $note);
    }
});

$T->check('正常标量 / 数组 / 对象编码正确', function () use ($T, $encode, $decode) {
    $cases = [
        [true, true],
        [42, 42],
        [1.5, 1.5],
        ['hello', 'hello'],
        ['中文标题', '中文标题'],
        [[1, 2, 3], [1, 2, 3]],
        [['a' => 1], ['a' => 1]],
    ];
    foreach ($cases as [$input, $expected]) {
        $note = 'input=' . var_export($input, true);
        [$status, $payload] = $encode->invoke(null, $input);
        $T->assertSame(0, $status, $note);
        $T->assertSame($expected, $decode($payload), $note);
    }
});

$T->check('含引号 / 反斜杠 / 换行的字符串仍是合法 JSON', function () use ($T, $encode, $decode) {
    // 修复前这几条都会产出非法 JSON，JS 侧收到
    // "Failed to parse binding result as JSON" 的 reject。
    // Windows 路径 C:\... 属于必现场景，也是当初发现问题的触发点。
    $inputs = [
        'he said "hi"',
        'C:\\tmp\\a.txt',
        "line1\nline2",
        "tab\there",
        'back\\slash',
    ];
    foreach ($inputs as $input) {
        $note = 'input=' . var_export($input, true);
        [$status, $payload] = $encode->invoke(null, $input);
        $T->assertSame(0, $status, $note);
        $T->assertSame($input, $decode($payload), '往返应还原 | ' . $note);
    }
});

$T->check('INF / NAN 走 status=1，不能静默变成 undefined', function () use ($T, $encode, $decode) {
    foreach ([INF, -INF, NAN] as $input) {
        $label = is_nan($input) ? 'NAN' : (string) $input;

        [$status, $payload] = $encode->invoke(null, $input);

        // 关键：状态必须是 1。若变成 0 且 payload 是空串，webview 会把它当成 undefined，
        // JS 侧静默 resolve(undefined) —— 那比报错更难查。
        $T->assertSame(1, $status, "input={$label}");

        $decoded = $decode($payload);
        $T->assertTrue(
            is_array($decoded) && array_key_exists('error', $decoded),
            "status=1 的 payload 必须带 error 键 | input={$label} | payload=" . var_export($payload, true)
        );
    }
});

$T->check('payload 永远不是空串（空串在 webview 里等价于 undefined）', function () use ($T, $encode) {
    foreach ([null, false, 0, '', [], INF, NAN, 'x'] as $input) {
        [, $payload] = $encode->invoke(null, $input);
        $T->assertTrue($payload !== '', 'input=' . var_export($input, true) . ' 的 payload 不应为空串');
    }
});
