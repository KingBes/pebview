<?php

// 根据你的实际情况，修改下面的路径
require dirname(__DIR__) . "/vendor/autoload.php";

/**
 * 交互组 —— 窗口透明背景（setTransparent）真透明验收
 *
 * 跑法：
 *     php -d extension=ffi -d ffi.enable=1 test/demo-transparent.php
 *     （沙箱环境先设 PEBVIEW_USER_DATA_FOLDER 指向项目内目录）
 *
 * 验收方法：底层参照法（2026-09-29 定稿，A_on == base == desktop 逐位一致即通过）
 *   1. 窗口挪到屏幕左下角固定位置（避开用户工作区，底层稳定）
 *   2. 先 hide 抓"底层参照帧" base → show → 抓透明态帧 on → 抓不透明对照 off
 *   3. 中间穿插两个子元素 alpha 实验：
 *        h1 绿底   —— 内容像素 alpha=255 必须可见
 *        body 红底 —— 必须采到 #FF0000（根背景 alpha 正常）
 *   4. 每次采样/截图前 HWND_TOPMOST 瞬时置顶 + 刷新实时 rect + 打印状态行
 *      （layered / NOREDIRECT / Z 序），采样与截图共用 BitBlt 单一数据源
 *      —— GetPixel 在 NOREDIRECTIONBITMAP 窗口上读数不可信（恒 #D9D9D9）
 *
 * 观察点：
 *   1. 控制台 [结论] 行：A_on 与底层参照是否逐位一致
 *   2. test/.webview2-profile/ 截图：on.png 应"页面内容可见 + 背景透出底层"，
 *      base/desktop.png 应是纯底层，off.png 应是不透明窗口
 *
 * 退出方式：采样完成后自动 terminate；Ctrl+C 也可随时退出。
 */

use Kingbes\PebView\Base;
use Kingbes\PebView\Window;
use Kingbes\PebView\WindowHint;

$isWin = PHP_OS_FAMILY === 'Windows';

// 官方环境变量：早期设置默认背景色（API 方式有"生效前白闪/延迟"的已知问题）。
// 8 位 hex，alpha 在前：00000000 = 全透明。必须在创建窗口（WebView2 初始化）之前设。
putenv('WEBVIEW2_DEFAULT_BACKGROUND_COLOR=00000000');

echo "=== PebView 交互演示：窗口透明背景 ===\n";
echo "当前平台: " . PHP_OS_FAMILY . "\n";

$win = new Window(false);
$win->setTitle('透明背景演示')
    ->setSize(640, 420, WindowHint::None);

// 1. 窗口层透明（Linux 必须在 run() 之前调用，Windows/macOS 随时可调）
try {
    $win->setTransparent(true);
    echo "[1] setTransparent(true) 已开启\n";
} catch (\RuntimeException $e) {
    echo "[1] [本平台不支持] {$e->getMessage()}\n";
}

// JS 存活探针：页面脚本若真的执行了，会调用这个 bind —— 控制台没打印
// 就说明页面脚本没跑起来。注意 bind 必须在 setHtml 之前注册
$win->bind('pebview_js_probe', function () {
    echo "[JS] 页面脚本执行了 —— 渲染层是活的。\n";
    return true;
});

// 2. 页面侧配合：html/body 都设 CSS 背景透明 + 中间留一个无内容的探针区
//    （color-scheme=light：防深色模式下 Chromium 把画布底画成黑）
$win->setHtml(<<<'HTML'
<!doctype html>
<html style="background: transparent">
<head><meta name="color-scheme" content="light"></head>
<body style="margin:0; font-family: system-ui, sans-serif; background: transparent">
  <div style="padding:22px 26px">
    <h1 style="font-size:19px; margin:0 0 6px; color:#1a1a1a">窗口透明演示</h1>
    <p style="margin:0; font-size:13px; color:#555; line-height:1.6">
      页面 html/body 是 CSS 背景透明 rgba(0,0,0,0)。<br>
      下面虚线框是采样探针区，框内没有任何内容（采样点在框正中）。
    </p>
  </div>
  <div style="margin:34px 56px; height:190px; border:2px dashed #c0392b;
              border-radius:10px; display:flex; align-items:center; justify-content:center;
              color:#c0392b; font:13px ui-monospace, monospace">
    probe area
  </div>
  <script>setTimeout(function(){ pebview_js_probe(); }, 800);</script>
</body>
</html>
HTML);

