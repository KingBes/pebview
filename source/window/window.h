#pragma once

struct tray_menu
{
    char *text;
    int disabled;
    int checked;
    void (*callback)(const void *ptr);
};

struct tray
{
    const char *tip;
    const char *icon_path;
    struct tray_menu *menu;
};

#ifdef __cplusplus
extern "C"
{
#endif

    // 窗口显示
    int window_show(const void *ptr);

    // 窗口隐藏
    int window_hide(const void *ptr);

    // 创建托盘
    int window_create_tray(const void *ptr, const struct tray *tray);

    // 销毁托盘
    int window_destroy_tray(const void *ptr);

#ifdef __cplusplus
}
#endif
