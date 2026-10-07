<?php

// 根据你的实际情况，修改下面的路径
require dirname(__DIR__) . "/vendor/autoload.php";

/**
 * 交互组 —— 区域点击穿透（"真实的点击穿透"）+ 悬浮挂件拖动
 *
 * 跑法：
 *     php -d extension=ffi -d ffi.enable=1 test/demo-click-through.php
 *
 * 现有 setClickThrough(true) 是"整窗开关"——整扇窗都不收鼠标。这个 demo 演示
 * "真实"的穿透：**透明处穿透到下层窗口、不透明内容正常可点**（桌面挂件/HUD 的标准行为）。
 *
 * 原理（三平台统一，页面侧不按平台分支）：
 *   逐像素 alpha 三平台都拿不到（WebView2 渲染在子窗口里，宿主读不到像素），
 *   等效做法是页面侧自动收集「有可见背景 / 可交互」元素的矩形，经 bind 通道上报，
 *   原生侧执行区域白名单穿透（rects 内可点、rects 外穿透）：
 *     - Windows：约 30ms 轮询光标位置，翻 WS_EX_TRANSPARENT（命中白名单=可点）
 *     - Linux  ：input shape 原生多矩形（事件路由层直接按形状命中，无轮询）
 *     - macOS  ：约 30ms 轮询翻 setIgnoresMouseEvents（未真机验证）
 *
 *   拖动：HUD 栏 mousedown -> beginDrag(screenX, screenY)（三平台统一，与标题栏 demo 同源）。
 *
 *   两种模式可切换（最后调用者获胜）：
 *     - alpha 模式（默认）：JS 采集器常驻，DOM 变化 / resize / scroll 后 120ms
 *       防抖全量重算上报，setClickThroughRegions() 更新白名单
 *     - 整窗模式：setClickThrough(true)。⚠️ 此时整扇窗连 HUD 按钮都点不到，
 *       demo 让它 6 秒后自动回到 alpha 模式（窗口仍持有焦点时也可按键盘 A 切回）
 *
 * 观察点（先在桌面开一个记事本垫在窗口下方）：
 *   1. alpha 模式：点不透明卡片 → 计数 +1（挂件响应）
 *   2. alpha 模式：在透明区按下拖动 → 移动的是记事本（穿透成功）
 *   3. 按住 HUD 栏拖动 → 挂件跟手移动（beginDrag）
 *   4. HUD 三个按钮：alpha / 整窗 / 关闭，都应可点
 *   5. 悬停卡片时挂件不应闪烁（原生侧状态无变化时不动样式位）
 *   6. 底部调试条实时显示：rects 数量、页面收到的 mousedown 坐标、bind 调用结果 ——
 *      如果按钮点了没反应，把调试条显示的坐标发出来即可定位是穿透判定还是桥的问题
 *
 * 退出方式：点 HUD 右上角的关闭按钮。
 */

use Kingbes\PebView\Window;
use Kingbes\PebView\WindowHint;

echo "=== PebView 交互演示：区域点击穿透（透明悬浮挂件）===\n";
echo "提示：先在桌面开一个记事本垫在窗口下方再试穿透。\n";
echo "退出：点 HUD 右上角的关闭按钮。\n\n";

/** 终端日志：bind 回调的动态都打出来，人工验收时配合界面调试条定位问题 */
$blog = static function (string $s): void {
    echo '[demo] ' . $s . "\n";
};

$win = new Window(true);

$win->setTitle('区域点击穿透演示')
    ->setSize(360, 280, WindowHint::None)
    ->setIcon(PHP_OS_FAMILY === 'Linux' ? __DIR__ . '/icon.png' : __DIR__ . '/php.ico')
    ->setPosition(80, 80);

$win->setCloseCallback(fn() => true);

// 悬浮挂件标配：真透明 + 置顶
$win->setTransparent(true);
$win->setAlwaysOnTop(true);

// ---------------------------------------------------------------------------
// 模式状态：$mode = 'alpha' | 'full'；$lastRects 是 JS 最近一次上报的白名单
// ---------------------------------------------------------------------------
$mode = 'alpha';
$lastRects = [];

