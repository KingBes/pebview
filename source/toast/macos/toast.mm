#include "../toast.h"
#import <Foundation/Foundation.h>
#import <UserNotifications/UserNotifications.h>
#import <AppKit/AppKit.h> 

// 辅助函数：C字符串转NSString（处理空指针）
static NSString* cstr_to_nsstr(const char* cstr) {
    if (!cstr || strlen(cstr) == 0) {
        return @"";
    }
    return [NSString stringWithUTF8String:cstr];
}

#if MAC_OS_X_VERSION_MAX_ALLOWED >= MAC_OS_X_VERSION_10_14
// ========== 新版 macOS 10.14+ 实现（UNUserNotificationCenter） ==========
bool toastShow(const char* app, const char* title, const char* message, const char* image_path) {
    // 1. 基础参数校验
    if (!app || !title || !message) {
        NSLog(@"[Toast] 无效参数：app/title/message 不能为空");
        return false;
    }

    // 2. C字符串转NSString（UTF-8编码）
    NSString* nsApp = cstr_to_nsstr(app);
    NSString* nsTitle = cstr_to_nsstr(title);
    NSString* nsContent = cstr_to_nsstr(message);
    NSString* nsImagePath = cstr_to_nsstr(image_path);

    // 3. 信号量：异步操作同步化（避免函数提前返回）
    dispatch_semaphore_t sema = dispatch_semaphore_create(0);
    __block bool sendSuccess = false;

    // 4. 获取通知中心实例
    UNUserNotificationCenter* center = [UNUserNotificationCenter currentNotificationCenter];

    // 5. 请求通知权限（Alert + Sound）
    [center requestAuthorizationWithOptions:(UNAuthorizationOptionAlert | UNAuthorizationOptionSound)
                          completionHandler:^(BOOL granted, NSError * _Nullable error) {
        if (error) {
            NSLog(@"[Toast] 权限请求失败：%@", error.localizedDescription);
            dispatch_semaphore_signal(sema);
            return;
        }
        if (!granted) {
            NSLog(@"[Toast] 用户拒绝通知权限");
            dispatch_semaphore_signal(sema);
            return;
        }

        // 6. 构建通知内容
        UNMutableNotificationContent* notificationContent = [[UNMutableNotificationContent alloc] init];
        notificationContent.title = nsTitle;          // 标题
        notificationContent.body = nsContent;         // 正文
        notificationContent.sound = [UNNotificationSound defaultSound]; // 默认提示音
        notificationContent.threadIdentifier = nsApp; // 按app标识分组通知

        // 7. 处理图片附件（非空时）
        if (nsImagePath.length > 0) {
            NSURL* imageURL = [NSURL fileURLWithPath:nsImagePath];
            // 检查图片文件是否存在
            if (![[NSFileManager defaultManager] fileExistsAtPath:nsImagePath]) {
                NSLog(@"[Toast] 图片文件不存在：%@", nsImagePath);
                dispatch_semaphore_signal(sema);
                return;
            }
            // 创建通知附件
            NSError* attachError = nil;
            UNNotificationAttachment* attachment = [UNNotificationAttachment attachmentWithIdentifier:@"toast_image"
                                                                                              URL:imageURL
                                                                                          options:nil
                                                                                            error:&attachError];
            if (attachError) {
                NSLog(@"[Toast] 创建图片附件失败：%@", attachError.localizedDescription);
                dispatch_semaphore_signal(sema);
                return;
            }
            notificationContent.attachments = @[attachment];
        }

        // 8. 创建通知请求（立即触发）
        NSString* requestID = [NSString stringWithFormat:@"toast_%@", [NSUUID UUID].UUIDString];
        UNNotificationRequest* request = [UNNotificationRequest requestWithIdentifier:requestID
                                                                              content:notificationContent
                                                                              trigger:nil]; // 无触发器=立即发送

        // 9. 发送通知
        [center addNotificationRequest:request withCompletionHandler:^(NSError * _Nullable error) {
            if (error) {
                NSLog(@"[Toast] 发送通知失败：%@", error.localizedDescription);
                sendSuccess = false;
            } else {
                NSLog(@"[Toast] 通知发送成功");
                sendSuccess = true;
            }
            dispatch_semaphore_signal(sema); // 释放信号量
        }];
    }];

    // 10. 等待异步操作完成（超时5秒）
    dispatch_semaphore_wait(sema, dispatch_time(DISPATCH_TIME_NOW, 5 * NSEC_PER_SEC));
    dispatch_release(sema); // 释放信号量

    return sendSuccess;
}

#else
// ========== 旧版 macOS <10.14 实现（NSUserNotification） ==========
bool toastShow(const char* app, const char* title, const char* message, const char* image_path) {
    // 1. 参数校验与字符串转换
    if (!app || !title || !message) {
        NSLog(@"[Toast] 无效参数：app/title/message 不能为空");
        return false;
    }
    NSString* nsTitle = cstr_to_nsstr(title);
    NSString* nsContent = cstr_to_nsstr(message);
    NSString* nsImagePath = cstr_to_nsstr(image_path);

    // 2. 创建旧版通知实例
    NSUserNotification* notification = [[NSUserNotification alloc] init];
    notification.title = nsTitle;
    notification.informativeText = nsContent;
    notification.soundName = NSUserNotificationDefaultSoundName;

    // 3. 处理图片附件
    if (nsImagePath.length > 0) {
        NSImage* image = [[NSImage alloc] initWithContentsOfFile:nsImagePath];
        if (!image) {
            NSLog(@"[Toast] 图片加载失败：%@", nsImagePath);
            return false;
        }
        notification.contentImage = image;
    }

    // 4. 发送通知
    NSUserNotificationCenter* center = [NSUserNotificationCenter defaultUserNotificationCenter];
    [center deliverNotification:notification];
    
    NSLog(@"[Toast] 旧版通知发送成功");
    return true;
}
#endif