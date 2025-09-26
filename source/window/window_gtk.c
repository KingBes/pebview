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
struct _TrayData {
    GtkStatusIcon *status_icon;
    GtkWidget *menu;
    const void *window_ptr;
};
typedef struct _TrayData TrayData;

// 菜单项回调函数
static void menu_item_callback(GtkWidget *widget, gpointer data)
{
    struct tray_menu *menu = (struct tray_menu *)data;
    if (menu->callback && !menu->disabled) {
        menu->callback(menu);
    }
}

// 创建窗口托盘
void *window_tray(const void *ptr, const char *icon)
{
    if (!ptr || !icon) return NULL;
    
    TrayData *tray_data = malloc(sizeof(TrayData));
    if (!tray_data) return NULL;
    
    tray_data->window_ptr = ptr;
    
    // 创建状态图标
    tray_data->status_icon = gtk_status_icon_new_from_file(icon);
    if (!tray_data->status_icon) {
        free(tray_data);
        return NULL;
    }
    
    // 创建菜单
    tray_data->menu = gtk_menu_new();
    
    // 设置图标可见
    gtk_status_icon_set_visible(tray_data->status_icon, TRUE);
    
    return tray_data;
}

// 添加托盘菜单
void window_tray_add_menu(const void *tray, struct tray_menu *menu)
{
    if (!tray || !menu) return;
    
    TrayData *tray_data = (TrayData *)tray;
    GtkWidget *menu_item = gtk_menu_item_new_with_label(menu->text);
    
    // 设置菜单项状态
    gtk_widget_set_sensitive(menu_item, !menu->disabled);
    
    if (menu->checked) {
        GtkWidget *check_item = gtk_check_menu_item_new_with_label(menu->text);
        gtk_check_menu_item_set_active(GTK_CHECK_MENU_ITEM(check_item), TRUE);
        menu_item = check_item;
    }
    
    // 连接信号
    g_signal_connect(menu_item, "activate", 
                    G_CALLBACK(menu_item_callback), menu);
    
    // 添加到菜单
    gtk_menu_shell_append(GTK_MENU_SHELL(tray_data->menu), menu_item);
    gtk_widget_show(menu_item);
    
    // 设置菜单到状态图标
    gtk_status_icon_set_menu(tray_data->status_icon, GTK_MENU(tray_data->menu));
}

// 移除托盘菜单
void window_tray_remove(void *tray)
{
    if (!tray) return;
    
    TrayData *tray_data = (TrayData *)tray;
    
    if (tray_data->status_icon) {
        g_object_unref(tray_data->status_icon);
    }
    
    if (tray_data->menu) {
        gtk_widget_destroy(tray_data->menu);
    }
    
    free(tray_data);
}