// JS 采集器上报白名单（bind 通道）。只有 alpha 模式才实时生效；
// 整窗模式期间先记着，切回 alpha 时用。
$win->bind('__reportRegions', function (array $flat) use ($win, &$lastRects, &$mode, $blog) {
    $lastRects = array_chunk(array_map('intval', $flat), 4);
    $blog('__reportRegions: ' . count($lastRects) . ' 个矩形');
    if ($mode !== 'alpha') {
        return true; // 整窗模式期间只记录
    }
    try {
        $win->setClickThroughRegions($lastRects);
        $blog('  -> setClickThroughRegions OK');
        return true;
    } catch (\Throwable $e) {
        $blog('  -> setClickThroughRegions 失败: ' . $e->getMessage());
        return false;
    }
});

// 切换模式。注意"整窗穿透"会连 HUD 按钮一起穿透（鼠标事件全落到底下窗口），
// 页面 JS 会安排 6 秒后自动切回 alpha —— 见页面脚本。
$win->bind('ctSetMode', function (string $m) use ($win, &$mode, &$lastRects, $blog) {
    $blog('ctSetMode: ' . $m);
    try {
        if ($m === 'full') {
            $win->setClickThrough(true); // 退出区域模式，整窗穿透
        } else {
            $win->setClickThroughRegions($lastRects); // 恢复区域白名单（空数组=恢复正常）
        }
        $mode = $m;
        return true;
    } catch (\Throwable $e) {
        $blog('  -> 失败: ' . $e->getMessage());
        return false;
    }
});

// 卡片点击计数：alpha 模式下点卡片应能 +1（整窗模式下点不到，属预期）
$clicks = 0;
$win->bind('ctPing', function () use (&$clicks, $blog) {
    $clicks++;
    $blog('ctPing: 计数 -> ' . $clicks);
    return $clicks;
});

// HUD 栏拖动（三平台统一：beginDrag，屏幕坐标由 JS mousedown 传入）
$win->bind('tbBeginDrag', function (int $x, int $y) use ($win, $blog) {
    $blog("tbBeginDrag: ($x, $y)");
    try {
        $win->beginDrag($x, $y);
        return true;
    } catch (\Throwable $e) {
        $blog('  -> 失败: ' . $e->getMessage());
        return false;
    }
});

$win->bind('ctClose', function () use ($win, $blog) {
    $blog('ctClose');
    $win->terminate();
    return true;
});

$html = <<<'HTML'
<html>
<head><meta charset="utf-8"></head>
<!-- 悬浮挂件：html/body 必须 transparent，窗口层的 setTransparent(true) 才能透出桌面 -->
<body style="margin:0;font-family:system-ui,-apple-system,'Segoe UI',sans-serif;
     background:transparent;overflow:hidden;user-select:none;-webkit-user-select:none">

<!-- HUD 顶栏：不透明背景 → 自动进白名单；按住空白处拖动挂件，按钮正常点 -->
<div id="bar" style="height:40px;background:#1f2430;color:#e8e2da;display:flex;align-items:center;
     padding:0 10px;gap:8px;cursor:move">
  <span style="font-size:12px;font-weight:600">穿透演示</span>
  <div style="flex:1"></div>
  <button id="btnAlpha" onclick="setMode('alpha')"
    style="height:26px;padding:0 10px;border:1px solid #4a5266;border-radius:6px;
           background:#2a3145;color:#e8e2da;font-size:12px;cursor:pointer">alpha</button>
  <button id="btnFull" onclick="goFull()"
    style="height:26px;padding:0 10px;border:1px solid #4a5266;border-radius:6px;
           background:#2a3145;color:#e8e2da;font-size:12px;cursor:pointer">整窗</button>
  <button onclick="ctClose()"
    style="height:26px;padding:0 10px;border:1px solid #4a5266;border-radius:6px;
           background:#2a3145;color:#e8e2da;font-size:12px;cursor:pointer">×</button>
</div>

<!-- 不透明卡片：点击计数的目标 -->
<div id="card" onclick="ctPing().then(n => { document.getElementById('count').textContent = n; })"
  style="margin:16px;padding:18px;background:#ffffff;border:1px solid #d8dce2;border-radius:10px;
         box-shadow:0 4px 14px rgba(0,0,0,.18);cursor:pointer">
  <div style="font-size:13px;color:#666">点我计数（alpha 模式下应能 +1）</div>
  <div style="font-size:30px;font-weight:700;color:#1f2430;margin-top:6px">
    <span id="count">0</span>
  </div>
