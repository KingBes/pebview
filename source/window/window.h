#pragma once

struct tray_menu
{
    int id;
    char *text;
    int disabled;
    int checked;
    void (*callback)(const void *ptr);
};

#ifdef __cplusplus
extern "C"
{
#endif

    // 窗口显示
    int window_show(const void *ptr);

    // 窗口隐藏
    int window_hide(const void *ptr);

    /**
     * @brief 创建窗口托盘
     * 
     * @param ptr 窗口句柄
     * @param icon 托盘图标路径
     * @param title 托盘标题
     * @return void* 托盘句柄
     */
    void *window_tray(const void *ptr, const char *icon);

    /**
     * @brief 添加托盘菜单
     * 
     * @param tray 托盘句柄
     * @param menu 菜单配置
     */
    void window_tray_add_menu(const void *tray, struct tray_menu *menu);

    /**
     * @brief 移除托盘菜单
     * 
     * @param tray 托盘句柄
     */
    void window_tray_remove(void *tray);
    
#ifdef __cplusplus
}
#endif
