#include "../toast.h"
#import <Foundation/Foundation.h>
#import <UserNotifications/UserNotifications.h>
#include <AppKit/AppKit.h>

// 使用旧版 API 确保兼容性
@interface NotificationCenter : NSObject
+ (instancetype)defaultUserNotificationCenter;
- (void)deliverNotification:(NSUserNotification *)notification;
@end

bool toastShow(const char* app, const char* title, const char* message, const char* image_path) {
    @autoreleasepool {
        // 创建通知对象
        NSUserNotification *notification = [[NSUserNotification alloc] init];
        notification.title = [NSString stringWithUTF8String:(title ? title : "")];
        notification.subtitle = [NSString stringWithUTF8String:(app ? app : "")];
        notification.informativeText = [NSString stringWithUTF8String:(message ? message : "")];
        notification.soundName = NSUserNotificationDefaultSoundName;
        
        // 尝试添加图片（可选）
        if (image_path && image_path[0] != '\0') {
            NSString *path = [NSString stringWithUTF8String:image_path];
            NSImage *image = [[NSImage alloc] initWithContentsOfFile:path];
            if (image) {
                // 注意：NSUserNotification 不直接支持图片附件
                // 这里只是演示，实际不会显示
                NSLog(@"图片加载成功，但NSUserNotification不支持显示图片");
            }
        }
        
        // 发送通知
        NSClassFromString(@"NSUserNotificationCenter");
        id center = [NSClassFromString(@"NSUserNotificationCenter") performSelector:@selector(defaultUserNotificationCenter)];
        [center performSelector:@selector(deliverNotification:) withObject:notification];
        
        return true;
    }
}