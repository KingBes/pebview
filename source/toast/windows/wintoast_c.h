#ifndef WINTOAST_C_H
#define WINTOAST_C_H

#ifdef __cplusplus
extern "C"
{
#endif

#ifdef WINTOAST_EXPORTS
#define WINTOAST_API __declspec(dllexport)
#else
#define WINTOAST_API __declspec(dllimport)
#endif

    // 创建一个 toast 通知
    WINTOAST_API void *toastCreate();
    // 设置应用名称
    WINTOAST_API void toastSetAppName(void *instance, const char *app_name);
    // 设置应用用户模型 ID
    WINTOAST_API void toastSetAppUserModelId(void *instance, const char *name, const char *app_user_model_id, const char *version);
    // 验证 WinToast 库是否初始化
    WINTOAST_API bool toastInitialize(void *instance);
    // 创建 toast 模板
    WINTOAST_API void *toastCreateTemplate(int template_type);
    // 设置 toast 模板第一行文本
    WINTOAST_API void toastSetFirstLine(void *template_ptr, const char *first_line);
    // 设置 toast 模板第二行文本
    WINTOAST_API void toastSetSecondLine(void *template_ptr, const char *second_line);
    // 设置 toast 模板图片路径
    WINTOAST_API void toastSetImagePath(void *template_ptr, const char *image_path);
    // 显示 toast 通知
    WINTOAST_API bool toastShow(void *instance, void *template_ptr);

#ifdef __cplusplus
}
#endif

#endif // WINTOAST_C_H
