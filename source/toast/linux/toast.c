#include "../toast.h"
#include <libnotify/notify.h>
#include <string.h> // 用于strlen、strncmp
#include <stdio.h>  // 用于打印错误
#include <stdbool.h>

bool toastShow(const char *app,
               const char *title,
               const char *message,
               const char *image_path)
{
    // 初始化 libnotify（只需执行一次）
    static gboolean initialized = FALSE;
    if (!initialized)
    {
        if (!notify_init(app))
        {
            fprintf(stderr, "Failed to initialize libnotify\n");
            return false;
        }
        initialized = TRUE;
    }

    // 创建通知对象
    NotifyNotification *notification = notify_notification_new(
        title,
        message,
        (image_path && *image_path) ? image_path : NULL // 空路径时使用默认图标
    );

    if (!notification)
    {
        fprintf(stderr, "错误：libnotify创建通知失败！\n");
        return false;
    }

    // 设置通知属性
    notify_notification_set_timeout(notification, 3000); // 3秒后自动关闭
    notify_notification_set_urgency(notification, NOTIFY_URGENCY_NORMAL);

    // 显示通知
    GError *error = NULL;
    if (!notify_notification_show(notification, &error))
    {
        fprintf(stderr, "错误：libnotify显示通知失败！\n");
        g_error_free(error);
        g_object_unref(notification);
        return false;
    }

    // 清理资源
    g_object_unref(notification);
    return true;
}