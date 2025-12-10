#include "../toast.h"
#include <libnotify/notify.h>
#include <string.h> // 用于strlen、strncmp
#include <stdio.h>  // 用于打印错误
#include <stdbool.h>

// 全局标记：是否已初始化libnotify
static bool notify_inited = false;

bool toastShow(const char *app,
               const char *title,
               const char *message,
               const char *image_path)
{
    // 1. 参数合法性检查
    if (title == NULL || message == NULL)
    {
        fprintf(stderr, "错误：title或message不能为空！\n");
        return false;
    }

    // 2. 初始化libnotify（仅首次调用）
    if (!notify_inited)
    {
        if (!notify_init("GTK_Toast_Notification"))
        {
            fprintf(stderr, "错误：libnotify初始化失败！\n");
            return false;
        }
        notify_inited = true;
    }

    // 3. 处理默认参数
    const char *icon = image_path ? image_path : "dialog-info"; // 默认信息图标
    const char *actual_content = (strlen(message) > 0) ? message : "无通知内容";
    const char *actual_title = (strlen(title) > 0) ? title : "提示";

    // 4. 创建Toast通知（Toast风格：短超时、低打扰）
    NotifyNotification *notify = notify_notification_new(
        actual_title,
        actual_content,
        icon);
    if (notify == NULL)
    {
        fprintf(stderr, "错误：创建通知对象失败！\n");
        return false;
    }

    // 5. 设置Toast特性：3秒超时（符合Toast短提示风格）、普通紧急度
    notify_notification_set_timeout(notify, 3000); // 3000ms=3秒
    notify_notification_set_urgency(notify, NOTIFY_URGENCY_LOW);

    // 6. 显示通知并处理错误
    GError *error = NULL;
    bool success = notify_notification_show(notify, &error);
    if (!success)
    {
        fprintf(stderr, "错误：显示通知失败：%s\n", error->message);
        g_error_free(error);
    }

    // 7. 释放通知资源（libnotify内部保留显示状态，不影响通知展示）
    g_object_unref(notify);

    return success;
}