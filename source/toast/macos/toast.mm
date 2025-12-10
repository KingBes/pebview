#include "../toast.h"
#include <Foundation/Foundation.h>
#include <AppKit/AppKit.h>

bool toastShow(
    const char *app,
    const char *title,
    const char *message,
    const char *image_path)
{
    @autoreleasepool
    {
        // 将 C 字符串转换为 NSString
        NSString *nsApp = [NSString stringWithUTF8String:app ? app : ""];
        NSString *nsTitle = [NSString stringWithUTF8String:title ? title : ""];
        NSString *nsMessage = [NSString stringWithUTF8String:message ? message : ""];
        NSString *nsImagePath = image_path ? [NSString stringWithUTF8String:image_path] : nil;

        // 创建通知对象
        NSUserNotification *notification = [[NSUserNotification alloc] init];
        notification.title = nsTitle;
        notification.informativeText = nsMessage;
        notification.soundName = NSUserNotificationDefaultSoundName;

        // 设置应用名称
        if (nsApp.length > 0)
        {
            notification.subtitle = nsApp;
        }

        // 设置通知图标
        if (nsImagePath && nsImagePath.length > 0)
        {
            NSURL *imageURL = [NSURL fileURLWithPath:nsImagePath];
            NSImage *image = [[NSImage alloc] initWithContentsOfURL:imageURL];
            if (image)
            {
                // 调整图标大小
                [image setSize:NSMakeSize(64, 64)];
                notification.contentImage = image;
            }
        }

        // 发送通知
        NSUserNotificationCenter *center = [NSUserNotificationCenter defaultUserNotificationCenter];
        [center deliverNotification:notification];

        // 清理资源
        [notification release];

        return true;
    }
}