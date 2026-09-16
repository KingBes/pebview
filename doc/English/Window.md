## Window Class

> use Kingbes\PebView\Window;

## Methods

### Create a Window Object

Constructor
 - `new Window` Creates a window object.
    Parameters
  - `bool` `$debug` Whether to enable debug mode. Default: true
    Returns
  - `Window` Returns a window object.

Usage:
```PHP
$win = new Window(true);
```

### Destroy Window

Public Method
 - `destroy` Destroys the window.
    Parameters
  - None
    Returns
  - `void` No return value.

Usage:
```PHP
// Must be called after the run() method.
$win->destroy();
```

### Run Window

Public Method
 - `run` Runs the window.
    Parameters
  - None
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
// Must be called after the setHtml() or navigate() method.
$win->run();
```

### Terminate Window

Public Method
 - `terminate` Terminates the window.
    Parameters
  - None
    Returns
  - `void` No return value.

Usage:
```PHP
// Used to close the application.
$win->terminate();
```

### Dispatch Function

Schedule the functions to be called on the thread that has a running/event loop.
This feature can be used, for example, to interact with libraries or native objects.

Public Method
 - `dispatch` Dispatches the function to be called on the thread that has a running/event loop.
    Parameters
  - `callable` `$callback` The function to be called.
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
// Used to interact with libraries or native objects.
$win->dispatch(function($win, $arg) {
    // Interact with libraries or native objects.
    // $win is the window object.
    // $arg is the argument passed to the dispatch() method.
});
```

### Set Window Icon

Windows requires the ICO format; Linux requires the PNG format; MacOs requires the ICO format.
Usually, only the Windows system will work, while Linux and MacOs do not have this effect.
 
Public Method
 - `setIcon` Sets the window icon.
    Parameters
  - `string` `$path` The path to the icon file.
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
// Must be called before the run() method.
$win->setIcon("path/to/icon.ico");
```

### Set Titlebar Appearance

Sets the light / dark appearance and the colors of the native titlebar. **When never called, the system default is kept and the behavior is exactly as before.**

Support differs per platform. An unsupported combination throws instead of silently doing nothing:

| Platform | Light / dark | Custom colors |
| --- | --- | --- |
| Windows | Supported | Supported (requires Win11; Win10 only honors light / dark) |
| macOS | Supported | Not supported. The titlebar is drawn by the system and AppKit exposes no API for it |
| Linux | Not supported. GTK cannot change only the titlebar (theme changes are global) | Supported. Switches to a client-side decorated titlebar (GtkHeaderBar + CSS) |

Public Method
 - `setTitlebarTheme` Sets the titlebar appearance.
    Parameters
  - `?bool` `$dark` true for dark, false for light, null to follow the system (default null).
  - `?string` `$caption` Titlebar background color, `#RRGGBB` or `RRGGBB`; null uses the system default (default null).
  - `?string` `$text` Titlebar text color, same format; null picks it automatically from light / dark (default null).
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
// Follow the system
$win->setTitlebarTheme();

// Dark titlebar only
$win->setTitlebarTheme(dark: true);

