#include "window.h"

#include <gtk/gtk.h>
#include <stdlib.h>

// 窗口显示
int window_show(const void *ptr)
{
    if (!ptr)
    {
        return 0;
    }
    GtkWidget *window = (GtkWidget *)ptr;
    gtk_widget_show(window);
    return 1;
}

// 窗口隐藏
int window_hide(const void *ptr)
{
    if (!ptr)
    {
        return 0;
    }
    GtkWidget *window = (GtkWidget *)ptr;
    gtk_widget_hide(window);
    return 1;
}

// 定义托盘结构体
typedef struct
{
    GtkWidget *menu;
    GtkStatusIcon *status_icon;
    const void *window_ptr;
    GSList *menu_data_list;
} TrayData;

// 菜单项数据结构
typedef struct
{
    int id;
    char *text;
    int disabled;
    int checked;
    void (*callback)(const void *ptr);
} MenuItemData;

// 菜单项回调函数
static void menu_item_callback(GtkWidget *widget, gpointer data)
{
    MenuItemData *menu = (MenuItemData *)data;
    if (menu->callback && !menu->disabled)
    {
        menu->callback(menu);
    }
}

// 托盘图标左键点击回调
static void on_status_icon_activate(GtkStatusIcon *status_icon, gpointer user_data)
{
    TrayData *tray_data = (TrayData *)user_data;
    if (tray_data->menu)
    {
        // 获取当前事件时间
        guint32 activate_time = gtk_get_current_event_time();
        gtk_menu_popup(GTK_MENU(tray_data->menu), NULL, NULL,
                       gtk_status_icon_position_menu,
                       status_icon, 1, activate_time);
    }
}

// 托盘图标点击回调
static gboolean on_status_icon_popup_menu(GtkStatusIcon *status_icon,
                                          guint button,
                                          guint activate_time,
                                          gpointer user_data)
{
    TrayData *tray_data = (TrayData *)user_data;
    if (tray_data->menu)
    {
        gtk_menu_popup(GTK_MENU(tray_data->menu), NULL, NULL,
                       gtk_status_icon_position_menu,
                       status_icon, button, activate_time);
        return TRUE;
    }
    return FALSE;
}

// 创建窗口托盘
void *window_tray(const void *ptr, const char *icon)
{
    if (!ptr || !icon)
        return NULL;

    TrayData *tray_data = malloc(sizeof(TrayData));
    if (!tray_data)
        return NULL;

    tray_data->window_ptr = ptr;
    tray_data->menu_data_list = NULL;

    // 创建状态图标
    tray_data->status_icon = gtk_status_icon_new_from_file(icon);
    if (!tray_data->status_icon)
    {
        free(tray_data);
        return NULL;
    }

    // 创建菜单
    tray_data->menu = gtk_menu_new();

    // 连接点击信号
    g_signal_connect(tray_data->status_icon, "popup-menu",
                     G_CALLBACK(on_status_icon_popup_menu), tray_data);
    // 连接左键点击信号
    g_signal_connect(tray_data->status_icon, "activate",
                     G_CALLBACK(on_status_icon_activate), tray_data);
    // 设置图标可见
    gtk_status_icon_set_visible(tray_data->status_icon, TRUE);

    return tray_data;
}

