#include "other.h"

#include <windows.h>
#include <stdlib.h>
#include <string.h>

// 窗口显示
int other_window_show(const void *window)
{
    if (window == NULL)
    {
        return 0;
    }
    return ShowWindow((HWND)window, SW_SHOW);
}

// 窗口隐藏
int other_window_hide(const void *window)
{
    if (window == NULL)
    {
        return 0;
    }
    return ShowWindow((HWND)window, SW_HIDE);
}

// 恢复原窗口大小
void other_window_restore_size(const void *window)
{
    if (window == NULL)
    {
        return;
    }
    ShowWindow((HWND)window, SW_RESTORE);
}

// 窗口最小化
int other_window_minimize(const void *window)
{
    if (window == NULL)
    {
        return 0;
    }
    return ShowWindow((HWND)window, SW_MINIMIZE);
}

// 窗口最大化
int other_window_maximize(const void *window)
{
    if (window == NULL)
    {
        return 0;
    }
    return ShowWindow((HWND)window, SW_MAXIMIZE);
}

// 窗口关闭
int other_window_close(const void *window)
{
    if (window == NULL)
    {
        return 0;
    }
    return PostMessage((HWND)window, WM_CLOSE, 0, 0);
}
