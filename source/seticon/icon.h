#pragma once

#ifdef _WIN32
#include <stdlib.h>
#include <wchar.h>
#include <windows.h>
#elif __linux__
#include <gtk/gtk.h>
#endif

enum SetIconErrorCode {
	OK = 0,
	WINDOW_NOT_FOUND,
	ICON_NOT_FOUND,
	OS_UNSUPPORTED,
};
#ifdef __cplusplus
extern "C" {
#endif
enum SetIconErrorCode set_icon(const void* ptr, const char* iconFilePath);
#ifdef __cplusplus
}
#endif