// 3. 像素采样（仅 Windows）：user32/gdi32 各自一个 FFI 实例
$u = null;
$gd = null;
if ($isWin) {
    $u = FFI::cdef(
        'struct RECT{int left;int top;int right;int bottom;};'
        . 'int GetWindowRect(void* h, struct RECT* r);'
        . 'void* GetDC(void* hwnd);'
        . 'int ReleaseDC(void* hwnd, void* hdc);'
        // after 用 intptr_t 承载 HWND_TOPMOST(-1)，避免 FFI::cast 指针的坑
        . 'int SetWindowPos(void* h, intptr_t after, int x, int y, int cx, int cy, unsigned int flags);'
        . 'long long GetWindowLongPtrW(void* h, int index);'
        . 'void* GetWindow(void* h, int cmd);',
        'user32.dll'
    );
    $gd = FFI::cdef('int GetPixel(void* hdc, int x, int y);', 'gdi32.dll');
}

// 瞬时把窗口顶到 HWND_TOPMOST(-1)：用户正在操作电脑，一次性 HWND_TOP 会被
// 用户活动立刻压下去（实测 Z序最顶=否，采到的全是别人的像素）。topmost 带
// 普通窗口压不住，且每次操作前瞬时置顶竞态窗口最小。
// after 传 intptr_t(-1)，避免 FFI::cast 指针的坑。
$hwndRef = (object) ['h' => null];
$toTop = function () use ($u, $hwndRef): void {
    if ($hwndRef->h !== null) {
        $r = $u->SetWindowPos($hwndRef->h, -1, 0, 0, 0, 0, 0x0001 | 0x0002 | 0x0010); // NOSIZE|NOMOVE|NOACTIVATE
        if ($r === 0) {
            echo "  [warn] SetWindowPos TOPMOST 失败\n";
        }
    }
};

// 把窗口放到屏幕左下角固定位置：避开用户工作区（微信/浏览器都在中右），
// hide/show 对比时同点底层才稳定。topmost + NOSIZE + 指定坐标（不带 NOMOVE）
$relocate = function () use ($u, $hwndRef): void {
    if ($hwndRef->h !== null) {
        $u->SetWindowPos($hwndRef->h, -1, 30, 560, 0, 0, 0x0001 | 0x0010); // NOSIZE|NOACTIVATE
    }
};

// 打印窗口的系统级状态：layered 标志 + 是否真的在 Z 序最顶
// （Z 顶判定用 rect 对比而不是指针 == ，CData 的 == 比较不可靠）
$zstate = function (string $label, ?\FFI\CData $h) use ($u): void {
    if ($h === null || FFI::isNull($h)) {
        echo "  [状态 {$label}] 无句柄\n";
        return;
    }
    $ex = $u->GetWindowLongPtrW($h, -20); // GWL_EXSTYLE = -20
    $layered = ($ex & 0x00080000) !== 0 ? '是' : '否';      // WS_EX_LAYERED
    $nrdb = ($ex & 0x00200000) !== 0 ? '是' : '否';          // WS_EX_NOREDIRECTIONBITMAP
    $r1 = $u->new('struct RECT');
    $r2 = $u->new('struct RECT');
    $u->GetWindowRect($h, FFI::addr($r1));
    $first = $u->GetWindow($h, 0); // GW_HWNDFIRST = 0
    $top = '否';
    if ($first !== null && !FFI::isNull($first)) {
        $u->GetWindowRect($first, FFI::addr($r2));
        if ($r1->left === $r2->left && $r1->top === $r2->top
            && $r1->right === $r2->right && $r1->bottom === $r2->bottom) {
            $top = '是';
        }
    }
    echo "  [状态 {$label}] WS_EX_LAYERED={$layered}  NOREDIRECT={$nrdb}  Z序最顶={$top}\n";
};

