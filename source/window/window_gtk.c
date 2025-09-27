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
typedef struct {
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

// 托盘图标右键点击回调
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

// 托盘图片左键点击回调
static void on_status_icon_activate(GtkStatusIcon *status_icon,
                                    gpointer user_data)
{
    TrayData *tray_data = (TrayData *)user_data;
    if (tray_data->window_ptr)
    {
        window_show(tray_data->window_ptr);
    }
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

    // 连接右键点击信号
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