// Custom colors (Windows 11 / Linux CSD)
$win->setTitlebarTheme(dark: true, caption: "#1F2430", text: "#FFD166");
```

An invalid color format throws `InvalidArgumentException`; a missing window or a combination unsupported on the current platform throws `RuntimeException`.

### Custom Titlebar (Truly Borderless + JS-driven Resize)

**This is a different thing from the section above.** The previous one only changes the titlebar's
*colors / light-dark*, while the titlebar is still drawn by the system. This one hands the titlebar
over to you so you can draw it in HTML.

Once enabled, the top `$height` pixels are drawn by your HTML instead of the system, and the system
stops painting a titlebar. The window is **truly borderless** — `WM_NCCALCSIZE` returns 0, so the
client area fills the whole window, the content sits flush against the top edge, and there is **no
default top margin** (no auto-sizing frame is kept). Edge resizing is instead started from JS: in a
`mousedown` handler the page detects which edge it is on and calls `beginResize(edge)` (see below),
because WebView2's child windows swallow the mouse and the system hit-test can never catch the edges.

Toggling the custom titlebar **does not change the client size**: `setSize()` always means the
content size, and you get the same client area before and after (the window frame is compensated
for the caption delta internally).

Per-platform differences:

| Platform | Approach | Dragging | Notes |
| --- | --- | --- | --- |
| Windows | `WM_NCCALCSIZE` returns 0, client area fills the whole window (truly borderless, zero top margin) | Unified via `beginDrag()` | Edge resizing unified via `beginResize()` (JS detects the edge); double-click-to-maximize and Aero Snap come along |
| macOS | Transparent titlebar + content extends under it (`FullSizeContentView`) | Native | Traffic lights and dragging are native; `$controlsWidth` is meaningless (the system decides where the traffic lights go) |
| Linux | `gtk_window_set_decorated(FALSE)` | **Needs JS help** | GTK never receives the mouse events swallowed by webview, so you must call `beginDrag()` from `mousedown` |

> **One JS call on every platform**: dragging is unified through `beginDrag()` and resizing through
> `beginResize(edge)`, so the page side never branches per platform. WebView2 hosts the page inside a
> chain of child windows that cover the whole client area (`webview_widget → Chrome_WidgetWin_* →
> Chrome_RenderWidgetHostHWND`). Those children own the mouse messages, so the host window's `WM_NCHITTEST`
> is never consulted — you cannot rely on hit testing to claim the titlebar or edge regions yourself.
> Instead, from a `mousedown` handler the page passes `event.screenX / screenY` to `beginDrag()` for the
> titlebar, and the detected `edge` to `beginResize(edge)` for the window edges; each platform's native
> side starts the action the best way it can (on Windows dragging synthesizes `WM_NCLBUTTONDOWN(HTCAPTION)`
> and resizing sends `WM_SYSCOMMAND(SC_SIZE | edge)`, both entering the system's modal loop with native
> Aero Snap and double-click-to-maximize). Exclude the buttons with `stopPropagation` on their `mousedown`,
> and treat `$controlsWidth` as a reserved parameter only.

Public Method
 - `setCustomTitlebar` Enables the custom titlebar.
    Parameters
  - `?int` `$height` Titlebar height in px. `null` or `<= 0` disables it and restores the system titlebar.
  - `int` `$controlsWidth` Width of the right-side button area in px; that region does not respond to dragging. 0 means the whole bar is draggable. Reserved on every platform — the buttons are excluded via `stopPropagation` in the webview, so this value is kept for compatibility only.
  - `bool` `$drag` Whether the titlebar area is draggable.
    Returns
  - `Window` Returns the window object.

 - `minimize` Minimizes the window.
    Returns
  - `Window` Returns the window object.

 - `toggleMaximize` Maximizes / restores (toggle).
    Returns
  - `bool` Whether the window is maximized after the toggle.

 - `isMaximized` Whether the window is currently maximized.
    Returns
  - `bool`

 - `onStateChange` Listens for maximize / restore (fires once per actual state change).
    Parameters
  - `callable` `$callable` Of the form `function (Window $win, bool $maximized): void`.
    Returns
  - `Window` Returns the window object.

 - `beginDrag` Starts a window drag (screen coordinates). Unified across all platforms — Windows synthesizes `WM_NCLBUTTONDOWN(HTCAPTION)`, Linux uses `gdk_window_begin_move_drag`, macOS moves the window by cursor delta. Returns `self`; never throws while the window exists. Left button already released returns `4` (treated as a no-op).
    Parameters
  - `int` `$x` Screen X.
  - `int` `$y` Screen Y.

 - `beginResize` Starts a window resize (edge code). Same idea as `beginDrag`: because WebView2's child windows swallow the mouse and the system hit-test can never catch the edges, resizing is started from JS after the page detects which edge it is on. Unified across all platforms — Windows sends `WM_SYSCOMMAND(SC_SIZE | edge)`, Linux uses `gdk_window_begin_resize_drag`, macOS changes the window frame by cursor delta. Returns `self`; never throws while the window exists. Left button already released returns `4` (treated as a no-op).
    Parameters
  - `int` `$edge` Edge/corner code (WMSZ_*): 1=left 2=right 3=top 4=top-left 5=top-right 6=bottom 7=bottom-left 8=bottom-right.

Usage:
```PHP
$win->setCustomTitlebar(36, 138);   // 36px tall, 138px reserved for three buttons

