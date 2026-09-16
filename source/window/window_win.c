#include "window.h"
#include <windows.h>
#include <windowsx.h> // GET_X_LPARAM / GET_Y_LPARAM
#include <dwmapi.h>
#include <stdlib.h>
#include <string.h>
#include <stdio.h>

// 窗口显示
int window_show(const void *ptr)
{
    if (!ptr)
    {
        return 0;
    }
    HWND hwnd = (HWND)ptr;
    ShowWindow(hwnd, SW_SHOW);
    return 1;
}

// 窗口隐藏
int window_hide(const void *ptr)
{
    if (!ptr)
    {
        return 0;
    }
    HWND hwnd = (HWND)ptr;
    ShowWindow(hwnd, SW_HIDE);
    return 1;
}

const LPCWSTR char2lpcwstr(char *str)
{
    if (!str || *str == '\0')
    {
        // 处理空指针或空字符串
        static const wchar_t empty[] = L"";
        return empty;
    }

    // 计算所需缓冲区大小
    int wchar_count = MultiByteToWideChar(
        CP_UTF8, 0, str, -1, NULL, 0);

    if (wchar_count == 0)
    {
        static const wchar_t empty[] = L"";
        return empty;
    }

    // 使用静态缓冲区（线程不安全）
    static wchar_t *buffer = NULL;
    static size_t buffer_size = 0;

    // 如果当前缓冲区太小，重新分配
    if (buffer_size < (size_t)wchar_count)
    {
        if (buffer)
        {
            free(buffer);
        }
        buffer = (wchar_t *)malloc(wchar_count * sizeof(wchar_t));
        if (!buffer)
        {
            buffer_size = 0;
            static const wchar_t empty[] = L"";
            return empty;
        }
        buffer_size = wchar_count;
    }

    // 执行转换
    if (!MultiByteToWideChar(
            CP_UTF8, 0, str, -1, buffer, wchar_count))
    {
        static const wchar_t empty[] = L"";
        return empty;
    }

    return buffer;
}

// 托盘菜单项数据结构
typedef struct
{
    int id;
    char *text;
    int disabled;
    int checked;
    void (*callback)(const void *ptr);
} TrayMenuItem;

// 托盘图标数据结构
typedef struct
{
    NOTIFYICONDATA nid;
    HMENU hMenu;
    HWND hwnd;
    HWND hTrayWnd;
    TrayMenuItem *items;
    int item_count;
    HICON hIcon;
} TrayData;

// 窗口类名
static const char *TRAY_WINDOW_CLASS = "TrayHelperWindowClass";

// 窗口过程函数，用于处理托盘消息
static LRESULT CALLBACK TrayWndProc(HWND hwnd, UINT msg, WPARAM wParam, LPARAM lParam)
{
    TrayData *tray_data = (TrayData *)GetWindowLongPtr(hwnd, GWLP_USERDATA);

    switch (msg)
    {
    case WM_CREATE:
        // 窗口创建时设置用户数据
        {
            CREATESTRUCT *cs = (CREATESTRUCT *)lParam;
            SetWindowLongPtr(hwnd, GWLP_USERDATA, (LONG_PTR)cs->lpCreateParams);
        }
        break;

    case WM_DESTROY:
        PostQuitMessage(0);
        break;

    case WM_COMMAND:
        // 处理菜单项点击
        if (tray_data && tray_data->items)
        {
            int id = LOWORD(wParam);
            for (int i = 0; i < tray_data->item_count; i++)
            {
                if (tray_data->items[i].id == id &&
                    tray_data->items[i].callback &&
                    !tray_data->items[i].disabled)
                {
                    tray_data->items[i].callback(&tray_data->items[i]);
                    break;
                }
            }
        }
        break;

    case WM_USER + 1:
        // 处理托盘图标消息
        if (lParam == WM_RBUTTONUP || lParam == WM_CONTEXTMENU || lParam == WM_LBUTTONUP)
        {
            // 点击，显示菜单
            if (tray_data && tray_data->hMenu)
            {
                // 获取鼠标位置
                POINT pt;
                GetCursorPos(&pt);

                // 设置前景窗口
                SetForegroundWindow(hwnd);

                // 更新菜单项状态
                for (int i = 0; i < tray_data->item_count; i++)
                {
                    UINT state = MF_BYCOMMAND;
                    if (tray_data->items[i].disabled)
                        state |= MF_GRAYED;
                    else
                        state |= MF_ENABLED;

                    if (tray_data->items[i].checked)
                        state |= MF_CHECKED;
                    else
                        state |= MF_UNCHECKED;

                    CheckMenuItem(tray_data->hMenu, tray_data->items[i].id, state);
                    EnableMenuItem(tray_data->hMenu, tray_data->items[i].id,
                                   tray_data->items[i].disabled ? MF_GRAYED : MF_ENABLED);
                }

                // 显示弹出菜单
                TrackPopupMenu(tray_data->hMenu,
                               TPM_RIGHTBUTTON | TPM_BOTTOMALIGN,
                               pt.x, pt.y, 0, hwnd, NULL);

                // 发送消息以确保菜单正确关闭
                PostMessage(hwnd, WM_NULL, 0, 0);
            }
        }
        break;

    default:
        return DefWindowProc(hwnd, msg, wParam, lParam);
    }
    return 0;
}

