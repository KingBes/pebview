#include "window.h"

#include <gtk/gtk.h>

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
