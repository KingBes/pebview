#pragma once

#ifdef _WIN32
#include <stdlib.h>
#include <wchar.h>
#include <windows.h>
#elif __linux__
#include <gtk/gtk.h>
#endif

#ifndef MATH_API
#if defined(WEBVIEW_SHARED) || defined(WEBVIEW_BUILD_SHARED)
#if defined(_WIN32) || defined(__CYGWIN__)
#if defined(WEBVIEW_BUILD_SHARED)
#define MATH_API __declspec(dllexport)
#else
#define MATH_API __declspec(dllimport)
#endif
#else
#define MATH_API __attribute__((visibility("default")))
#endif
#elif !defined(WEBVIEW_STATIC) && defined(__cplusplus)
#define MATH_API inline
#else
#define MATH_API extern
#endif
#endif

#ifdef __cplusplus
extern "C"
{
#endif

    // MATH_API xxx

#ifdef __cplusplus
}
#endif
