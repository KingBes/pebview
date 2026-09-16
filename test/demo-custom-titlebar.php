<?php

// 根据你的实际情况，修改下面的路径
require dirname(__DIR__) . "/vendor/autoload.php";

/**
 * 交互组 —— 自定义标题栏（真正无边框 + JS 驱动缩放）
 *
 * 跑法：
 *     php -d extension=ffi -d ffi.enable=1 test/demo-custom-titlebar.php
 *
 * 这个 demo 演示的是"自己画标题栏"，不是改颜色（改颜色见 demo-titlebar.php）。
 * 页面顶部那条深色栏就是自绘标题栏，右侧三个按钮也是最普通的 HTML 按钮。
 *
 * 关键设计（三平台统一，页面侧不按平台分支）：
 *   这扇窗是 WebView2 宿主，子窗口链铺满客户区、把鼠标全吃掉，顶层窗口在边缘收不到
 *   WM_NCHITTEST，留系统边框也抓不到边缘。所以拖动和缩放都由 JS 在 mousedown 里显式发起，
 *   不走系统命中测试：
 *     - 标题栏 mousedown -> beginDrag(screenX, screenY)  -> C 侧按平台发起拖动
 *     - 四条边/角 mousedown -> beginResize(edge)         -> C 侧按平台发起缩放
 *   C 侧各自用最合适的方式：Windows 合成 WM_NCLBUTTONDOWN(HTCAPTION / HT* 边缘)，
 *   macOS 搬窗口/改 frame，Linux 走 gdk_window_begin_*_drag。贴边 / 双击最大化等原生行为都在。
 *
 *   标题栏顶部留一条 M px 的"缩放条"：这条让出来调 beginResize（顶部整条 + 左上/右上角都能缩），
 *   其余标题栏区域仍是拖动。否则顶部整条都被拖动占住，窗口顶部就永远无法调整大小。
 *
 *   边缘判定全部用"视口坐标 vs 窗口尺寸"（clientX/Y 对比 window.innerWidth/Height），
 *   且监听挂在 document 上 —— 所以标题栏/页面被 padding / margin 内缩也不会破坏左右下与四角的缩放。
 *
 * 观察点：
 *   1. 系统标题栏消失，窗口真正无边框：顶部换成页面里画的那条、内容贴到窗口顶边，零默认边距
 *   2. 按住标题栏（除顶部 6px 缩放条外）拖：窗口跟着动（三平台统一走 beginDrag）
 *   3. 双击标题栏：最大化 / 还原
 *   4. 拖窗口的四条边与角（含顶部 6px 缩放条）：应当能调整大小（JS 判定边缘后调 beginResize 发起，三平台统一）
 *   5. 点右侧三个按钮：最小化 / 最大化 / 关闭（按钮区用 stopPropagation 排除，不会误触发拖动）
 *   6. 最大化时页面里那行状态文字会变，标题栏的图标也跟着变
 *   7. 最大化后往屏幕顶部拖：应当触发 Aero Snap（Windows）
 *
 * 退出方式：右上角关闭按钮；窗口关闭回调返回 true，会真的关掉。
 */

use Kingbes\PebView\Window;
use Kingbes\PebView\WindowHint;

const TITLEBAR_HEIGHT = 36;
const CONTROLS_WIDTH = 138; // 三个按钮 46px 一个
const RESIZE_MARGIN = 6;    // 距离边缘多少 px 算"在缩放区"

echo "=== PebView 交互演示：自定义标题栏（真正无边框 + JS 驱动缩放）===\n";
echo "标题栏高度 " . TITLEBAR_HEIGHT . "px，右侧按钮区 " . CONTROLS_WIDTH . "px\n";
echo "退出：点右上角的关闭按钮。\n\n";

$win = new Window(true);

$win->setTitle('自定义标题栏演示')
    ->setSize(880, 560, WindowHint::None)
    ->setIcon(PHP_OS_FAMILY === 'Linux' ? __DIR__ . '/icon.png' : __DIR__ . '/php.ico');

$win->setCloseCallback(fn() => true);

// 打开自定义标题栏
$win->setCustomTitlebar(TITLEBAR_HEIGHT, CONTROLS_WIDTH);

// 三个按钮直接走现有的 bind 通道，不需要新 API
$win->bind('tbMinimize', function () use ($win) {
    return $win->minimize() !== null;
});
$win->bind('tbToggleMaximize', function () use ($win) {
    return $win->toggleMaximize();
});
$win->bind('tbClose', function () use ($win) {
    $win->terminate();
    return true;
});
$win->bind('tbIsMaximized', function () use ($win) {
    // 这条顺带验证 encodeResult 修复：返回 false 也能正确 resolve
    return $win->isMaximized();
});