/**
 * @brief 创建窗口托盘
 *
 * @param ptr 窗口句柄
 * @param icon 托盘图标路径
 * @return void* 托盘句柄
 */
void *window_tray(const void *ptr, const char *icon)
{
    if (!ptr)
    {
        return NULL;
    }

    HWND hwnd = (HWND)ptr;

    // 注册窗口类
    WNDCLASSEX wc = {0};
    wc.cbSize = sizeof(WNDCLASSEX);
    wc.lpfnWndProc = TrayWndProc;
    wc.hInstance = GetModuleHandle(NULL);
    wc.lpszClassName = TRAY_WINDOW_CLASS;

    if (!GetClassInfoEx(wc.hInstance, TRAY_WINDOW_CLASS, &wc))
    {
        if (!RegisterClassEx(&wc))
        {
            return NULL;
        }
    }

    // 创建托盘数据结构
    TrayData *tray_data = (TrayData *)calloc(1, sizeof(TrayData));
    if (!tray_data)
    {
        return NULL;
    }

    tray_data->hwnd = hwnd;
    tray_data->hMenu = CreatePopupMenu();
    tray_data->item_count = 0;
    tray_data->items = NULL;

    // 创建隐藏窗口用于接收托盘消息
    tray_data->hTrayWnd = CreateWindowEx(
        0,
        TRAY_WINDOW_CLASS,
        "TrayHelper",
        0,
        0, 0, 0, 0,
        NULL,
        NULL,
        GetModuleHandle(NULL),
        tray_data // 将tray_data作为创建参数传递
    );

    if (!tray_data->hTrayWnd)
    {
        free(tray_data);
        return NULL;
    }

    // 加载图标
    if (icon)
    {
        tray_data->hIcon = (HICON)LoadImage(
            NULL,
            icon,
            IMAGE_ICON,
            0, 0,
            LR_LOADFROMFILE | LR_DEFAULTSIZE | LR_LOADTRANSPARENT);
    }

    // 如果从文件加载失败，尝试使用默认图标
    if (!tray_data->hIcon)
    {
        tray_data->hIcon = LoadIcon(NULL, IDI_APPLICATION);
    }

    // 设置NOTIFYICONDATA结构
    tray_data->nid.cbSize = sizeof(NOTIFYICONDATA);
    tray_data->nid.hWnd = tray_data->hTrayWnd;
    tray_data->nid.uID = 1;
    tray_data->nid.uFlags = NIF_ICON | NIF_MESSAGE | NIF_TIP;
    tray_data->nid.uCallbackMessage = WM_USER + 1;
    tray_data->nid.hIcon = tray_data->hIcon;
    strncpy(tray_data->nid.szTip, "", sizeof(tray_data->nid.szTip) - 1);

    // 添加托盘图标
    if (!Shell_NotifyIcon(NIM_ADD, &tray_data->nid))
    {
        if (tray_data->hIcon && tray_data->hIcon != LoadIcon(NULL, IDI_APPLICATION))
        {
            DestroyIcon(tray_data->hIcon);
        }
        DestroyWindow(tray_data->hTrayWnd);
        free(tray_data);
        return NULL;
    }

    return tray_data;
}