// 添加托盘菜单
void window_tray_add_menu(const void *tray, struct tray_menu *menu)
{
    if (!tray || !menu)
        return;

    TrayData *tray_data = (TrayData *)tray;
    GtkWidget *menu_item;

    // 为菜单项创建独立的数据副本
    MenuItemData *menu_data = malloc(sizeof(MenuItemData));
    menu_data->id = menu->id;
    menu_data->text = strdup(menu->text);
    menu_data->disabled = menu->disabled;
    menu_data->checked = menu->checked;
    menu_data->callback = menu->callback;

    // 将菜单数据添加到列表以便后续释放
    tray_data->menu_data_list = g_slist_append(tray_data->menu_data_list, menu_data);

    if (menu->checked)
    {
        menu_item = gtk_check_menu_item_new_with_label(menu_data->text);
        gtk_check_menu_item_set_active(GTK_CHECK_MENU_ITEM(menu_item), TRUE);
    }
    else
    {
        menu_item = gtk_menu_item_new_with_label(menu_data->text);
    }

    // 设置菜单项状态
    gtk_widget_set_sensitive(menu_item, !menu_data->disabled);

    // 连接信号，使用独立的数据副本
    g_signal_connect(menu_item, "activate",
                     G_CALLBACK(menu_item_callback), menu_data);

    // 添加到菜单
    gtk_menu_shell_append(GTK_MENU_SHELL(tray_data->menu), menu_item);
    gtk_widget_show(menu_item);
}

// 移除托盘菜单
void window_tray_remove(void *tray)
{
    if (!tray)
        return;

    TrayData *tray_data = (TrayData *)tray;

    if (tray_data->status_icon)
    {
        g_object_unref(tray_data->status_icon);
    }

    if (tray_data->menu)
    {
        gtk_widget_destroy(tray_data->menu);
    }

    free(tray_data);
}

// ---------------------------------------------------------------------------
// 标题栏外观
// ---------------------------------------------------------------------------

// 0xRRGGBB -> "#RRGGBB"（调用方负责 g_free）
static gchar *pebview_hex(int rgb)
{
    return g_strdup_printf("#%02X%02X%02X",
                           (rgb >> 16) & 0xFF, (rgb >> 8) & 0xFF, rgb & 0xFF);
}

int window_set_titlebar_theme(const void *ptr, int mode, int caption, int text)
{
    if (!ptr)
    {
        return 1; // WINDOW_NOT_FOUND
    }

    // GTK3 的窗口装饰由窗口管理器绘制，GTK 无从控制其颜色。想在 GTK 里改标题栏
    // 外观，唯一可行的是切到 CSD（客户端装饰）：换成 GtkHeaderBar 再用 CSS 上色。
    // 注意这里换的只是"外观"—— 标题栏的布局、标题文字、按钮仍是系统那套；
    // 真正自绘标题栏（自己画高度/按钮/拖拽区）需要另走无边框方案。
    // 因此这里只在调用方显式给了颜色时才走这条路 —— 只给 mode 的话，在 GTK 上
    // 「只改标题栏深浅」无法表达（改主题是全局的），如实返回不支持。
    if (caption < 0 && text < 0)
    {
        return 3; // OS_UNSUPPORTED
    }

    GtkWidget *window = (GtkWidget *)ptr;
    if (!GTK_IS_WINDOW(window))
    {
        return 1;
    }

    // 深色模式且没指定文字色时，给一个可读的默认值
    int fg = (text >= 0) ? text : ((mode == 1) ? 0xFFFFFF : 0x000000);

    GtkWidget *header = gtk_window_get_titlebar(GTK_WINDOW(window));
    if (!header)
    {
        header = gtk_header_bar_new();
        gtk_header_bar_set_show_close_button(GTK_HEADER_BAR(header), TRUE);
        gtk_window_set_titlebar(GTK_WINDOW(window), header);
        gtk_widget_show_all(header);
    }

    gchar *fg_hex = pebview_hex(fg);
    gchar *css;
    if (caption >= 0)
    {
        gchar *bg_hex = pebview_hex(caption);
        css = g_strdup_printf("headerbar { background-image: none; background-color: %s; }"
                              "headerbar .title, headerbar label { color: %s; }",
                              bg_hex, fg_hex);
        g_free(bg_hex);
    }
    else
    {
        css = g_strdup_printf("headerbar .title, headerbar label { color: %s; }", fg_hex);
    }

    GtkCssProvider *provider = gtk_css_provider_new();
    gtk_css_provider_load_from_data(provider, css, -1, NULL);
    // 挂在标题栏自己的 style context 上，避免影响同进程的其它窗口
    gtk_style_context_add_provider(gtk_widget_get_style_context(header),
                                   GTK_STYLE_PROVIDER(provider),
                                   GTK_STYLE_PROVIDER_PRIORITY_APPLICATION);
    g_object_unref(provider);

    g_free(fg_hex);
    g_free(css);

    return 0; // OK
}