// 三平台统一：拖动由 JS 在 mousedown 里发起（见页面脚本），这里转调 beginDrag()
$win->bind('tbBeginDrag', function (int $x, int $y) use ($win) {
    try {
        $win->beginDrag($x, $y);
        return true;
    } catch (\RuntimeException $e) {
        return false;
    }
});

// 三平台统一：缩放由 JS 在 mousedown 里判定边缘后发起，这里转调 beginResize(edge)
$win->bind('tbBeginResize', function (int $edge) use ($win) {
    try {
        $win->beginResize($edge);
        return true;
    } catch (\RuntimeException $e) {
        return false;
    }
});

// 状态变化：最大化 / 还原时同步给页面
$win->onStateChange(function ($w, bool $maximized) {
    // 回调发生在事件循环里，这里用 eval 把状态推给页面
    $w->eval('window.__onMaximized && window.__onMaximized(' . ($maximized ? 'true' : 'false') . ');');
});

$html = <<<'HTML'
<body style="margin:0;font-family:system-ui,-apple-system,'Segoe UI',sans-serif;background:#f6f7f9">

<!-- 自绘标题栏：高度必须和 setCustomTitlebar() 传的一致（本例 36px） -->
<!-- 拖动走 beginDrag()（标题栏 mousedown，除顶部 6px 缩放条），缩放走 beginResize()（四条边/角 mousedown）；
     按钮区用 stopPropagation 挡住 mousedown，所以不会被误当成拖/缩。 -->
<div id="bar" style="padding:8px;background:#1f2430;color:#e8e2da;display:flex;
     align-items:center;padding-left:14px;user-select:none;-webkit-user-select:none;cursor:move">

  <span style="font-size:13px;font-weight:500">PebView · 自定义标题栏</span>

  <div style="flex:1"></div>

  <!-- 按钮区：宽度要和 setCustomTitlebar 的 controlsWidth 对得上 -->
  <div id="controls" style="display:flex;height:100%;">
    <button class="tb-btn" onclick="tbMinimize()" title="最小化"
      style="width:46px;height:100%;border:0;background:transparent;color:#cfc9c1;
             font-size:15px;cursor:pointer">&#8211;</button>
    <button class="tb-btn" id="maxBtn" onclick="onMax()" title="最大化"
      style="width:46px;height:100%;border:0;background:transparent;color:#cfc9c1;
             font-size:13px;cursor:pointer">&#9744;</button>
    <button class="tb-btn" onclick="tbClose()" title="关闭"
      style="width:46px;height:100%;border:0;background:transparent;color:#cfc9c1;
             font-size:15px;cursor:pointer">&#10005;</button>
  </div>
</div>

<div style="padding:28px 32px;line-height:1.8">
  <h1 style="font-size:20px;margin:0 0 10px">这条标题栏是 HTML 画的</h1>
  <p style="margin:0 0 18px;color:#555">
    系统标题栏已经让出来了（<code>setCustomTitlebar(36, 138)</code>），窗口真正无边框、<br>
    内容贴到顶边、<b>零默认边距</b>。拖动和缩放都不依赖系统边框：<br>
    标题栏 mousedown 触发 <code>beginDrag</code>（顶部 6px 缩放条除外，改触发 <code>beginResize</code>），<br>
    四条边/角 mousedown 触发 <code>beginResize</code>（页面自己判定边缘），三平台同一句 JS。<br>
    右侧按钮区用 <code>stopPropagation</code> 排除，点按钮不会误触发拖动。
  </p>

  <h2 style="font-size:15px;margin:22px 0 8px">可以试这些</h2>
  <ul style="margin:0;padding-left:20px;color:#333">
    <li>按住标题栏（除顶部 6px 缩放条外）拖动窗口</li>
    <li>双击标题栏 → 最大化 / 还原</li>
    <li>把鼠标移到窗口四条边/角（约 6px 内），光标变缩放箭头，按住拖动 → 调整大小（含顶部 6px 缩放条）</li>
    <li>最大化后往屏幕顶部拖 → 触发贴边（Windows）</li>
    <li>点右上角三个按钮</li>
  </ul>

  <div id="status" style="margin-top:22px;padding:12px 14px;background:#fff;
       border:1px solid #e3e6ea;border-radius:8px;font:13px/1.7 ui-monospace,monospace">
    窗口状态：<b id="maxState">普通</b>
  </div>
</div>

