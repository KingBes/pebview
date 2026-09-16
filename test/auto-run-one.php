<?php

// 严格模式
declare(strict_types=1);

/**
 * 单个用例文件的执行体 —— 由 test/auto.php 作为独立子进程拉起。
 *
 * 为什么要分进程：
 *   本机实测在**同一个 PHP 进程**里连续创建/销毁窗口，累积到第 7 个左右时
 *   destroy() 会随机死锁（WebView2 是异步初始化的，环境回调还没回来窗口就被销毁了）。
 *   分进程之后每个文件只碰少量窗口，而且单个文件万一卡住，父进程可以超时终止它，
 *   不会拖垮整轮测试 —— 同时还能如实报告"是哪个文件超时了"，而不是整体静默挂死。
 *
 * 用法（一般不直接调用）：
 *   php -d extension=ffi -d ffi.enable=1 test/auto-run-one.php auto-03-lifecycle.php
 *
 * 输出末行是机器可读汇总，供父进程解析：
 *   ##RESULT## pass=N fail=N skip=N
 */

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/auto-harness.php';

$file = $argv[1] ?? '';

if ($file === '' || !is_file(__DIR__ . '/' . $file)) {
    fwrite(STDOUT, "找不到用例文件: {$file}\n");
    exit(2);
}

$T = new PebTest\Harness();

require __DIR__ . '/' . $file;

$code = $T->report();

$c = $T->counts();
fwrite(STDOUT, sprintf("##RESULT## pass=%d fail=%d skip=%d\n", $c['pass'], $c['fail'], $c['skip']));

exit($code);