</div>

<div style="margin:0 16px;font-size:12px;line-height:1.9;color:#333">
  当前模式：<b id="modeText">alpha</b><span id="fullNote" style="color:#b00"></span><br>
  这行字没有背景 → 透明区，按住这里拖动会移动<b>下层窗口</b>（拿记事本试）。
</div>

<!-- 调试条：人工验收用，显示 rects 数量 / 页面收到的 mousedown / bind 结果 -->
<div id="dbg" style="position:fixed;left:0;right:0;bottom:0;padding:3px 8px;
     background:rgba(20,24,32,.85);color:#9fe8a2;font:11px/1.5 ui-monospace,monospace">
  dbg: booting…
</div>

<script>
  let mode = 'alpha';
  let debounceTimer = null;
  let fullTimer = null;
  const dbg = document.getElementById('dbg');
  let dbgClick = 'none';

  function setDbg(s) {
    const t = 'dbg: ' + s;
    // 文本没变就不写 DOM —— 写了会触发 MutationObserver → 采集循环 → 窗口闪烁
    if (dbg.textContent !== t) dbg.textContent = t;
  }

  // 页面收到的每一个 mousedown 都记下来 —— 按钮没反应时，先看坐标有没有出现在这里
  document.addEventListener('mousedown', e => {
    dbgClick = '(' + e.clientX + ',' + e.clientY + ') on ' + (e.target.id || e.target.tagName);
    setDbg('rects=' + (window.__lastRectCount ?? '?')
      + ' | mousedown ' + dbgClick
      + ' | bind=' + (window.__lastBind ?? '-'));
  });

  // ---------------------------------------------------------------------------
  // JS 采集器：收集「有可见背景 或 可交互」元素的矩形（CSS px），经 bind 上报。
  // 不依赖 mousemove —— 穿透状态下页面收不到鼠标事件，只能靠 DOM 事件驱动。
  // ---------------------------------------------------------------------------
  function excluded(el) {
    for (let n = el; n && n.nodeType === 1; n = n.parentElement) {
      const cs = getComputedStyle(n);
      if (cs.display === 'none' || cs.visibility === 'hidden' || parseFloat(cs.opacity) === 0) return true;
    }
    return false;
  }

  // 背景色 alpha >= 0.01 算"有背景"；渐变按整元素可点处理（保守：宁可多可点，不可丢点击）
  function hasVisibleBackground(cs) {
    const bg = cs.backgroundColor;
    if (bg && bg !== 'transparent' && bg !== 'rgba(0, 0, 0, 0)') {
      const m = bg.match(/rgba?\(([^)]+)\)/);
      if (!m) return true; // #hex / 命名色 → 不透明
      const parts = m[1].split(',').map(s => s.trim());
      const a = parts.length === 4 ? parseFloat(parts[3]) : 1;
      if (a >= 0.01) return true;
    }
    if (cs.backgroundImage && cs.backgroundImage !== 'none' && cs.backgroundImage.includes('gradient(')) return true;
    return false;
  }

  function isInteractive(el, cs) {
    const tag = el.tagName.toLowerCase();
    if (['a', 'button', 'input', 'select', 'textarea', 'label'].includes(tag)) return true;
    if (el.hasAttribute('onclick')) return true;
    if (el.getAttribute('role') === 'button') return true;
    if (cs.cursor === 'pointer') return true;
    return false;
  }

  function collectRects() {
    const out = [];
    const W = window.innerWidth, H = window.innerHeight;
    document.querySelectorAll('*').forEach(el => {
      if (excluded(el)) return;
      const cs = getComputedStyle(el);
      if (!hasVisibleBackground(cs) && !isInteractive(el, cs)) return;
      const r = el.getBoundingClientRect();
      if (r.width < 1 || r.height < 1) return;
      if (r.bottom < 0 || r.right < 0 || r.top > H || r.left > W) return; // 视口外无意义
      out.push([Math.round(r.left), Math.round(r.top), Math.round(r.width), Math.round(r.height)]);
    });
    return out;
  }

  let lastSentKey = '';

  function report() {
    debounceTimer = null;
    if (mode !== 'alpha') return; // 整窗模式不上报（PHP 侧也只记录）
    const rects = collectRects();
    window.__lastRectCount = rects.length;
    setDbg('rects=' + rects.length + ' | mousedown ' + dbgClick + ' | bind=' + (window.__lastBind ?? '-'));
    // 矩形没变就不上报 —— 上报会让原生侧重置穿透状态（旧版闪烁根源之一）
    const key = JSON.stringify(rects);
    if (key === lastSentKey) return;
    lastSentKey = key;
    __reportRegions(rects.flat()).then(() => {
      window.__lastBind = '__reportRegions(OK,' + rects.length + ')';
    }).catch(() => {
      window.__lastBind = '__reportRegions(FAIL)';
    });
  }

  function scheduleReport() {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(report, 120); // 防抖合并，全量重算（不做 diff，DOM 规模内够用）
  }

  // DOM 变化 / 尺寸 / 滚动 → 重算上报（穿透状态下没有 mousemove，这些是唯一的触发源）
  new MutationObserver(scheduleReport).observe(document.documentElement, {
    childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'style']
  });
  window.addEventListener('resize', scheduleReport);
  document.addEventListener('scroll', scheduleReport, { capture: true });

  // ---------------------------------------------------------------------------
  // 模式切换 + 拖动
  // ---------------------------------------------------------------------------
  function updateUI() {
    document.getElementById('modeText').textContent = mode === 'alpha' ? 'alpha（区域白名单）' : '整窗穿透';
    document.getElementById('btnAlpha').style.background = mode === 'alpha' ? '#3d4660' : '#2a3145';
    document.getElementById('btnFull').style.background = mode === 'full' ? '#3d4660' : '#2a3145';
  }

  async function setMode(m) {
    window.__lastBind = 'ctSetMode(' + m + ')';
    const ok = await ctSetMode(m);
    mode = m;
    if (m === 'alpha') {
      if (fullTimer) { clearInterval(fullTimer); fullTimer = null; }
      document.getElementById('fullNote').textContent = '';
      report(); // 立即恢复白名单
    }
    updateUI();
    return ok;
  }

  // 整窗模式：连 HUD 都点不到，6 秒后自动回 alpha（窗口有焦点时也可按 A）
  function goFull() {
    setMode('full');
    let left = 6;
    document.getElementById('fullNote').textContent = '（' + left + ' 秒后自动回到 alpha）';
    if (fullTimer) clearInterval(fullTimer);
    fullTimer = setInterval(() => {
      left--;
      if (left <= 0) { setMode('alpha'); return; }
      document.getElementById('fullNote').textContent = '（' + left + ' 秒后自动回到 alpha）';
    }, 1000);
  }

  // HUD 栏：按住空白处拖动挂件（beginDrag 三平台统一）；按钮区不拖
  document.getElementById('bar').addEventListener('mousedown', e => {
    if (e.button !== 0) return;
    if (e.target.closest('button')) return; // 按钮自己收点击，不触发拖动
    tbBeginDrag(e.screenX, e.screenY);
  });

  // 窗口仍持有焦点时的键盘逃生通道（WS_EX_TRANSPARENT 只挡鼠标，不挡键盘）
  document.addEventListener('keydown', e => {
    if (e.key === 'a' || e.key === 'A') setMode('alpha');
    if (e.key === 'f' || e.key === 'F') goFull();
  });

  // 初始：先按 alpha 收集上报一轮（PHP 侧首次 setClickThroughRegions 在此触发）
  report();
  updateUI();
</script>
</body>
</html>
HTML;

$win->setHtml($html);

echo "[提示] 窗口应已置顶悬浮在 (80, 80)。alpha 模式下：卡片可点计数、透明区穿透到下层、\n";
echo "       HUD 栏可拖动。终端会实时打印每次 bind 回调 —— 按钮没反应时对照终端与界面调试条。\n";
echo "       本机 WebView2 不渲染页面时看不到 HUD，但模式切换 / 白名单更新仍会生效 ——\n";
echo "       那种情况跑 test/auto-win-10-click-through-regions.php 看契约级验证。\n\n";

$win->run();
$win->destroy();

echo "=== 演示结束 ===\n";
