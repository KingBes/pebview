#include "window.h"
#include <windows.h>
#include <stdlib.h>

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

static HMENU _tray_menu(struct tray_menu *m, UINT *id)
{
    HMENU hmenu = CreatePopupMenu();
    for (; m != NULL && m->text != NULL; m++, (*id)++)
    {
        if (strcmp(m->text, "-") == 0)
        {
            InsertMenu(hmenu, *id, MF_SEPARATOR, TRUE, "");
        }
        else
        {
            MENUITEMINFO item;
            memset(&item, 0, sizeof(item));
            item.cbSize = sizeof(MENUITEMINFO);
            item.fMask = MIIM_ID | MIIM_TYPE | MIIM_STATE | MIIM_DATA;
            item.fType = 0;
            item.fState = 0;
            if (m->disabled)
            {
                item.fState |= MFS_DISABLED;
            }
            if (m->checked)
            {
                item.fState |= MFS_CHECKED;
            }
            item.wID = *id;
            item.dwTypeData = m->text;
            item.dwItemData = (ULONG_PTR)m;

            InsertMenuItem(hmenu, *id, TRUE, &item);
        }
    }
    return hmenu;
}

// 创建托盘
int window_create_tray(const void *ptr, const struct tray *tray)
{
    if (!ptr || !tray)
    {
        return 0;
    }
    HWND hwnd = (HWND)ptr;
    NOTIFYICONDATA nid = {0};
    nid.cbSize = sizeof(NOTIFYICONDATA);
    nid.hWnd = hwnd;
    nid.uID = 1;
    nid.uFlags = NIF_ICON | NIF_TIP | NIF_MESSAGE;
    nid.uCallbackMessage = WM_USER + 1; // 自定义消息，用于处理托盘事件
    // 加载图标从文件
    nid.hIcon = (HICON)LoadImageA(NULL, tray->icon_path, IMAGE_ICON, 0, 0, LR_LOADFROMFILE);
    if (!nid.hIcon)
    {
        return 0;
    }
    // 设置提示文本，使用安全函数
    strncpy_s(nid.szTip, sizeof(nid.szTip), tray->tip, _TRUNCATE);
    // 创建菜单
    UINT menu_id = 1; // 菜单项ID从1开始
    HMENU hmenu = _tray_menu(tray->menu, &menu_id);
    // 保存菜单句柄和图标句柄到窗口属性中
    SetPropA(hwnd, "TrayMenu", hmenu);
    SetPropA(hwnd, "TrayIcon", nid.hIcon);

    // 添加托盘图标
    if (!Shell_NotifyIconA(NIM_ADD, &nid))
    {
        DestroyMenu(hmenu);
        DestroyIcon(nid.hIcon);
        RemovePropA(hwnd, "TrayMenu");
        RemovePropA(hwnd, "TrayIcon");
        return 0;
    }
    return 1;
}

// 销毁托盘
int window_destroy_tray(const void *ptr)
{
    if (!ptr)
    {
        return 0;
    }
    HWND hwnd = (HWND)ptr;
    NOTIFYICONDATA nid = {0};
    nid.cbSize = sizeof(NOTIFYICONDATA);
    nid.hWnd = hwnd;
    nid.uID = 1; // 与创建时一致
    Shell_NotifyIconA(NIM_DELETE, &nid);

    // 销毁菜单
    HMENU hmenu = GetPropA(hwnd, "TrayMenu");
    if (hmenu)
    {
        DestroyMenu(hmenu);
        RemovePropA(hwnd, "TrayMenu");
    }

    // 销毁图标
    HICON hIcon = GetPropA(hwnd, "TrayIcon");
    if (hIcon)
    {
        DestroyIcon(hIcon);
        RemovePropA(hwnd, "TrayIcon");
    }
    return 1;
}