// Buttons just go through the existing bind channel - no new API needed
$win->bind('tbMinimize', fn() => $win->minimize());
$win->bind('tbMaximize', fn() => $win->toggleMaximize());
$win->bind('tbClose',    fn() => $win->terminate());
$win->bind('tbIsMax',    fn() => $win->isMaximized());

// A maximized window gets rounded corners and extra insets, so the bar must follow
$win->onStateChange(function ($win, $maximized) {
    $win->eval('window.__onMaximized(' . ($maximized ? 'true' : 'false') . ')');
});

// Dragging is started from JS (same on every platform), this just forwards to beginDrag
$win->bind('tbBeginDrag', function (int $x, int $y) use ($win) {
    $win->beginDrag($x, $y);   // pass event.screenX / screenY from a mousedown handler
    return true;
});

// Resizing is also started from JS: the page detects the edge inside a mousedown on the window
// edges and forwards the edge code, this just forwards to beginResize
$win->bind('tbBeginResize', function (int $edge) use ($win) {
    $win->beginResize($edge);  // edge code 1..8 (WMSZ_*: left/right/top/top-left/top-right/bottom/bottom-left/bottom-right)
    return true;
});
```

The bar itself (draggable on every platform; the buttons opt out via `stopPropagation`):

```HTML
<!-- height must equal $height passed to setCustomTitlebar() -->
<div id="bar" style="height:36px; cursor:move">
    <span>Your title</span>

    <!-- buttons opt out of dragging/resizing via stopPropagation on mousedown (see demo) -->
    <div id="controls">
        <button onclick="tbMinimize()">-</button>
        <button onclick="tbMaximize()">[]</button>
        <button onclick="tbClose()">x</button>
    </div>
</div>

<script>
  const M = 6; // resize zone width in px
  // map a point to which edge/corner it is on (WMSZ_* codes, matching the C side); 0 = not on an edge
  function edgeOf(x, y) {
    const w = innerWidth, h = innerHeight;
    const L = x < M, R = x > w - M, T = y < M, B = y > h - M;
    if (T && L) return 4; if (T && R) return 5; if (B && L) return 7; if (B && R) return 8;
    if (L) return 1; if (R) return 2; if (T) return 3; if (B) return 6; return 0;
  }
  // titlebar mousedown drags; window edge/corner mousedown resizes (buttons already stopPropagation)
  document.body.addEventListener('mousedown', e => {
    if (e.button !== 0) return;
    if (e.target.closest('#bar') || e.target.closest('#controls')) return;
    const edge = edgeOf(e.clientX, e.clientY);
    if (edge) tbBeginResize(edge);
  });
  const bar = document.getElementById('bar');
  bar.addEventListener('mousedown', e => { if (e.button === 0) tbBeginDrag(e.screenX, e.screenY); });
  bar.addEventListener('dblclick', () => tbMaximize());
</script>
```

See `test/demo-custom-titlebar.php` for a complete example.

> `$height` must match the actual height of the bar you draw — it is the number that tells the
> system how many pixels to hand over.
>
> Dragging is unified through `beginDrag()` and resizing through `beginResize(edge)`; the page side
> never branches per platform. `$controlsWidth` is only a reserved parameter, and button exclusion
> happens in the webview via `stopPropagation`.
>
> Passing `null` restores the system titlebar completely, so it can be toggled at any time.

### Set Window Title

Public Method
 - `setTitle` Sets the window title.
    Parameters
  - `string` `$title` The window title name.
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
// Must be called before the run() method.
$win->setTitle("PebView");
```

