#pragma once

#ifdef _WIN32
#include <stdlib.h>
#include <wchar.h>
#include <windows.h>
#elif __linux__
#include <gtk/gtk.h>
#elif __APPLE__
#include <AppKit/AppKit.h>
#endif

#ifdef __cplusplus
extern "C"
{
#endif
    int window_close(const void *ptr, int (*fn)(void *));
#ifdef __cplusplus
}
#endif