/**
 * @brief 添加托盘菜单
 *
 * @param tray 托盘句柄
 * @param menu 菜单配置
 */
void window_tray_add_menu(const void *tray, struct tray_menu *menu)
{
    if (!tray || !menu || !menu->text)
    {
        return;
    }

    TrayData *tray_data = (TrayData *)tray;

    // 分配或重新分配菜单项数组
    TrayMenuItem *new_items = realloc(
        tray_data->items,
        sizeof(TrayMenuItem) * (tray_data->item_count + 1));

    if (!new_items)
    {
        return;
    }

    tray_data->items = new_items;

    // 复制菜单项数据
    TrayMenuItem *item = &tray_data->items[tray_data->item_count];
    item->id = menu->id;
    item->disabled = menu->disabled;
    item->checked = menu->checked;
    item->callback = menu->callback;

    // 复制文本
    item->text = _strdup(menu->text);
    if (!item->text)
    {
        return;
    }

    // 添加菜单项到菜单
    UINT flags = MF_STRING;
    if (menu->disabled)
        flags |= MF_GRAYED;
    if (menu->checked)
        flags |= MF_CHECKED;

    const LPCWSTR wtext = char2lpcwstr(menu->text);
    AppendMenuW(tray_data->hMenu, flags, menu->id, wtext);
    
    // 增加菜单项计数
    tray_data->item_count++;
}

/**
 * @brief 移除托盘图标（清理函数）
 *
 * @param tray 托盘句柄
 */
void window_tray_remove(void *tray)
{
    if (!tray)
    {
        return;
    }

    TrayData *tray_data = (TrayData *)tray;

    // 移除托盘图标
    Shell_NotifyIcon(NIM_DELETE, &tray_data->nid);

    // 清理资源
    if (tray_data->hIcon && tray_data->hIcon != LoadIcon(NULL, IDI_APPLICATION))
    {
        DestroyIcon(tray_data->hIcon);
    }

    if (tray_data->hMenu)
    {
        DestroyMenu(tray_data->hMenu);
    }

    if (tray_data->items)
    {
        // 释放所有菜单项的文本
        for (int i = 0; i < tray_data->item_count; i++)
        {
            if (tray_data->items[i].text)
            {
                free(tray_data->items[i].text);
            }
        }
        free(tray_data->items);
    }

    if (tray_data->hTrayWnd && IsWindow(tray_data->hTrayWnd))
    {
        DestroyWindow(tray_data->hTrayWnd);
    }

    free(tray_data);
}

// ---------------------------------------------------------------------------
// 标题栏外观
// ---------------------------------------------------------------------------

// DwmSetWindowAttribute 的属性 id。取值来自 dwmapi.h 的 DWMWINDOWATTRIBUTE，
// 但这里用自定义宏名写数值而不是引用枚举成员：老版本 Windows SDK 没有后两项，
// 而 enum 成员无法用 #ifndef 探测，直接写数值最稳。
#define PEBVIEW_DWMWA_USE_IMMERSIVE_DARK_MODE 20
#define PEBVIEW_DWMWA_CAPTION_COLOR           35
#define PEBVIEW_DWMWA_TEXT_COLOR              36

// 读取系统"应用颜色模式"。注册表里 AppsUseLightTheme = 0 表示深色。
static int pebview_system_prefers_dark(void)
{
    HKEY key = NULL;
    DWORD value = 1;
    DWORD size = sizeof(value);
    DWORD type = 0;

    if (RegOpenKeyExA(HKEY_CURRENT_USER,
                      "Software\\Microsoft\\Windows\\CurrentVersion\\Themes\\Personalize",
                      0, KEY_QUERY_VALUE, &key) != ERROR_SUCCESS)
    {
        return 0;
    }
    if (RegQueryValueExA(key, "AppsUseLightTheme", NULL, &type,
                         (LPBYTE)&value, &size) != ERROR_SUCCESS)
    {
        value = 1;
    }
    RegCloseKey(key);
    return value == 0;
}

// 0xRRGGBB -> COLORREF（COLORREF 是 0x00BBGGRR，与 RGB 宏的入参顺序一致）
static COLORREF pebview_rgb(int rgb)
{
    return RGB((rgb >> 16) & 0xFF, (rgb >> 8) & 0xFF, rgb & 0xFF);
}