// ---------------------------------------------------------------------------
// 自定义标题栏（真正无边框 + JS 驱动缩放）
// ---------------------------------------------------------------------------
//
// GTK3 上没有 macOS 那种"标题栏透明化"的等价物，只能走 gtk_window_set_decorated(FALSE)：
// 让窗口管理器不再绘制装饰。代价是拖动、边缘调整大小、双击最大化这些原本由 WM 提供的
// 行为**全都要自己补**。
//
// 而 GTK 层拿不到鼠标事件 —— webview 的 GtkWidget 会把鼠标事件吃掉，事件不会冒泡到窗口，
// 所以拖动只能由 JS 在 mousedown 时调 window_begin_move_drag() 显式发起（内部走
// gdk_window_begin_move_drag，Wayland 下映射到 xdg_toplevel.move）。
// 这一点与 Windows / macOS 完全不同，文档里要如实写明。

typedef struct
{
    GtkWidget *window;
    void (*callback)(const void *ptr, int state);
    int last_maximized;
} PebViewGtkState;

static gboolean pebview_on_window_state(GtkWidget *widget,
                                        GdkEventWindowState *event,
                                        gpointer data)
{
    PebViewGtkState *st = (PebViewGtkState *)data;
    if (!st || !st->callback)
    {
        return FALSE;
    }

    int maximized = (event->new_window_state & GDK_WINDOW_STATE_MAXIMIZED) ? 1 : 0;
    if (maximized != st->last_maximized)
    {
        st->last_maximized = maximized;
        st->callback(st->window, maximized);
    }
    return FALSE;
}

int window_set_titlebar_geometry(const void *ptr, int height, int controls_width, int drag)
{
    if (!ptr)
    {
        return 1; // WINDOW_NOT_FOUND
    }

    GtkWidget *widget = (GtkWidget *)ptr;
    if (!GTK_IS_WINDOW(widget))
    {
        return 1;
    }

    if (height <= 0)
    {
        // 关闭：把装饰还给窗口管理器
        gtk_window_set_decorated(GTK_WINDOW(widget), TRUE);
        return 0;
    }

    gtk_window_set_decorated(GTK_WINDOW(widget), FALSE);

    (void)controls_width; // 拖动由 JS 显式发起，不依赖区域划分
    (void)drag;

    return 0;
}

int window_minimize(const void *ptr)
{
    if (!ptr)
    {
        return 1;
    }
    gtk_window_iconify(GTK_WINDOW(ptr));
    return 0;
}

int window_toggle_maximize(const void *ptr)
{
    if (!ptr)
    {
        return 0;
    }

    GtkWindow *window = GTK_WINDOW(ptr);
    if (gtk_window_is_maximized(window))
    {
        gtk_window_unmaximize(window);
    }
    else
    {
        gtk_window_maximize(window);
    }
    return gtk_window_is_maximized(window) ? 1 : 0;
}

int window_is_maximized(const void *ptr)
{
    if (!ptr)
    {
        return 0;
    }
    return gtk_window_is_maximized(GTK_WINDOW(ptr)) ? 1 : 0;
}

int window_set_state_callback(const void *ptr, void (*cb)(const void *ptr, int state))
{
    if (!ptr || !cb)
    {
        return 1;
    }

    GtkWidget *widget = (GtkWidget *)ptr;
    if (!GTK_IS_WINDOW(widget))
    {
        return 1;
    }

    PebViewGtkState *st = (PebViewGtkState *)g_object_get_data(G_OBJECT(widget),
                                                               "pebview-state");
    if (!st)
    {
        st = g_new0(PebViewGtkState, 1);
        st->window = widget;
        st->last_maximized = gtk_window_is_maximized(GTK_WINDOW(widget)) ? 1 : 0;
        g_object_set_data_full(G_OBJECT(widget), "pebview-state", st, g_free);
        g_signal_connect(widget, "window-state-event",
                         G_CALLBACK(pebview_on_window_state), st);
    }

    st->callback = cb;
    return 0;
}