<script>
  const M = 6; // 缩放区宽度（px），与 PHP 侧 RESIZE_MARGIN 对应

  const bar = document.getElementById('bar');

  // 按钮能点：controls 上的 mousedown 默认 stopPropagation 挡掉拖动；
  // 但标题栏顶部缩放条（按 bar 实际位置算）要让事件冒泡回 bar 去调 beginResize，
  // 否则顶部右边（含右上角）会被按钮区吞掉、无法缩放。标题栏被 padding/margin 内缩也正确。
  document.getElementById('controls').addEventListener('mousedown', e => {
    const r = bar.getBoundingClientRect();
    if (e.clientY >= r.top && e.clientY < r.top + M) return; // 顶部缩放条：放行
    e.stopPropagation();
  });

  function onMax() {
    tbToggleMaximize().then(v => { /* resolve 的值就是切换后的状态 */ });
  }

  // 由坐标算出落在哪条边/角（WMSZ_* 编码，与 C 侧 window_begin_resize_drag 一致）
  // 1=左 2=右 3=上 4=左上 5=右上 6=下 7=左下 8=右下；0=不在边缘
  // 用视口坐标（clientX/Y）对比 window.innerWidth/Height —— 即相对"窗口边缘"判定，
  // 不依赖页面有没有 padding/margin，页面内缩也能正确认出窗口边。
  function edgeOf(x, y) {
    const w = window.innerWidth, h = window.innerHeight;
    const left = x < M, right = x > w - M, top = y < M, bottom = y > h - M;
    if (top && left) return 4;
    if (top && right) return 5;
    if (bottom && left) return 7;
    if (bottom && right) return 8;
    if (left) return 1;
    if (right) return 2;
    if (top) return 3;
    if (bottom) return 6;
    return 0;
  }

  function setCursor(x, y) {
    const w = window.innerWidth, h = window.innerHeight;
    const left = x < M, right = x > w - M, top = y < M, bottom = y > h - M;
    if (top && left || bottom && right) document.body.style.cursor = 'nwse-resize';
    else if (top && right || bottom && left) document.body.style.cursor = 'nesw-resize';
    else if (left || right) document.body.style.cursor = 'ew-resize';
    else if (top || bottom) document.body.style.cursor = 'ns-resize';
    else document.body.style.cursor = 'default';
  }

  // 鼠标移到边缘：显示对应缩放光标。挂在 document 上，连 body 的 margin/padding 死区也能覆盖到。
  document.addEventListener('mousemove', e => {
    const inBar = e.target.closest('#bar');
    if (inBar) {
      const r = bar.getBoundingClientRect();
      if (e.clientY >= r.top && e.clientY < r.top + M) {
        // 标题栏顶部缩放条：右上角应是 nesw-resize（之前误写成 nwse），左上才是 nwse
        if (e.clientX > window.innerWidth - M) bar.style.cursor = 'nesw-resize';
        else if (e.clientX < M) bar.style.cursor = 'nwse-resize';
        else bar.style.cursor = 'ns-resize';
        return;
      }
      bar.style.cursor = 'move';
      return;
    }
    setCursor(e.clientX, e.clientY);
  });

  // 四条边/角 mousedown -> 调 beginResize(edge)。挂在 document 上，页面内缩也能抓到窗口边。
  // 标题栏/按钮区已在下面排除（交给 bar / controls 自己的处理）。
  document.addEventListener('mousedown', e => {
    if (e.button !== 0) return;
    if (e.target.closest('#bar') || e.target.closest('#controls')) return;
    const edge = edgeOf(e.clientX, e.clientY);
    if (edge !== 0) tbBeginResize(edge);
  });

  // 标题栏：顶部 M px（按 bar 实际位置算）让出来做顶部缩放（顶部整条 + 左上/右上角），
  // 其余区域 mousedown 交给 beginDrag() 拖动；双击标题栏切换最大化。
  // 按钮区已在上面 stopPropagation（顶部缩放条除外），不会误触发拖动。
  bar.addEventListener('mousedown', e => {
    if (e.button !== 0) return;
    const r = bar.getBoundingClientRect();
    if (e.clientY >= r.top && e.clientY < r.top + M) {
      let edge = 3; // 上
      if (e.clientX < M) edge = 4;                        // 左上
      else if (e.clientX > window.innerWidth - M) edge = 5; // 右上
      tbBeginResize(edge);
      return;
    }
    tbBeginDrag(e.screenX, e.screenY);
  });
  bar.addEventListener('dblclick', () => tbToggleMaximize());

  // PHP 侧状态变化时回调进来
  window.__onMaximized = function (maximized) {
    document.getElementById('maxState').textContent = maximized ? '已最大化' : '普通';
    document.getElementById('maxBtn').innerHTML = maximized ? '&#10064;' : '&#9744;';
    document.title = '自定义标题栏演示 · ' + (maximized ? '最大化' : '普通');
  };

  // 初始同步一次
  tbIsMaximized().then(v => window.__onMaximized(v));
</script>
</body>
HTML;

$win->setHtml($html);

echo "[提示] 窗口已显示。系统标题栏应当消失，顶部换成页面里画的那条、内容贴到窗口顶边（零默认边距）。\n";
echo "       本机 WebView2 不渲染页面时看不到 HTML，但窗口几何变化仍会生效 ——\n";
echo "       那种情况下可以跑 test/auto-win-09-custom-titlebar.php 看状态级的验证。\n\n";

$win->run();
$win->destroy();

echo "=== 演示结束 ===\n";