int window_set_titlebar_theme(const void *ptr, int mode, int caption, int text)
{
    if (!ptr)
    {
        return 1; // WINDOW_NOT_FOUND
    }

    HWND hwnd = (HWND)ptr;
    BOOL dark = (mode < 0) ? (pebview_system_prefers_dark() ? TRUE : FALSE)
                           : (mode == 1 ? TRUE : FALSE);

    // Win10 1809+ 的正式取值是 20；1809/1903 的早期版本用的是 19，失败则回退
    if (FAILED(DwmSetWindowAttribute(hwnd, PEBVIEW_DWMWA_USE_IMMERSIVE_DARK_MODE,
                                     &dark, sizeof(dark))))
    {
        DwmSetWindowAttribute(hwnd, 19, &dark, sizeof(dark));
    }

    // 标题栏配色是 Win11 才有的能力，老系统上这两个调用会返回 E_INVALIDARG。
    // 这里不算失败：浅/深色已经生效，用户拿到的仍是合理结果。
    if (caption >= 0)
    {
        COLORREF c = pebview_rgb(caption);
        DwmSetWindowAttribute(hwnd, PEBVIEW_DWMWA_CAPTION_COLOR, &c, sizeof(c));
    }
    if (text >= 0)
    {
        COLORREF c = pebview_rgb(text);
        DwmSetWindowAttribute(hwnd, PEBVIEW_DWMWA_TEXT_COLOR, &c, sizeof(c));
    }

    // 非客户区不会因为 DWM 属性变化而自动重绘，必须显式触发一次
    RedrawWindow(hwnd, NULL, NULL, RDW_FRAME | RDW_INVALIDATE);

    return 0; // OK
}

// ---------------------------------------------------------------------------
// 自定义标题栏（真正无边框 + JS 驱动缩放）
// ---------------------------------------------------------------------------
//
// 走的是"真正无边框"：去掉系统标题栏的绘制，让网页内容自己画标题栏。
// WM_NCCALCSIZE 直接 return 0，客户区填满整个窗口 —— 不留任何会自动缩放的边框，
// 内容贴到窗口顶边、零顶部默认边距（用户明确不要"可自动大小的窗口"带来的默认顶栏）。
// 窗口阴影、圆角由 DWM 在非客户区外缘处理，不影响内容布局。
//
// 缩放怎么发起（2026-09-16 实测结论 + 调研）：
//   WebView2 把网页内容放在一串铺满客户区的子窗口里 ——
//   webview → webview_widget → Chrome_WidgetWin_0 → Chrome_WidgetWin_1
//           → Chrome_RenderWidgetHostHWND
//   鼠标落在最深的那层，**顶层窗口的 WM_NCHITTEST 根本不会被系统问到**。
//   实测：同一位置在"系统标题栏"模式下 WindowFromPoint 返回顶层 webview，
//   打开自定义标题栏后返回 Chrome_RenderWidgetHostHWND。
//   所以下面 WM_NCHITTEST 里回 HTCAPTION 那段在本项目里到不了，只当兜底保留。
//   真正发起拖动/缩放的是 window_begin_move_drag / window_begin_resize_drag
//   （PHP 侧 beginDrag / beginResize）：JS 在 mousedown 里把屏幕坐标（拖动）或
//   判定出的 edge（缩放）传进来，这里合成 WM_NCLBUTTONDOWN(HTCAPTION) /
//   WM_SYSCOMMAND(SC_SIZE|edge) 进入系统模态循环（贴边、双击最大化等原生行为都在）。
//   三平台同一句 JS，不按平台分支。
//
// 三个关键消息：
//   WM_NCCALCSIZE     return 0（客户区=整窗，无边框、无顶部边距）
//   WM_NCHITTEST      先让 DefWindowProc 判边缘（返回 HTLEFT 之类就直接放行），
//                     再对标题栏区域回 HTCAPTION（WebView2 下走不到，兜底）
//   WM_GETMINMAXINFO  最大化时按显示器工作区修正，避免盖住任务栏
//

// 钩子插在哪是个关键点：webview 自己也用 SetWindowLongPtr(GWLP_WNDPROC) 子类化过
// 窗口（见 source/webview/webview.h 的 win32_edge_engine 构造函数），所以我们必须
// 保存它那份过程并老实转发，不能直接替换掉。
//
// 状态存放用 SetPropW 而不是 GWLP_USERDATA —— 后者已被 webview 占用（存 engine 指针）。