// 单一数据源：BitBlt 抓取窗口矩形像素（32bpp BGRA，自底向上行序）。
// 采样与截图必须共用这里 —— GetPixel 与 BitBlt 在 NOREDIRECTIONBITMAP 窗口上
// 读到的画面不一致（实测 GetPixel 恒返回画面中不存在的 #D9D9D9）。
$grabData = function (int $left, int $top, int $right, int $bottom): ?array {
    static $u = null;
    static $g = null;
    if ($u === null) {
        $u = FFI::cdef(
            'void* GetDC(void* hwnd); int ReleaseDC(void* hwnd, void* hdc);',
            'user32.dll'
        );
        $g = FFI::cdef(
            'struct BITMAPINFOHEADER{unsigned int size;long width;long height;'
            . 'unsigned short planes;unsigned short bitcount;unsigned int compression;'
            . 'unsigned int sizeimage;long xpels;long ypels;unsigned int clrused;unsigned int clrimportant;};'
            . 'struct RGBQUAD{unsigned char b;unsigned char g;unsigned char r;unsigned char x;};'
            . 'struct BITMAPINFO{struct BITMAPINFOHEADER header;struct RGBQUAD colors[1];};'
            . 'void* CreateCompatibleDC(void* hdc);'
            . 'int DeleteDC(void* hdc);'
            . 'void* CreateDIBSection(void* hdc, struct BITMAPINFO* bmi, unsigned int usage, void** bits, void* sec, unsigned int off);'
            . 'void* SelectObject(void* hdc, void* obj);'
            . 'int BitBlt(void* dest, int dx, int dy, int w, int h, void* src, int sx, int sy, unsigned int rop);'
            . 'int DeleteObject(void* obj);',
            'gdi32.dll'
        );
    }

    $w = $right - $left;
    $h = $bottom - $top;
    if ($w <= 0 || $h <= 0) {
        return null;
    }

    $screen = $u->GetDC(null);
    if ($screen === null || FFI::isNull($screen)) {
        return null;
    }
    $mem = $g->CreateCompatibleDC($screen);

    $bmi = $g->new('struct BITMAPINFO');
    $bmi->header->size = 40;
    $bmi->header->width = $w;
    $bmi->header->height = $h; // 正值 = 自底向上，与 BMP 文件格式一致
    $bmi->header->planes = 1;
    $bmi->header->bitcount = 32;
    $bmi->header->compression = 0; // BI_RGB

    $bits = $g->new('void*[1]');
    $hbitmap = $g->CreateDIBSection($screen, FFI::addr($bmi), 0, FFI::addr($bits[0]), null, 0);
    if ($hbitmap === null || FFI::isNull($hbitmap)) {
        $g->DeleteDC($mem);
        $u->ReleaseDC(null, $screen);
        return null;
    }

    $old = $g->SelectObject($mem, $hbitmap);
    $g->BitBlt($mem, 0, 0, $w, $h, $screen, $left, $top, 0x00CC0020); // SRCCOPY

    $size = $w * $h * 4;
    $buf = $g->new("unsigned char[{$size}]");
    FFI::memcpy($buf, $g->cast('unsigned char*', $bits[0]), $size);
    $pixels = FFI::string($buf, $size);

    $g->SelectObject($mem, $old);
    $g->DeleteObject($hbitmap);
    $g->DeleteDC($mem);
    $u->ReleaseDC(null, $screen);

    return [$w, $h, $pixels];
};

$sample = function (int $x, int $y) use ($u, $toTop, $zstate, $hwndRef, $grabData): string {
    $toTop();
    $zstate('采样', $hwndRef->h);
    $r = $u->new('struct RECT');
    $u->GetWindowRect($hwndRef->h, FFI::addr($r));
    $got = $grabData($r->left, $r->top, $r->right, $r->bottom);
    if ($got === null) {
        return '(抓取失败)';
    }
    [$w, $h, $data] = $got;
    $rx = $x - $r->left;
    $ry = $y - $r->top;
    if ($rx < 0 || $ry < 0 || $rx >= $w || $ry >= $h) {
        return '(采样点不在窗口内)';
    }
    // DIB 自底向上：屏幕行 ry 对应缓冲行 (h-1-ry)；像素 BGRA → 输出 RGB
    $off = ((($h - 1 - $ry) * $w) + $rx) * 4;
    return sprintf('#%02X%02X%02X', ord($data[$off + 2]), ord($data[$off + 1]), ord($data[$off]));
};

$windowHwnd = function (Window $w) use ($u) {
    // Window::$pv 是私有的，demo 用反射取出（仅测试用途）
    $prop = new ReflectionProperty(Window::class, 'pv');
    $prop->setAccessible(true);
    $hwnd = Base::ffi()->webview_get_window($prop->getValue($w));
    if ($hwnd === null || FFI::isNull($hwnd)) {
        return null;
    }
    return $hwnd;
};

