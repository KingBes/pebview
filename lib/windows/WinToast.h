// 创建一个 toast 通知
void *toastCreate();
// 设置应用名称
void toastSetAppName(void *instance, const char *app_name);
// 设置应用用户模型 ID
void toastSetAppUserModelId(void *instance, const char *name, const char *app_user_model_id, const char *version);
// 验证 WinToast 库是否初始化
bool toastInitialize(void *instance);
// 创建 toast 模板
void *toastCreateTemplate(int template_type);
// 设置 toast 模板第一行文本
void toastSetFirstLine(void *template_ptr, const char *first_line);
// 设置 toast 模板第二行文本
void toastSetSecondLine(void *template_ptr, const char *second_line);
// 设置 toast 模板图片路径
void toastSetImagePath(void *template_ptr, const char *image_path);
// 显示 toast 通知
bool toastShow(void *instance, void *template_ptr);