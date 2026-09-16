<?php

// 根据你的实际情况，修改下面的路径
require dirname(__DIR__) . "/vendor/autoload.php";

/**
 * 交互组 —— JS ↔ PHP 双向调用（bind）
 *
 * 跑法：
 *     php -d extension=ffi -d ffi.enable=1 test/demo-bind.php
 *
 * 这是 encodeResult 那组修复的「肉眼验证」场合：
 * 自动组用反射锁住了编码边界（test/auto-03-encode-result.php），
 * 这里则让 JS 真的去 await 各种返回值，看 promise 会不会挂起、会不会 reject。
 *
 * 页面上每个按钮点一下，控制台就会打印 PHP 侧的返回结果。
 *
 * 观察点：
 *   1. 「null / false / 0 / 空串 / 空数组」这五个按钮 ——
 *      修复前它们会让 promise 永久挂起（页面一直显示 pending），现在应当立刻 resolve
 *   2. 「含引号 / 含反斜杠 / 含换行」三个按钮 ——
 *      修复前 JS 侧会 reject（"Failed to parse binding result as JSON"），现在应当正常
 *   3. 「INF / NAN」两个按钮 —— 应当走 reject（这是刻意的，因为无法编码成合法 JSON）
 *   4. 中文与数组/对象往返后类型是否正确
 *
 * 退出方式：直接关窗口（关闭回调返回 true）。也可以 Ctrl+C。
 *
 * 本机已知：WebView2 不渲染页面时按钮点不到。这时候本 demo 验证不了，
 *          请在有正常 WebView2 的桌面环境跑。
 */

use Kingbes\PebView\Window;
use Kingbes\PebView\WindowHint;

echo "=== PebView 交互演示：JS ↔ PHP 双向调用 ===\n";
echo "退出：直接关窗口。\n\n";

$win = new Window(false);
$win->setTitle('bind 双向调用')
    ->setSize(760, 620, WindowHint::None)
    ->setIcon(__DIR__ . '/php.ico');

$win->setCloseCallback(fn() => true);

// 每个绑定都返回一种不同形态的值，用来覆盖 encodeResult 的各条分支
$win->bind('ret_null',       fn() => null);
$win->bind('ret_false',      fn() => false);
$win->bind('ret_zero',       fn() => 0);
$win->bind('ret_empty_str',  fn() => '');
$win->bind('ret_empty_arr',  fn() => []);
$win->bind('ret_true',       fn() => true);
$win->bind('ret_int',        fn() => 42);
$win->bind('ret_float',      fn() => 1.5);
$win->bind('ret_string',     fn() => 'hello');
$win->bind('ret_chinese',    fn() => '中文标题 · 标点，符号！');
$win->bind('ret_quotes',     fn() => 'he said "hi" and \'bye\'');
$win->bind('ret_backslash',  fn() => 'C:\\Users\\test\\file.txt');
$win->bind('ret_newline',    fn() => "第一行\n第二行");
$win->bind('ret_array',      fn() => [1, 2, 3]);
$win->bind('ret_object',     fn() => ['a' => 1, 'b' => 'two']);
$win->bind('ret_inf',        fn() => INF);
$win->bind('ret_nan',        fn() => NAN);

// 带参数的回调：JS 传什么就原样回显
$win->bind('echo_args', function (...$args) {
    return ['received' => $args, 'count' => count($args)];
});

$win->setHtml(<<<'HTML'
<body style="font-family: system-ui, sans-serif; margin:0; padding:24px; background:#fafafa">
<h1 style="font-size:19px; margin:0 0 6px">JS ↔ PHP 双向调用</h1>
<p style="color:#666;font-size:13px;margin:0 0 18px">
  点按钮，看下面每行的结果。绿色 = resolve，红色 = reject，黄色 = 一直 pending（那就是 bug）。
</p>
<div id="rows"></div>

<h2 style="font-size:15px;margin:24px 0 8px">带参数调用</h2>
<button onclick="call('echo_args', [1, 'two', true, null])">传 4 个参数</button>

<script>
const CASES = [
  ['null',        'ret_null'],
  ['false',       'ret_false'],
  ['0',           'ret_zero'],
  ['空串',         'ret_empty_str'],
  ['空数组',       'ret_empty_arr'],
  ['true',        'ret_true'],
  ['整数 42',      'ret_int'],
  ['浮点 1.5',     'ret_float'],
  ['字符串',       'ret_string'],
  ['中文',         'ret_chinese'],
  ['含双引号单引号', 'ret_quotes'],
  ['含反斜杠(路径)', 'ret_backslash'],
  ['含换行',       'ret_newline'],
  ['数组',         'ret_array'],
  ['对象',         'ret_object'],
  ['INF',         'ret_inf'],
  ['NAN',         'ret_nan'],
];

const rows = document.getElementById('rows');
const cells = {};

for (const [label, fn] of CASES) {
  const div = document.createElement('div');
  div.style.cssText = 'display:flex;gap:10px;align-items:baseline;padding:5px 0;border-bottom:1px solid #eee;font-size:13px';
  div.innerHTML = `<span style="width:130px;color:#555">${label}</span>
                   <span style="flex:1;font-family:ui-monospace,monospace">-</span>`;
  rows.appendChild(div);
  const cell = div.lastElementChild;
  cells[fn] = cell;

  const btn = document.createElement('button');
  btn.textContent = '调用';
  btn.style.cssText = 'font-size:12px;padding:2px 8px';
  btn.onclick = () => call(fn, []);
  div.insertBefore(btn, cell);
}

async function call(name, args) {
  const cell = cells[name];
  if (cell) { cell.textContent = '...'; cell.style.color = '#a80'; }
  try {
    const v = await window[name](...args);
    const text = JSON.stringify(v);
    if (cell) { cell.textContent = 'resolve: ' + (text === undefined ? String(v) : text);
                cell.style.color = '#1a7f37'; }
    console.log('resolve', name, v, typeof v);
  } catch (e) {
    if (cell) { cell.textContent = 'reject: ' + (e && e.message ? e.message : JSON.stringify(e));
                cell.style.color = '#c00'; }
    console.log('reject', name, e);
  }
}

// 一次性全部调用，方便快速看结论
function callAll() {
  for (const [, fn] of CASES) call(fn, []);
}

// 给 echo_args 也加个行
const d = document.createElement('div');
d.style.cssText = 'padding:8px 0;font-size:13px;font-family:ui-monospace,monospace';
d.id = 'echoOut';
document.body.appendChild(d);
</script>

<div style="margin-top:20px">
  <button onclick="callAll()" style="padding:6px 14px">全部调用一遍</button>
  <button onclick="document.querySelectorAll('#rows span:last-child').forEach(s=>s.textContent='-')"
          style="padding:6px 14px">清空结果</button>
</div>
</body>
HTML);

echo "[提示] 窗口已显示。如果内容渲染出来了，点「全部调用一遍」。\n";
echo "       重点看：修复前会挂起的那几行（null/false/0/空串/空数组）、\n";
echo "       以及含引号/反斜杠/换行的字符串是否正常 resolve。\n\n";

$win->run();
$win->destroy();

echo "=== 演示结束 ===\n";