// 泵 Windows 消息循环 $ms 毫秒：sleep 不泵消息，JS→PHP 的 bind 回调和
// WebView2 的异步事件都堵在消息队列里，必须边等边泵
$pump = function (int $ms): void {
    static $p = null;
    if ($p === null) {
        $p = FFI::cdef(
            'struct MSG{void* hwnd;unsigned int message;unsigned long long wParam;'
            . 'long long lParam;unsigned long time;struct{int x;int y;} pt;};'
            . 'int PeekMessageW(struct MSG* m, void* wnd, unsigned int min, unsigned int max, unsigned int remove);'
            . 'int TranslateMessage(struct MSG* m);'
            . 'long long DispatchMessageW(struct MSG* m);',
            'user32.dll'
        );
    }
    $end = microtime(true) + $ms / 1000;
    while (microtime(true) < $end) {
        $m = $p->new('struct MSG');
        if ($p->PeekMessageW(FFI::addr($m), null, 0, 0, 1)) {
            $p->TranslateMessage(FFI::addr($m));
            $p->DispatchMessageW(FFI::addr($m));
        } else {
            usleep(5000);
        }
    }
};

// 截屏存 BMP：复用 $grabData（与采样同一数据源）。
$shot = function (string $name, int $left, int $top, int $right, int $bottom) use ($toTop, $grabData): string {
    $toTop();
    $got = $grabData($left, $top, $right, $bottom);
    if ($got === null) {
        return '(抓取失败)';
    }
    [$w, $h, $pixels] = $got;

    echo "    [shot:{$name}] 截取完成 {$w}x{$h}\n";

    // BITMAPFILEHEADER 是 14 字节：'BM'(2) + 文件大小(4) + 保留(4) + 偏移(4)
    $fileHeader = pack('v', 0x4D42) . pack('V', 54 + strlen($pixels)) . pack('vv', 0, 0) . pack('V', 54);
    $infoHeader = pack('V3v2V6', 40, $w, $h, 1, 32, 0, strlen($pixels), 2835, 2835, 0, 0);
    $path = __DIR__ . DIRECTORY_SEPARATOR . '.webview2-profile' . DIRECTORY_SEPARATOR . $name . '.bmp';
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }
    file_put_contents($path, $fileHeader . $infoHeader . $pixels);
    echo "    [shot:{$name}] 写盘完成\n";
    return $path;
};