### Set Window Size

Public Method
 - `setSize` Sets the window size.
    Parameters
  - `int` `$width` The window width.
  - `int` `$height` The window height.
  - `WindowHint` `$hint` The window hint. Default: WindowHint::None
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
// Must be called before the run() method.
$win->setSize(800, 600, WindowHint::None);
```

### Initialize JavaScript

Will load the js code before the window.onload event.

Public Method
 - `init` Initializes the JavaScript.
    Parameters
  - `string` `$js` The JavaScript code to be initialized.
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
// Must be called before the run() method.
$win->init("console.log('hello PebView!');");
```

### Evaluate JavaScript

Public Method
 - `eval` Evaluates the JavaScript code.
    Parameters
  - `string` `$js` The JavaScript code to be evaluated.
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
$win->eval("console.log('hello PebView!');");
```

### Set Window HTML Content

Public Method
 - `setHtml` Sets the window HTML content.
    Parameters
  - `string` `$html` The HTML content to be set.
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
// Must be called before the run() method.
$win->setHtml("<h1>hello PebView!</h1>");
```

### Navigate Window to Specified URL

Public Method
 - `navigate` Navigates the window to the specified URL.
    Parameters
  - `string` `$url` The URL to navigate to.
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
// The  and  methods must be called before the run() method. And only one of the two can be selected.
$win->navigate("https://www.baidu.com");
```

### Bind JS Function

Public function
 - `bind` Binds a JS function.
    Parameters
  - `string` `$name` The function name.
  - `callable` `$callback` The function to call.
    Returns
  - `Window` Returns the window object.

用法：
```PHP
$win->bind("hello", function(...$params) {
    // $win is the window object
    // $params are the parameters passed to the hello() method
    // Use params[0],params[1], ... to access the parameters
    // Example: params[0] is the first parameter, params[1] is the second, etc.
    return $params[0] . " " . $params[1];
});
```

### Unbind JS Function

Public function
 - `unBind` Unbinds a JS function.
    Parameters
  - `string` `$name` The function name.
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
$win->unBind("hello");
```

### Set Window Close Event

Public function
 - `setCloseCallback` Sets the window close event callback.
    Parameters
  - `callable` `$callback` The function to call.
    Returns
  - `Window` Returns the window object.

用法：
```PHP
$win->setCloseCallback(function($win) {
    // $win is the window object
    // Perform some operations when closing the window
    return true; // Return true to allow closing the window
    return false; // Return false to prevent closing the window
});
```

### Show Window

Public function
 - `show` Shows the window.
    Parameters
  - None
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
$win->show();
```

### Hide Window

Public function
 - `hide` Hides the window.
    Parameters
  - None
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
$win->hide();
```

### Create Tray

Windows requires the ICO format; Linux requires the PNG format; MacOs requires the ICO format.
Usually, only the Windows system will work, while Linux and MacOs do not have this effect.

Public function
 - `tray` Creates the tray icon.
    Parameters
  - `string` `$path` The path to the icon file.
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
// Must be called before the run() method.
$win->tray("path/to/icon.ico");
```

### Tray Menu

Public function
 - `trayMenu` Creates the tray menu.
    Parameters
  - `array` `$menu` The menu array.
    Returns
  - `Window` Returns the window object.

Usage:
```PHP
// Must be called before the run() method.
// Note: each menu entry is itself an array, so this is an array of arrays.
$win->trayMenu([
    [
        "text" => "menu1", // Menu name
        "disabled" => 0, // 0 clickable, 1 disabled
        "checked" => 0, // 0 unchecked, 1 checked
        "cb" => function ($win) { // The function to call when the menu is clicked
            // $win is the window object
            // You can perform some operations when the menu is clicked
        },
    ],
    [
        "text" => "menu2",
        "cb" => function ($win) {
            // $win is the window object
            // You can perform some operations when the menu is clicked
        },
    ],
]);
```