int window_begin_move_drag(const void *ptr, int x, int y)
{
    if (!ptr)
    {
        return 1;
    }

    GtkWidget *widget = (GtkWidget *)ptr;
    if (!GTK_IS_WINDOW(widget))
    {
        return 1;
    }

    GdkWindow *gdkwin = gtk_widget_get_window(widget);
    if (!gdkwin)
    {
        return 3;
    }

    // 传进来的是屏幕坐标；GTK 要的是相对该 GdkWindow 的坐标，先换算一下。
    // 按钮固定用 1（左键）—— 调用方是在 mousedown 里发起的。
    int origin_x = 0;
    int origin_y = 0;
    gdk_window_get_origin(gdkwin, &origin_x, &origin_y);
    gdk_window_begin_move_drag(gdkwin, 1, x - origin_x, y - origin_y, GDK_CURRENT_TIME);
    return 0;
}

// 从外部发起窗口缩放（edge 为 WMSZ_* 编码：1=LEFT 2=RIGHT 3=TOP 4=TOPLEFT
// 5=TOPRIGHT 6=BOTTOM 7=BOTTOMLEFT 8=BOTTOMRIGHT）。
//
// 这一扇窗通常是 WebView2 宿主，子窗口铺满客户区、吃掉了鼠标，GTK 在内容区
// 收不到边缘的 mouse 事件，所以缩放由 JS 在 mousedown 里判定边缘、把 edge 传进来，
// 这里转调 gdk_window_begin_resize_drag。三平台同一句 JS，页面侧不按平台分支。
// 只在左键真按着时发起；左键已松开由调用方（begin_move_drag 同款逻辑）保证。
int window_begin_resize_drag(const void *ptr, int edge)
{
    if (!ptr)
    {
        return 1;
    }

    GtkWidget *widget = (GtkWidget *)ptr;
    if (!GTK_IS_WINDOW(widget))
    {
        return 1;
    }

    GdkWindow *gdkwin = gtk_widget_get_window(widget);
    if (!gdkwin)
    {
        return 3;
    }

    // edge -> GdkWindowEdge：NORTH_WEST=0 NORTH=1 NORTH_EAST=2 WEST=3 EAST=4 SOUTH_WEST=5 SOUTH=6 SOUTH_EAST=7
    GdkWindowEdge gdk_edge;
    switch (edge)
    {
        case 1: gdk_edge = GDK_WINDOW_EDGE_WEST; break;
        case 2: gdk_edge = GDK_WINDOW_EDGE_EAST; break;
        case 3: gdk_edge = GDK_WINDOW_EDGE_NORTH; break;
        case 4: gdk_edge = GDK_WINDOW_EDGE_NORTH_WEST; break;
        case 5: gdk_edge = GDK_WINDOW_EDGE_NORTH_EAST; break;
        case 6: gdk_edge = GDK_WINDOW_EDGE_SOUTH; break;
        case 7: gdk_edge = GDK_WINDOW_EDGE_SOUTH_WEST; break;
        case 8: gdk_edge = GDK_WINDOW_EDGE_SOUTH_EAST; break;
        default: return 1;
    }

    // 取当前光标位置（相对 GdkWindow），换算成屏幕坐标传给 gdk 作为抓取点
    int wx = 0, wy = 0;
    gdk_window_get_pointer(gdkwin, &wx, &wy, NULL);
    int origin_x = 0, origin_y = 0;
    gdk_window_get_origin(gdkwin, &origin_x, &origin_y);
    gdk_window_begin_resize_drag(gdkwin, gdk_edge, 1, origin_x + wx, origin_y + wy, GDK_CURRENT_TIME);
    return 0;
}