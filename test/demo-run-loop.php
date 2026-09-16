<?php

// 根据你的实际情况，修改下面的路径
require dirname(__DIR__) . "/vendor/autoload.php";

/**
 * 交互组 —— 事件循环、关闭回调与 dispatch
 *
 * 跑法：
 *     php -d extension=ffi -d ffi.enable=1 test/demo-run-loop.php
 *
 * 这是唯一一个会真正进入 run() 事件循环的示例（自动组完全不敢碰 run()，
 * 因为它会阻塞到窗口关闭为止，无人值守必挂）。
 *
 * 覆盖：
 *   - run()      进入事件循环
 *   - dispatch() 往事件循环里投递回调（观察它是不是在循环里被执行的）
 *   - setCloseCallback() 拦截关闭：本 demo 第一次点 X 会「拒绝关闭」并计数，
 *                        第二次才放行 —— 这样两种返回值都能看到
 *
 * 观察点：
 *   1. 启动后控制台是否打印 dispatch 回调执行的信息
 *   2. 第一次点窗口 X：窗口不关，控制台打印「拒绝关闭」且 HTML 里的计数 +1
 *   3. 第二次点 X：窗口关闭，run() 返回，脚本走到 destroy()
 *
 * 退出方式（三重保底）：
 *   1. 连点两次 X（第一次被拦截是刻意演示）
 *   2. 托盘菜单「退出」
 *   3. 30 秒后 JS 定时器兜底自动退出
 *      （本机 WebView2 不渲染页面时定时器可能不触发，那就走第 1、2 条）
 *
 * 本机已知：WebView2 环境不渲染页面时，HTML 内容看不到、按钮点不到，
 *          但窗口本身能显示、关闭回调也能触发，run() 会正常阻塞等待。
 */

use Kingbes\PebView\Window;
use Kingbes\PebView\WindowHint;

$icon = __DIR__ . '/php.ico';

echo "=== PebView 交互演示：事件循环 ===\n";
echo "退出：连点两次 X，或走托盘菜单「退出」。\n\n";

$closeAttempts = 0;
$win = new Window(false);

$win->setTitle('事件循环演示')
    ->setSize(720, 460, WindowHint::None)
    ->setIcon($icon);

// 关闭回调：第一次拒绝、第二次放行 —— 两种返回值都能观察到
$win->setCloseCallback(function (Window $w) use (&$closeAttempts): bool {
    $closeAttempts++;
    if ($closeAttempts === 1) {
        echo "[事件] 第 1 次点关闭：返回 false，拒绝关闭。\n";
        echo "       （再点一次就会放行；也可以走托盘「退出」）\n";
        $w->eval('window.__bumpClose && window.__bumpClose();');
        return false;
    }
    echo "[事件] 第 {$closeAttempts} 次点关闭：返回 true，允许关闭。\n";
    return true;
});

$win->tray($icon);
$win->trayMenu([
    [
        'text' => '退出',
        'cb' => function (Window $w) {
            echo "[菜单] 退出 —— terminate() 会让 run() 返回。\n";
            $w->terminate();
        },
    ],
]);

// 30 秒兜底自动退出
$win->init('setTimeout(function(){ if (typeof pebview_quit === "function") pebview_quit(); }, 30000);');
$win->bind('pebview_quit', function () use ($win) {
    echo "[定时器] 30 秒到，自动退出。\n";
    $win->terminate();
    return true;
});

$win->setHtml(<<<'HTML'
<body style="font-family: system-ui, sans-serif; margin:0; padding:28px; background:#fff">
<h1 style="font-size:20px">事件循环演示</h1>
<p>点窗口右上角的 X 试试：第一次会被拒绝（关闭回调返回 false），第二次才关。</p>
<p>关闭被拒绝次数：<b id="n">0</b></p>
<script>
  var n = 0;
  window.__bumpClose = function () {
    document.getElementById('n').textContent = ++n;
  };
</script>
</body>
HTML);

// dispatch：把回调投递到事件循环里执行，确认它确实在 loop 中被调用
$win->dispatch(function (Window $w, mixed $arg) {
    echo "[dispatch] 回调在事件循环中被执行了（arg = " . var_export($arg, true) . "）。\n";
});

echo "[提示] 进入 run()，此后由事件循环接管。\n";

$win->run();

echo "\n[run() 已返回] 事件循环结束，准备销毁窗口。\n";

$win->destroy();

echo "=== 演示结束 ===\n";