#define PEBVIEW_TITLEBAR_PROP L"PebViewTitlebarState"

typedef struct
{
    HWND hwnd;
    WNDPROC original_proc; // webview 的窗口过程；非 NC 消息都转给它
    int height;            // 标题栏高度，0 = 未启用自定义标题栏
    int controls_width;    // 右侧按钮区宽度（该区域内不响应拖动）
    int drag;              // 标题栏区域是否可拖动
    int last_maximized;    // 上次的最大化状态，用来抑制重复回调
    void (*state_cb)(const void *ptr, int state);
} PebViewTitlebar;

static PebViewTitlebar *pebview_titlebar_of(HWND hwnd)
{
    return (PebViewTitlebar *)GetPropW(hwnd, PEBVIEW_TITLEBAR_PROP);
}

static void pebview_notify_state(PebViewTitlebar *tb)
{
    if (!tb || !tb->state_cb)
    {
        return;
    }
    int maximized = IsZoomed(tb->hwnd) ? 1 : 0;
    if (maximized != tb->last_maximized)
    {
        tb->last_maximized = maximized;
        tb->state_cb(tb->hwnd, maximized);
    }
}

// 只处理非客户区相关的几个消息，其余原样转发给 webview 的过程
static LRESULT CALLBACK pebview_titlebar_proc(HWND hwnd, UINT msg, WPARAM wp, LPARAM lp)
{
    PebViewTitlebar *tb = pebview_titlebar_of(hwnd);

    if (!tb || !tb->original_proc)
    {
        return DefWindowProcW(hwnd, msg, wp, lp);
    }

    switch (msg)
    {
    case WM_NCCALCSIZE:
        if (wp == TRUE)
        {
            // 真正无边框：客户区 = 整个窗口。不保留任何非客户区（标题栏 / 边框都不要），
            // 内容直接贴到窗口顶边、零顶部默认边距。
            //
            // 为什么不留那圈会自动缩放的边框：留了的话顶部会凭空多出一条边距，
            // 正是用户明确不要的"可自动大小的窗口"带来的默认顶栏。
            //
            // 边缘调整大小怎么办：这扇窗是 WebView2 的宿主，子窗口链铺满客户区、
            // 把鼠标全拿走，顶层窗口的 WM_NCHITTEST 在内容区根本不会被问到，
            // 留边框也抓不到边缘。所以缩放改由 JS 在 mousedown 里判定边缘、调
            // window_begin_resize_drag() 显式发起（合成 WM_NCLBUTTONDOWN(HT*)，
            // 仍是系统的模态缩放循环，贴边 / 双击最大化原生生效），三平台同一句 JS。
            // 最大化不溢出任务栏由 WM_GETMINMAXINFO 修正。
            return 0;
        }
        break;

    case WM_NCHITTEST:
    {
        if (tb->height <= 0)
        {
            break;
        }

        // 客户区铺满整窗后，这里其实回不到我们这层（WebView2 的子窗口拿走了鼠标）。
        // 保留这段是为了在别的宿主（或子窗口没铺满时）仍能按"标题栏可拖"工作。
        LRESULT hit = DefWindowProcW(hwnd, msg, wp, lp);
        if (hit != HTCLIENT)
        {
            return hit;
        }

        // 屏幕坐标 → 客户区坐标
        POINT pt;
        pt.x = GET_X_LPARAM(lp);
        pt.y = GET_Y_LPARAM(lp);
        ScreenToClient(hwnd, &pt);

        RECT rc;
        GetClientRect(hwnd, &rc);

        // 右侧按钮区：不响应拖动，让点击落到 webview 上，HTML 里的按钮才点得到。
        //
        // 注意：WebView2 下这一段实际走不到（鼠标被子窗口拿走），按钮排除改由
        // webview 侧用 stopPropagation 处理；controls_width 只在没有子窗口盖住的场合才起作用。
        int in_controls = (tb->controls_width > 0 && pt.x >= rc.right - tb->controls_width);

        if (!in_controls && pt.y >= 0 && pt.y < tb->height)
        {
            return tb->drag ? HTCAPTION : HTCLIENT;
        }
        break;
    }

    case WM_GETMINMAXINFO:
    {
        if (tb->height <= 0)
        {
            break;
        }

        // 先让 webview 处理它自己的尺寸约束（setSize 的 MIN / MAX 提示）
        CallWindowProcW(tb->original_proc, hwnd, msg, wp, lp);

        // 客户区铺满整窗之后，默认的最大化度量会让窗口顶到屏幕边缘、盖住任务栏
        // （它按"客户区要填满工作区"算，多出来的一圈边框会把任务栏压住）。
        // 这里按显示器工作区修正最大化的位置与尺寸。
        LPMINMAXINFO mmi = (LPMINMAXINFO)lp;
        MONITORINFO mi;
        mi.cbSize = sizeof(MONITORINFO);
        if (GetMonitorInfoW(MonitorFromWindow(hwnd, MONITOR_DEFAULTTONEAREST), &mi))
        {
            mmi->ptMaxPosition.x = mi.rcWork.left - mi.rcMonitor.left;
            mmi->ptMaxPosition.y = mi.rcWork.top - mi.rcMonitor.top;
            mmi->ptMaxSize.x = mi.rcWork.right - mi.rcWork.left;
            mmi->ptMaxSize.y = mi.rcWork.bottom - mi.rcWork.top;
        }
        return 0;
    }

    case WM_SIZE:
        // 先交给 webview（它要 resize 内部 widget），再上报最大化状态
        CallWindowProcW(tb->original_proc, hwnd, msg, wp, lp);
        pebview_notify_state(tb);
        return 0;

    case WM_NCDESTROY:
        // 归还窗口过程、清掉挂着的属性，避免窗口销毁后还被回调
        if (tb->original_proc)
        {
            SetWindowLongPtrW(hwnd, GWLP_WNDPROC, (LONG_PTR)tb->original_proc);
        }
        RemovePropW(hwnd, PEBVIEW_TITLEBAR_PROP);
        free(tb);
        return DefWindowProcW(hwnd, msg, wp, lp);

    default:
        break;
    }

    return CallWindowProcW(tb->original_proc, hwnd, msg, wp, lp);
}

