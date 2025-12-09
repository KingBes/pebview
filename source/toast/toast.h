#ifndef WINTOAST_C_H
#define WINTOAST_C_H

#ifdef __cplusplus
extern "C"
{
#endif

#ifdef WINTOAST_EXPORTS
#define TOAST_API __declspec(dllexport)
#else
#define TOAST_API __declspec(dllimport)
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

#endif // WINTOAST_C_H
