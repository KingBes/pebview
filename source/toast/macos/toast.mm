#include "../toast.h"
#import <Foundation/Foundation.h>
#import <UserNotifications/UserNotifications.h>

// 辅助函数：C字符串转NSString（安全处理空指针，UTF-8编码）
static NSString* cstr_to_nsstr(const char* cstr) {
    if (!cstr || strlen(cstr) == 0) {
        return @"";
    }
    return [NSString stringWithUTF8String:cstr];
}

// 新版macOS 10.14+ 通知实现
bool toastShow(const char* app, const char* title, const char* message, const char* image_path) {
    // 1. 基础参数校验（app/title/message为必传）
    if (!app || strlen(app) == 0 || !title || strlen(title) == 0 || !message || strlen(message) == 0) {
        NSLog(@"[Toast] 错误：app/title/message 不能为空");
        return false;
    }

    // 2. 转换C字符串到NSString
    NSString* nsAppId = cstr_to_nsstr(app);
    NSString* nsTitle = cstr_to_nsstr(title);
    NSString* nsContent = cstr_to_nsstr(message);
    NSString* nsImagePath = cstr_to_nsstr(image_path);

    // 3. 信号量：将异步操作同步化，避免函数提前返回
    dispatch_semaphore_t sema = dispatch_semaphore_create(0);
    __block bool sendSuccess = false;

    // 4. 获取系统通知中心实例
    UNUserNotificationCenter* notificationCenter = [UNUserNotificationCenter currentNotificationCenter];

    // 5. 请求通知权限（Alert=弹窗显示，Sound=提示音）
    [notificationCenter requestAuthorizationWithOptions:(UNAuthorizationOptionAlert | UNAuthorizationOptionSound)
                                  completionHandler:^(BOOL granted, NSError * _Nullable error) {
        if (error) {
            NSLog(@"[Toast] 权限请求失败：%@", error.localizedDescription);
            dispatch_semaphore_signal(sema);
            return;
        }

        // 权限被拒绝直接返回失败
        if (!granted) {
            NSLog(@"[Toast] 失败：用户未授予通知权限（需在系统设置>通知中开启）");
            dispatch_semaphore_signal(sema);
            return;
        }

        // 6. 构建通知内容
        UNMutableNotificationContent* notifyContent = [[UNMutableNotificationContent alloc] init];
        notifyContent.title = nsTitle;                // 通知标题
        notifyContent.body = nsContent;               // 通知正文
        notifyContent.sound = [UNNotificationSound defaultSound]; // 默认提示音
        notifyContent.threadIdentifier = nsAppId;     // 按App标识分组通知

        // 7. 处理图片附件（非空时）
        if (nsImagePath.length > 0) {
            NSURL* imageURL = [NSURL fileURLWithPath:nsImagePath];
            // 检查图片文件是否存在
            if (![[NSFileManager defaultManager] fileExistsAtPath:nsImagePath]) {
                NSLog(@"[Toast] 失败：图片文件不存在 → %@", nsImagePath);
                dispatch_semaphore_signal(sema);
                return;
            }
            // 创建通知附件（支持PNG/JPG/GIF等）
            NSError* attachError = nil;
            UNNotificationAttachment* imageAttach = [UNNotificationAttachment attachmentWithIdentifier:@"toast_image"
                                                                                                  URL:imageURL
                                                                                              options:nil
                                                                                                error:&attachError];
            if (attachError) {
                NSLog(@"[Toast] 失败：创建图片附件失败 → %@", attachError.localizedDescription);
                dispatch_semaphore_signal(sema);
                return;
            }
            notifyContent.attachments = @[imageAttach];
        }

        // 8. 创建通知请求（UUID作为唯一ID，避免重复）
        NSString* requestId = [NSString stringWithFormat:@"toast_%@", [NSUUID UUID].UUIDString];
        UNNotificationRequest* notifyRequest = [UNNotificationRequest requestWithIdentifier:requestId
                                                                                    content:notifyContent
                                                                                    trigger:nil]; // nil = 立即发送

        // 9. 提交通知请求
        [notificationCenter addNotificationRequest:notifyRequest withCompletionHandler:^(NSError * _Nullable error) {
            if (error) {
                NSLog(@"[Toast] 失败：发送通知 → %@", error.localizedDescription);
                sendSuccess = false;
            } else {
                NSLog(@"[Toast] 成功：通知已发送");
                sendSuccess = true;
            }
            dispatch_semaphore_signal(sema); // 释放信号量，函数可返回
        }];
    }];

    // 等待异步操作完成（超时5秒，防止永久阻塞）
    dispatch_semaphore_wait(sema, dispatch_time(DISPATCH_TIME_NOW, 5 * NSEC_PER_SEC));
    dispatch_release(sema); // 释放信号量资源

    return sendSuccess;
}