/**
 * @brief 显示 toast 通知
 *
 * @param app 应用名称
 * @param title 通知标题
 * @param message 通知消息
 * @param image_path 图片路径
 * @return TOAST_API bool 是否成功显示通知
 */
bool toastShow(
    const char *app,
    const char *title,
    const char *message,
    const char *image_path);