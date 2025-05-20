#include "webview.h"

// 判断是否为Windows平台
#if defined(_WIN32) || defined(_WIN64)
#include <windows.h>
#endif

// 判断是否为Linux平台
#if defined(__linux__) || defined(__unix__) || defined(__APPLE__)
#include <unistd.h>
#endif

WEBVIEW_API void webview_set_icon(webview_t w, const char *icon)
{
#if defined(_WIN32) || defined(_WIN64)
    // Windows平台下设置图标
    HWND hwnd = (HWND)w;
    HICON hIcon = LoadIcon(NULL, icon);
    SendMessage(hwnd, WM_SETICON, ICON_BIG, (LPARAM)hIcon);
#endif
}