// 首次使用时挂钩子；已挂过则直接返回
static PebViewTitlebar *pebview_titlebar_ensure(HWND hwnd)
{
    PebViewTitlebar *tb = pebview_titlebar_of(hwnd);
    if (tb)
    {
        return tb;
    }

    tb = (PebViewTitlebar *)calloc(1, sizeof(PebViewTitlebar));
    if (!tb)
    {
        return NULL;
    }

    tb->hwnd = hwnd;
    tb->original_proc = (WNDPROC)SetWindowLongPtrW(hwnd, GWLP_WNDPROC,
                                                   (LONG_PTR)pebview_titlebar_proc);
    if (!tb->original_proc)
    {
        free(tb);
        return NULL;
    }

    tb->last_maximized = IsZoomed(hwnd) ? 1 : 0;
    SetPropW(hwnd, PEBVIEW_TITLEBAR_PROP, (HANDLE)tb);
    return tb;
}

// 让系统重新计算非客户区（WM_NCCALCSIZE 会重新到达窗口过程）
static void pebview_refresh_frame(HWND hwnd)
{
    SetWindowPos(hwnd, NULL, 0, 0, 0, 0,
                 SWP_FRAMECHANGED | SWP_NOMOVE | SWP_NOSIZE | SWP_NOZORDER | SWP_NOACTIVATE);
    RedrawWindow(hwnd, NULL, NULL, RDW_FRAME | RDW_INVALIDATE);
}

// 切换自定义标题栏会连带改变客户区尺寸（吃掉/还回 caption 那一条），
// 而 PHP 侧 setSize() 指定的一直是"内容区尺寸"。这里按差值把窗口外框补偿回来，
// 让 setSize(880,560) 在开/关自定义标题栏前后得到的客户区都是 880x560。
// 顺带：这次尺寸变化会触发 WM_SIZE，webview 会重新铺它的子窗口。
static void pebview_settle_client_size(HWND hwnd, RECT before)
{
    RECT after;
    GetClientRect(hwnd, &after);

    int dw = (before.right - before.left) - (after.right - after.left);
    int dh = (before.bottom - before.top) - (after.bottom - after.top);
    if (dw == 0 && dh == 0)
    {
        return;
    }

    RECT wr;
    GetWindowRect(hwnd, &wr);
    SetWindowPos(hwnd, NULL, 0, 0,
                 (wr.right - wr.left) + dw,
                 (wr.bottom - wr.top) + dh,
                 SWP_NOMOVE | SWP_NOZORDER | SWP_NOACTIVATE);
}

