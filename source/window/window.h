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

    /**
     * @brief 设置窗口标题栏外观（浅色 / 深色与配色）
     *
     * @param ptr     窗口句柄
     * @param mode    -1 = 跟随系统，0 = 浅色，1 = 深色
     * @param caption 标题栏底色 0xRRGGBB，传 -1 表示不覆盖（用系统默认）
     * @param text    标题栏文字色 0xRRGGBB，传 -1 表示不覆盖（用系统默认）
     * @return int    0 = OK，1 = 窗口不存在，3 = 当前平台不支持该配置
     *
     * 各平台支持范围不同，调用方需按返回值判断：
     *   Windows  mode + caption + text 全支持（配色需要 Win11，Win10 只认浅/深色）
     *   macOS    只支持 mode（NSAppearance）；标题栏由系统绘制，无配色接口
     *   Linux    只支持 caption / text（切换为 CSD 自绘标题栏）；只给 mode 时返回 3
     */
    int window_set_titlebar_theme(const void *ptr, int mode, int caption, int text);

    /**
     * @brief 自定义标题栏（真正无边框 + JS 驱动缩放）
     *
     * height > 0 时进入自定义标题栏模式：顶部 height 像素交给系统当标题栏处理
     * （拖动、双击最大化），height = 0 关闭、退回系统标题栏。
     * controls_width 是右侧按钮区宽度，该区域不响应拖动，让 HTML 按钮能收到点击。
     *
     * @return int 0 = OK，1 = 窗口不存在，3 = 当前平台不支持
     */
    int window_set_titlebar_geometry(const void *ptr, int height, int controls_width, int drag);

    // 最小化窗口
    int window_minimize(const void *ptr);

    // 最大化 / 还原（切换），返回切换后的状态：1 = 最大化
    int window_toggle_maximize(const void *ptr);

    // 是否处于最大化，1 = 是
    int window_is_maximized(const void *ptr);

    /**
     * @brief 注册窗口状态变化回调
     *
     * 最大化 / 还原时触发（最小化不便在回调里可靠区分，暂不暴露）。
     *
     * @param cb 回调，state 为 0（普通）或 1（最大化）
     * @return int 0 = OK，1 = 窗口不存在，3 = 当前平台不支持
     */
    int window_set_state_callback(const void *ptr, void (*cb)(const void *ptr, int state));

    /**
     * @brief 从外部发起窗口拖动（x / y 为屏幕坐标）
     *
     * 三平台统一：JS 在标题栏 mousedown 里把 event.screenX / screenY 传进来即可，
     * 页面侧不需要按平台分支。各平台内部各自用最合适的方式发起拖动：
     *   - Windows  合成 WM_NCLBUTTONDOWN(HTCAPTION)，走系统模态移动循环
     *              （贴边、双击最大化等原生行为都在）
     *   - Linux    gdk_window_begin_move_drag
     *   - macOS    按光标位移搬窗口
     * 只在左键真正按着时同步发起一次，不阻塞到拖动结束；左键已松开则直接返回 4。
     */
    int window_begin_move_drag(const void *ptr, int x, int y);

    /**
     * @brief 从外部发起窗口缩放（edge 为 WMSZ_* 编码：1=LEFT 2=RIGHT 3=TOP 4=TOPLEFT
     *        5=TOPRIGHT 6=BOTTOM 7=BOTTOMLEFT 8=BOTTOMRIGHT）
     *
     * 三平台统一：JS 在 mousedown 里判定边缘、把 edge 传进来即可，页面侧不需要按平台分支。
     * 各平台内部各自用最合适的方式发起缩放：
     *   - Windows  发 WM_SYSCOMMAND(SC_SIZE | edge)，进系统模态缩放循环
     *   - Linux    gdk_window_begin_resize_drag
     *   - macOS    按光标位移改窗口 frame
     * 只在左键真正按着时同步发起一次，不阻塞到缩放结束；左键已松开则直接返回 4。
     */
    int window_begin_resize_drag(const void *ptr, int edge);

#ifdef __cplusplus
}
#endif
