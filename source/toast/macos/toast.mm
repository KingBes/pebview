#include "../toast.h"
#import <Foundation/Foundation.h>
#import <UserNotifications/UserNotifications.h>
#include <AppKit/AppKit.h>

@interface NotificationDelegate : NSObject <UNUserNotificationCenterDelegate>
+ (instancetype)shared;
@end

@implementation NotificationDelegate

+ (instancetype)shared {
    static NotificationDelegate* instance = nil;
    static dispatch_once_t onceToken;
    dispatch_once(&onceToken, ^{
        instance = [[NotificationDelegate alloc] init];
    });
    return instance;
}

- (void)userNotificationCenter:(UNUserNotificationCenter *)center 
       willPresentNotification:(UNNotification *)notification 
         withCompletionHandler:(void (^)(UNNotificationPresentationOptions))completionHandler {
    completionHandler(UNNotificationPresentationOptionAlert | UNNotificationPresentationOptionSound);
}

@end

bool toastShow(const char* app, const char* title, const char* message, const char* image_path) {
    @autoreleasepool {
        // 获取通知中心
        UNUserNotificationCenter* center = [UNUserNotificationCenter currentNotificationCenter];
        center.delegate = [NotificationDelegate shared];
        
        // 请求权限（只请求一次）
        static bool permissionRequested = false;
        if (!permissionRequested) {
            [center requestAuthorizationWithOptions:(UNAuthorizationOptionAlert | UNAuthorizationOptionSound) 
                                  completionHandler:^(BOOL granted, NSError * _Nullable error) {
                if (!granted) {
                    NSLog(@"通知权限被拒绝");
                }
            }];
            permissionRequested = true;
        }
        
        // 创建通知内容
        UNMutableNotificationContent* content = [[UNMutableNotificationContent alloc] init];
        if (title) content.title = [NSString stringWithUTF8String:title];
        if (app) content.subtitle = [NSString stringWithUTF8String:app];
        if (message) content.body = [NSString stringWithUTF8String:message];
        content.sound = [UNNotificationSound defaultSound];
        
        // 添加图片附件
        if (image_path && image_path[0] != '\0') {
            NSString* path = [NSString stringWithUTF8String:image_path];
            NSURL* url = [NSURL fileURLWithPath:path];
            
            if ([[NSFileManager defaultManager] fileExistsAtPath:path]) {
                NSError* error;
                UNNotificationAttachment* attachment = [UNNotificationAttachment 
                    attachmentWithIdentifier:@"image" 
                    URL:url 
                    options:@{UNNotificationAttachmentOptionsTypeHintKey: @"public.image"} 
                    error:&error];
                
                if (attachment && !error) {
                    content.attachments = @[attachment];
                } else if (error) {
                    NSLog(@"图片附件错误: %@", error.localizedDescription);
                }
            } else {
                NSLog(@"图片不存在: %@", path);
            }
        }
        
        // 创建通知请求
        UNNotificationRequest* request = [UNNotificationRequest 
            requestWithIdentifier:[[NSUUID UUID] UUIDString]
            content:content
            trigger:[UNTimeIntervalNotificationTrigger triggerWithTimeInterval:0.1 repeats:NO]];
        
        // 发送通知
        __block bool success = false;
        dispatch_semaphore_t semaphore = dispatch_semaphore_create(0);
        
        [center addNotificationRequest:request withCompletionHandler:^(NSError * _Nullable error) {
            if (error) {
                NSLog(@"发送通知失败: %@", error.localizedDescription);
            } else {
                success = true;
            }
            dispatch_semaphore_signal(semaphore);
        }];
        
        dispatch_semaphore_wait(semaphore, DISPATCH_TIME_FOREVER);
        return success;
    }
}