int window_set_titlebar_geometry(const void *ptr, int height, int controls_width, int drag)
{
    if (!ptr)
    {
        return 1; // WINDOW_NOT_FOUND
    }

    HWND hwnd = (HWND)ptr;

    RECT before;
    GetClientRect(hwnd, &before); // 补偿用：切换前后的内容区尺寸要对得上

    // height <= 0：关闭自定义标题栏，退回系统标题栏
    if (height <= 0)
    {
        PebViewTitlebar *tb = pebview_titlebar_of(hwnd);
        if (tb)
        {
            tb->height = 0;
            if (tb->original_proc)
            {
                SetWindowLongPtrW(hwnd, GWLP_WNDPROC, (LONG_PTR)tb->original_proc);
            }
            RemovePropW(hwnd, PEBVIEW_TITLEBAR_PROP);
            free(tb);
        }
        pebview_refresh_frame(hwnd);
        pebview_settle_client_size(hwnd, before);
        return 0;
    }

    PebViewTitlebar *tb = pebview_titlebar_ensure(hwnd);
    if (!tb)
    {
        return 3; // OS_UNSUPPORTED（这里其实是内存/挂钩失败，按不支持处理）
    }

    tb->height = height;
    tb->controls_width = (controls_width > 0) ? controls_width : 0;
    tb->drag = drag ? 1 : 0;

    pebview_refresh_frame(hwnd);
    pebview_settle_client_size(hwnd, before);
    return 0;
}

int window_minimize(const void *ptr)
{
    if (!ptr)
    {
        return 1;
    }
    ShowWindow((HWND)ptr, SW_MINIMIZE);
    return 0;
}

int window_toggle_maximize(const void *ptr)
{
    if (!ptr)
    {
        return 0;
    }

    HWND hwnd = (HWND)ptr;
    ShowWindow(hwnd, IsZoomed(hwnd) ? SW_RESTORE : SW_MAXIMIZE);

    // 手动切换时 WM_SIZE 不一定同步到达，这里补一次状态通知
    PebViewTitlebar *tb = pebview_titlebar_of(hwnd);
    if (tb)
    {
        pebview_notify_state(tb);
    }

    return IsZoomed(hwnd) ? 1 : 0;
}

int window_is_maximized(const void *ptr)
{
    if (!ptr)
    {
        return 0;
    }
    return IsZoomed((HWND)ptr) ? 1 : 0;
}

int window_set_state_callback(const void *ptr, void (*cb)(const void *ptr, int state))
{
    if (!ptr)
    {
        return 1;
    }

    HWND hwnd = (HWND)ptr;

    // 回调挂在同一个结构上。即使还没启用自定义标题栏也要挂钩子，
    // 否则用户只是想监听最大化事件就不行了（此时 height 保持 0，不影响外观）。
    PebViewTitlebar *tb = pebview_titlebar_ensure(hwnd);
    if (!tb)
    {
        return 3;
    }

    tb->state_cb = cb;
    return 0;
}