// 4. 进入事件循环后由 dispatch 执行采样序列（非 Windows 平台只展示不采样）
$win->dispatch(function (Window $w, mixed $arg) use ($isWin, $u, $sample, $shot, $zstate, $pump, $relocate, $windowHwnd, $hwndRef): void {
    // 等 WebView2 起来并完成首次渲染（边等边泵，让 JS 探针回调能到达）
    $pump(2500);

    if (!$isWin) {
        echo "[提示] 非 Windows 平台不自动采样，窗口展示 15 秒后自动退出。\n";
        sleep(15);
        $w->terminate();
        return;
    }

    $hwnd = $windowHwnd($w);
    if ($hwnd === null) {
        echo "[错误] 取不到窗口句柄，退出。\n";
        $w->terminate();
        return;
    }
    $hwndRef->h = $hwnd;

    $rect = $u->new('struct RECT');
    $u->GetWindowRect($hwnd, FFI::addr($rect));
    // 提到 Z 序最顶（HWND_TOPMOST）：上层窗口（微信/浏览器等）会盖住采样点，
    // 导致所有像素对比测的是别人的像素（实测踩过）。
    // ⚠️ flags 必须含 SWP_NOMOVE(0x0002)、绝不能含 SWP_NOZORDER(0x0004) ——
    // 0x0004 是 NOZORDER 会把置顶废掉，漏 NOMOVE 会把窗口甩到 (0,0)（都踩过）。
    $u->SetWindowPos($hwnd, -1, 30, 560, 0, 0, 0x0001 | 0x0010); // 放到左下角固定位置

    // 每步前刷新实时 rect：窗口可能被用户拖走，用初始 rect 会采到窗口外的
    // 底层（实测踩过：四张截图全一样、采样全是同一个浅灰）
    $refresh = function () use ($u, $hwnd, $rect): array {
        $u->GetWindowRect($hwnd, FFI::addr($rect));
        return [intdiv($rect->left + $rect->right, 2), intdiv($rect->top + $rect->bottom, 2)];
    };

    // 第一步：隐藏窗口抓"底层参照帧" —— 同位置、秒级间隔，底层基本不变。
    // 透明的判定基准：A_on 应当等于这张参照（窗口在与不在，该点颜色一样）。
    $w->hide();
    $pump(500);
    [$cx, $cy] = $refresh();
    $b0 = $sample($cx, $cy);
    echo "\n[底层参照] 窗口隐藏后同点: {$b0}\n";
    echo '  截图: ' . $shot('base', $rect->left, $rect->top, $rect->right, $rect->bottom) . "\n";
    $w->show();
    $relocate();
    $pump(600);

    [$cx, $cy] = $refresh();
    echo "\n[采样] 窗口中心 ({$cx}, {$cy})（每步前刷新实时 rect）\n";

    $aOn = $sample($cx, $cy);
    echo "  透明开 + 窗口显示 : {$aOn}\n";
    echo '  截图: ' . $shot('on', $rect->left, $rect->top, $rect->right, $rect->bottom) . "\n";

    // 子元素 alpha 判定：给 h1 上不透明绿底 —— 页面内容（非根背景）像素
    // alpha=255 时必须可见；不可见 = 合成器把整帧 alpha 归零（内容也一起透掉）
    $w->eval("document.querySelector('h1').style.background = '#00FF00';");
    $pump(700);
    [$cx, $cy] = $refresh();
    echo '  截图: ' . $shot('on-h1-green', $rect->left, $rect->top, $rect->right, $rect->bottom) . "\n";
    $w->eval("document.querySelector('h1').style.background = '';");
    $pump(400);

    // 逐像素 alpha 判定：给页面不透明红背景 —— 若逐像素 alpha 正常，
    // 红色像素(alpha=255)必须可见；若整帧 alpha 被清零，红也看不见
    $w->eval("document.body.style.background = 'rgb(255,0,0)';");
    $pump(700);
    [$cx, $cy] = $refresh();
    $aRed = $sample($cx, $cy);
    echo "  透明开 + 红背景  : {$aRed}（期望 #FF0000 = 逐像素 alpha 正常）\n";
    echo '  截图: ' . $shot('on-red', $rect->left, $rect->top, $rect->right, $rect->bottom) . "\n";
    $w->eval("document.body.style.background = 'transparent';");
    $pump(400);

    try {
        $w->setTransparent(false);
        $pump(1500); // 渲染层恢复（白）是异步的，等新帧真的画出来
        [$cx, $cy] = $refresh();
        $aOff = $sample($cx, $cy);
        echo "  透明关 + 窗口显示 : {$aOff}（不透明对照）\n";
        echo '  截图: ' . $shot('off', $rect->left, $rect->top, $rect->right, $rect->bottom) . "\n";
    } catch (\RuntimeException $e) {
        echo "  关闭透明失败: {$e->getMessage()}\n";
        $aOff = null;
    }

    $w->hide();
    $pump(800);
    [$cx, $cy] = $refresh();
    $b = $sample($cx, $cy);
    echo "  窗口隐藏（桌面）  : {$b}\n";
    echo '  截图: ' . $shot('desktop', $rect->left, $rect->top, $rect->right, $rect->bottom) . "\n";
    $w->show();
    $u->SetWindowPos($hwnd, -1, 0, 0, 0, 0, 0x0001 | 0x0002 | 0x0010); // hide 后 Z 序要重新提顶

    echo "\n[结论] ";
    if ($b0 !== null && strcasecmp($aOn, $b0) === 0) {
        echo "A_on 与底层参照逐位一致 ({$aOn}) —— 窗口透明 + CSS 透明已透出桌面。\n";
    } elseif ($aOff !== null && strcasecmp($aOn, $aOff) === 0) {
        echo "A_on == A_off ({$aOn})，没透出去。\n";
        echo "        渲染层在该点输出的是不透明像素，窗口层透明已生效但被内容盖住。\n";
    } else {
        echo "A_on ({$aOn}) 与底层参照 ({$b0}) / A_off 均不同 —— 请对照 base/on 截图肉眼判断。\n";
    }

    $w->terminate();
});

echo "\n[提示] 进入 run()，采样完成后自动退出（约 4 秒）。\n";

$win->run();

echo "\n[run() 已返回] 销毁窗口。\n";
$win->destroy();
echo "=== 演示结束 ===\n";
