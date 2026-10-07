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
     * @brief 窗口透明背景（窗口层）
     *
     * enable=1 让窗口进入逐像素合成（配合页面 CSS 背景透明可透出桌面），
     * enable=0 关闭、回到不透明窗口。
     *
     * @param ptr    窗口句柄
     * @param enable 1 = 开启透明，0 = 关闭
     * @return int   0 = OK，1 = 窗口不存在，
     *               3 = 当前平台不支持（Linux 无合成器，或窗口已 realize ——
     *                   GTK 的 visual 只能在 realize 前设置，须在 run() 之前调用）
     */
    int window_set_transparent(const void *ptr, int enable);

    /**
     * @brief 窗口置顶
     *
     * @param ptr    窗口句柄
     * @param enable 1 = 进入置顶层，0 = 回到普通 Z 层
     * @return int   0 = OK，1 = 窗口不存在（不改位置/大小/激活态）
     */
    int window_set_always_on_top(const void *ptr, int enable);

    /**
     * @brief 窗口定位：把窗口左上角移动到屏幕坐标 (x, y)
     *
     * macOS 内部按主屏坐标系换算 y（多显示器副屏可能偏差）。
     *
     * @param ptr 窗口句柄
     * @param x   屏幕坐标 X（窗口左上角）
     * @param y   屏幕坐标 Y（窗口左上角）
     * @return int 0 = OK，1 = 窗口不存在
     */
    int window_set_position(const void *ptr, int x, int y);

    /**
     * @brief 整窗点击穿透：开启后鼠标事件落到下层窗口
     *
     * 只翻转 WS_EX_TRANSPARENT，不影响窗口透明的 layered 状态位（两者独立开关）。
     * Linux 需窗口 realize（run() 显示）之后调用。
     *
     * @param ptr    窗口句柄
     * @param enable 1 = 开启穿透，0 = 关闭
     * @return int   0 = OK，1 = 窗口不存在，3 = 平台或时机不支持
     */
    int window_set_click_through(const void *ptr, int enable);

    /**
     * @brief 区域白名单点击穿透：rects 内正常接收鼠标，rects 外穿透
     *
     * rects 为扁平 int 数组 [x0,y0,w0,h0, ...]（count 个矩形，长度 = count*4），
     * 坐标是页面 CSS px，各平台内部自行换算 DPI / 缩放。count<=0 退出区域模式、
     * 恢复正常交互（rects 可为 NULL）。与 window_set_click_through 互斥覆盖。
     *
     * @param ptr   窗口句柄
     * @param rects 扁平矩形数组，可为 NULL（当 count<=0）
     * @param count 矩形个数
     * @return int  0 = OK，1 = 窗口不存在，3 = 平台或时机不支持（Linux 需 realize），4 = 参数非法
     */
    int window_set_click_through_regions(const void *ptr, const int *rects, int count);

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