// JS 在标题栏 mousedown 里调过来，发起一次**系统级的窗口拖动**。
//
// 为什么不能靠 WM_NCHITTEST：WebView2 的内容放在一串铺满客户区的子窗口里
// （webview_widget → Chrome_WidgetWin_* → Chrome_RenderWidgetHostHWND），
// 鼠标事件被子窗口认领，顶层窗口的 WM_NCHITTEST 不会被系统问到。
// 所以这里绕开命中测试：自己把鼠标捕获交还给系统，再合成一条
// WM_NCLBUTTONDOWN(HTCAPTION) —— 之后就是 DefWindowProc 的模态移动循环，
// 贴边（Aero Snap）、双击最大化之类的原生行为都在，跟真的拖标题栏一样。
//
// 三平台写成同一个调用（Linux 走 gdk_window_begin_move_drag，macOS 手动搬窗口），
// 页面里那句 JS 不需要按平台分支。
int window_begin_move_drag(const void *ptr, int x, int y)
{
    if (!ptr)
    {
        return 1; // WINDOW_NOT_FOUND
    }

    HWND hwnd = (HWND)ptr;

    // 左键必须真的按着。否则合成的 WM_NCLBUTTONDOWN 会启动一个等不到松开事件的
    // 模态移动循环，把整个界面卡死（JS 的 mousedown 与这里之间可能有竞态）。
    if ((GetAsyncKeyState(VK_LBUTTON) & 0x8000) == 0)
    {
        return 4; // NOT_DRAGGING
    }

    POINT pt;
    pt.x = x;
    pt.y = y;
    if (x == 0 && y == 0)
    {
        GetCursorPos(&pt); // 没给坐标就用当前光标位置
    }

    ReleaseCapture(); // 从 webview 子窗口手里把捕获收回来，交给系统的移动循环
    SendMessageW(hwnd, WM_NCLBUTTONDOWN, HTCAPTION, MAKELPARAM(pt.x, pt.y));
    return 0;
}

// 从外部发起窗口缩放（edge 为 WMSZ_* 编码：1=LEFT 2=RIGHT 3=TOP 4=TOPLEFT
// 5=TOPRIGHT 6=BOTTOM 7=BOTTOMLEFT 8=BOTTOMRIGHT）。
//
// 与 begin_move_drag 同一思路：这扇窗是 WebView2 宿主，子窗口铺满客户区、吃掉了
// 所有鼠标事件，顶层窗口在边缘收不到 WM_NCHITTEST，留系统边框也抓不到边缘。
// 所以缩放由 JS 在 mousedown 里判定边缘、把 edge 传进来，这里收光标、合成
// WM_NCLBUTTONDOWN(HT*)、进系统缩放循环（贴边 / 双击最大化等原生行为都在）。
//
// 注意：不能用 WM_SYSCOMMAND(SC_SIZE | edge) 替代 WM_NCLBUTTONDOWN(HT*) —— 本窗口
// 真正无边框，系统进 SC_SIZE 循环时抓不到真实边框会直接放弃缩放（"可拖不可缩"的根因）。
// 三平台同一句 JS（Linux 走 gdk_window_begin_resize_drag，macOS 按光标位移搬窗口），
// 页面侧不需要按平台分支。只在左键真按着时发起一次；左键已松开直接返回 4。
int window_begin_resize_drag(const void *ptr, int edge)
{
    if (!ptr)
    {
        return 1; // WINDOW_NOT_FOUND
    }

    HWND hwnd = (HWND)ptr;

    // 左键必须真按着，否则进 resize 模态循环会卡死（同 begin_move_drag 的竞态）
    if ((GetAsyncKeyState(VK_LBUTTON) & 0x8000) == 0)
    {
        return 4; // NOT_DRAGGING
    }

    // edge 必须是合法的 WMSZ_* 方向（1..8）；越界按窗口不存在处理，让 PHP 侧抛错提示
    if (edge < 1 || edge > 8)
    {
        return 1;
    }

    ReleaseCapture(); // 从 webview 子窗口收回鼠标捕获，交给系统的缩放循环

    // edge(WMSZ_* 1..8) -> HT*(10..17)：和拖动一样走 WM_NCLBUTTONDOWN，让系统信任
    // 我们给的命中值、不再去非客户区重新判定 —— 无边框窗口没有真实边框，用
    // WM_SYSCOMMAND(SC_SIZE | edge) 会因为"抓不到边框"直接放弃缩放（这就是之前
    // "可拖不可缩"的坑）。当前光标位置作为缩放起点（左键必须真按着，见上面的判断）。
    static const int ht_of_edge[9] = {
        0, HTLEFT, HTRIGHT, HTTOP, HTTOPLEFT, HTTOPRIGHT, HTBOTTOM, HTBOTTOMLEFT, HTBOTTOMRIGHT
    };
    int ht = ht_of_edge[edge];
    POINT pt;
    GetCursorPos(&pt);
    SendMessageW(hwnd, WM_NCLBUTTONDOWN, (WPARAM)ht, MAKELPARAM(pt.x, pt.y));
    return 0;
}