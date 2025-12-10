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
    // 初始化 libnotify (如果尚未初始化)
    if (!notify_is_initted() && !notify_init(app))
    {
        fprintf(stderr, "Failed to initialize libnotify\n");
        return false;
    }

    // 创建通知对象
    NotifyNotification *notification = notify_notification_new(
        title,
        message,
        (image_path && *image_path) ? image_path : NULL // 空路径时使用默认图标
    );
    if (!notification)
    {
        fprintf(stderr, "Failed to create notification\n");
        return false;
    }

    // 设置通知属性 (匹配 nm 输出中的符号)
    notify_notification_set_timeout(notification, 8000); // 3秒后消失
    notify_notification_set_urgency(notification, NOTIFY_URGENCY_NORMAL);

    // 显示通知
    GError *error = NULL;
    if (!notify_notification_show(notification, &error))
    {
        fprintf(stderr, "Failed to show notification: %s\n", error->message);
        g_error_free(error);
        g_object_unref(notification);
        return false;
    }

    // 清理资源
    g_object_unref(notification);
    return true;
}