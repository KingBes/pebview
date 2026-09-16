<?php

// 严格模式
declare(strict_types=1);

/**
 * PebView 自动测试组入口
 *
 * 跑法：
 *     php -d extension=ffi -d ffi.enable=1 test/auto.php             # 默认：只跑纯逻辑组
 *     php -d extension=ffi -d ffi.enable=1 test/auto.php --windows   # 额外跑窗口组
 *
 * 退出码：0 = 全部通过；1 = 有失败或超时。
 *
 * 两组为什么分开：
 *   - 纯逻辑组（auto-NN-*.php）不创建任何窗口，几秒内跑完，任何环境都稳定。
 *   - 窗口组（auto-win-*.php）会创建真实窗口。本机实测 WebView2 环境下窗口操作
 *     又慢又不稳（同一进程里创建/销毁窗口累积到第 7 个左右会随机死锁），
 *     所以默认跳过，需要显式 --windows 开启。无头环境、CI 同样不该跑窗口组。
 *     窗口功能的真实验证更适合走交互组：test/demo-*.php。
 *
 * 每个用例文件都在**独立子进程**里执行，并带超时保护：
 * 单个文件卡住不会拖垮整轮，还能如实报出是哪个文件超时了（而不是整体静默挂死）。
 */

/** 单个用例文件的超时上限（秒）。超过就强制终止并计为失败。 */
const CASE_TIMEOUT_SEC = 30;

$withWindows = in_array('--windows', $argv, true);

$logicFiles  = glob(__DIR__ . '/auto-[0-9][0-9]-*.php') ?: [];
$windowFiles = glob(__DIR__ . '/auto-win-*.php') ?: [];

$files = $withWindows ? array_merge($logicFiles, $windowFiles) : $logicFiles;

echo "PebView 自动测试组 | PHP " . PHP_VERSION . " | " . PHP_OS_FAMILY . "\n";
echo $withWindows
    ? "模式：纯逻辑组 + 窗口组（--windows）\n"
    : "模式：仅纯逻辑组（跳过窗口组，加 --windows 可一并运行）\n";

if (!extension_loaded('ffi')) {
    echo "\n";
    echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n";
    echo "  当前进程没有启用 FFI。子进程会用 -d extension=ffi 重新拉起，\n";
    echo "  所以仍然能跑；但建议直接用下面这条命令，避免混淆：\n";
    echo "      php -d extension=ffi -d ffi.enable=1 test/auto.php\n";
    echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n";
}

if ($files === []) {
    echo "\n[致命] 没有找到任何用例文件 ——\n";
    echo '       这不是「全部通过」，是根本没跑。请检查文件是否还在。' . "\n";
    exit(1);
}

$pass = 0;
$fail = 0;
$skip = 0;
$failFiles = [];
$timeoutFiles = [];

foreach ($files as $file) {
    $name = basename($file);
    [$output, $timedOut] = runCaseFile($name);

    echo $output;

    if ($timedOut) {
        $timeoutFiles[] = $name;
        $fail++;
        continue;
    }

    if (preg_match('/##RESULT## pass=(\d+) fail=(\d+) skip=(\d+)/', $output, $m) === 1) {
        $pass += (int) $m[1];
        $fail += (int) $m[2];
        $skip += (int) $m[3];
        if ((int) $m[2] > 0) {
            $failFiles[] = $name;
        }
    } else {
        $fail++;
        $failFiles[] = $name . '（没有输出结果行，子进程可能异常退出）';
    }
}

echo "\n", str_repeat('=', 56), "\n";
printf(
    "用例文件 %d | 通过 %d | 失败 %d | 跳过 %d | 超时 %d\n",
    count($files),
    $pass,
    $fail,
    $skip,
    count($timeoutFiles)
);

if (!$withWindows && $windowFiles !== []) {
    printf(
        "\n另有 %d 个窗口组文件未运行（%s 等）—— 加 --windows 参数可一并运行。\n",
        count($windowFiles),
        basename($windowFiles[0])
    );
}

if ($failFiles !== []) {
    echo "\n断言失败的文件：\n";
    foreach ($failFiles as $i => $f) {
        printf("  %d. %s\n", $i + 1, $f);
    }
}

if ($timeoutFiles !== []) {
    echo "\n超时被终止的文件：\n";
    foreach ($timeoutFiles as $i => $f) {
        printf("  %d. %s —— 超过 %d 秒未结束\n", $i + 1, $f, CASE_TIMEOUT_SEC);
    }
    echo "  这类超时是窗口操作卡住了，不是断言失败 —— 本机 WebView2 环境下会偶发。\n";
    echo "  单独重跑确认：php -d extension=ffi -d ffi.enable=1 test/auto-run-one.php <文件名>\n";
}

if ($skip > 0) {
    echo "\n注意：有 {$skip} 条被跳过（各文件里已逐条打印原因），它们本轮并未被验证。\n";
}

$code = ($fail === 0) ? 0 : 1;
echo "\n退出码: {$code}";
echo $code === 0 ? "  （全部通过）\n" : "  （有失败或超时）\n";

exit($code);

// ------------------------------------------------------------------ 子进程执行

/**
 * 在独立子进程里跑一个用例文件，带超时保护。
 *
 * @return array{0: string, 1: bool} [子进程输出（已过滤噪音）, 是否超时]
 */
function runCaseFile(string $caseFile): array
{
    $cmd = [
        PHP_BINARY,
        '-d', 'extension=ffi',
        '-d', 'ffi.enable=1',
        __DIR__ . '/auto-run-one.php',
        $caseFile,
    ];

    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($cmd, $descriptors, $pipes, __DIR__);

    if (!is_resource($process)) {
        return ["[错误] 无法启动子进程：{$caseFile}\n", false];
    }

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout = '';
    $stderr = '';
    $start = microtime(true);
    $timedOut = false;

    while (true) {
        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);

        $status = proc_get_status($process);
        if ($status['running'] !== true) {
            break;
        }

        if (microtime(true) - $start > CASE_TIMEOUT_SEC) {
            $timedOut = true;
            proc_terminate($process, 9);
            break;
        }

        usleep(50000); // 50ms
    }

    // 收尾：把管道里剩余内容读干净，避免 proc_close 阻塞
    $stdout .= (string) stream_get_contents($pipes[1]);
    $stderr .= (string) stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    $text = filterNoise($stdout . $stderr);

    if ($timedOut) {
        $text .= "\n[TIMEOUT] 子进程超过 " . CASE_TIMEOUT_SEC . " 秒未结束，已强制终止。\n";
    }

    return [$text, $timedOut];
}

/**
 * 过滤 WebView2 / Chromium 往 stderr 打的内部噪音，只留测试自己的输出。
 */
function filterNoise(string $text): string
{
    $keep = [];
    foreach (explode("\n", str_replace("\r", '', $text)) as $line) {
        if (trim($line) === '') {
            continue;
        }
        $noisy = false;
        foreach (['Chrome_WidgetWin', 'ERROR:ui', 'ERROR:gpu', 'ERROR:viz', 'gfx\\win', 'gfx/win'] as $needle) {
            if (str_contains($line, $needle)) {
                $noisy = true;
                break;
            }
        }
        if (!$noisy) {
            $keep[] = $line;
        }
    }
    return implode("\n", $keep) . "\n";
}
