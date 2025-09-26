#include "window.h"
#include <windows.h>
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
        if (lParam == WM_RBUTTONUP || lParam == WM_CONTEXTMENU)
        {
            // 右键点击，显示菜单
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
        else if (lParam == WM_LBUTTONUP)
        {
            // 左键点击，显示/隐藏窗口
            if (tray_data && tray_data->hwnd)
            {
                if (IsWindowVisible(tray_data->hwnd))
                {
                    ShowWindow(tray_data->hwnd, SW_HIDE);
                }
                else
                {
                    ShowWindow(tray_data->hwnd, SW_SHOW);
                    SetForegroundWindow(tray_data->hwnd);
                }
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