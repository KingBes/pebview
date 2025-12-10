#pragma once

#include <stdbool.h>

#ifdef _WIN32
#ifdef BUILDING_DLL
#define TOAST_API __declspec(dllexport)
#else
#define TOAST_API __declspec(dllimport)
#endif
#else
#define TOAST_API __attribute__((visibility("default")))
#endif

#ifdef __cplusplus
extern "C"
{
#endif

    /**
     * @brief 显示 toast 通知
     *
     * @param app 应用名称
     * @param title 通知标题
     * @param message 通知消息
     * @param image_path 图片路径
     * @return TOAST_API bool 是否成功显示通知
     */
    TOAST_API bool toastShow(
        const char *app,
        const char *title,
        const char *message,
        const char *image_path);

#ifdef __cplusplus
}
#endif