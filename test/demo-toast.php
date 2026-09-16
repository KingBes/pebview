<?php

// 根据你的实际情况，修改下面的路径
require dirname(__DIR__) . "/vendor/autoload.php";

/**
 * 交互组 —— 系统通知（Toast）
 *
 * 跑法：
 *     php -d extension=ffi -d ffi.enable=1 test/demo-toast.php
 *
 * 为什么单独成档：Toast::show() 会真的弹系统通知，是有副作用的操作，
 * 所以自动组里刻意只做签名断言、不真调用（见 test/auto-06-toast-contract.php）。
 * 真实效果只能人工看。
 *
 * 观察点：
 *   1. 通知是否出现在系统通知中心（Windows 右下角 / macOS 通知中心）
 *   2. 标题、正文、图标是否正确
 *   3. 带图标的和带默认图标的两条，外观差异
 *   4. 返回值是否都是 true
 *
 * 退出方式：没有事件循环，弹完就结束。通知本身由系统管理，关不关随你。
 *
 * 本机已知（Windows）：
 *   - WinToast 会在开始菜单建一个同名的 .lnk 快捷方式，用于注册 AppUserModelId
 *     （系统要求通知必须来自有 AppID 的应用）。这是它的固有机制，删掉通知就发不出来。
 *   - 同一 App 名重复通知会合并成一条，属正常行为。
 */

use Kingbes\PebView\Toast;

$icon = __DIR__ . '/icon.png';

function step(string $msg): void
{
    echo "\n[执行] {$msg}\n";
}

echo "=== PebView 交互演示：系统通知 ===\n";
echo "图标文件: {$icon} (" . (is_file($icon) ? '存在' : '缺失！') . ")\n";

step('Toast::show 三参数形式（不带图标，用应用默认图标）');
$r = Toast::show('PebView Demo', '不带动画的通知', '这是只有 app / title / message 三个参数的通知。');
echo "       -> 返回值 = " . var_export($r, true) . "（期望 true）\n";
sleep(2);

step('Toast::show 四参数形式（带自定义图标）');
$r = Toast::show('PebView Demo', '带图标的通知', '这条用了 test/icon.png 作为图标。', $icon);
echo "       -> 返回值 = " . var_export($r, true) . "（期望 true）\n";
sleep(2);

step('Toast::show 中文 + 较长正文');
$r = Toast::show(
    'PebView 演示',
    '中文标题与正文',
    '这是一条包含中文的通知，用来确认编码没有问题：窗口、对话框、托盘、通知。'
);
echo "       -> 返回值 = " . var_export($r, true) . "（期望 true）\n";
sleep(2);

step('Toast::show 图标路径不存在时（看是否崩，而不是猜返回值）');
$r = Toast::show('PebView Demo', '图标缺失', '这条的图标路径是错的，观察返回值。', __DIR__ . '/__nope__.png');
echo "       -> 返回值 = " . var_export($r, true)
    . "（实测：图标路径无效时**仍返回 true**，通知照发、用默认图标，不崩）\n";

echo "\n=== 演示结束 ===\n";
echo "如果一条通知都没看到，先确认系统没开「专注助手 / 勿扰模式」。\n";
