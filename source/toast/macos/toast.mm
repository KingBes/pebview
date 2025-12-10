#include "../toast.h"
#include <Foundation/Foundation.h>
#include <AppKit/AppKit.h>

bool toastShow(
    const char *app,
    const char *title,
    const char *message,
    const char *image_path)
{
    @autoreleasepool {
        // 转换参数为 NSString
        NSString* nsApp = [NSString stringWithUTF8String:app ? app : ""];
        NSString* nsTitle = [NSString stringWithUTF8String:title ? title : ""];
        NSString* nsMessage = [NSString stringWithUTF8String:message ? message : ""];
        NSString* nsImagePath = image_path ? [NSString stringWithUTF8String:image_path] : nil;
        
        // 使用现代通知 API (macOS 10.14+)
        if (@available(macOS 10.14, *)) {
            // 获取通知中心
            UNUserNotificationCenter* center = [UNUserNotificationCenter currentNotificationCenter];
            
            // 请求通知权限
            dispatch_semaphore_t sema = dispatch_semaphore_create(0);
            __block BOOL permissionGranted = NO;
            
            [center requestAuthorizationWithOptions:(UNAuthorizationOptionAlert | UNAuthorizationOptionSound) 
                                  completionHandler:^(BOOL granted, NSError * _Nullable error) {
                permissionGranted = granted;
                if (error) {
                    NSLog(@"Notification authorization error: %@", error);
                }
                dispatch_semaphore_signal(sema);
            }];
            
            // 等待权限请求完成
            dispatch_semaphore_wait(sema, DISPATCH_TIME_FOREVER);
            
            if (!permissionGranted) {
                NSLog(@"Notification permission not granted");
                return false;
            }
            
            // 创建通知内容
            UNMutableNotificationContent* content = [[UNMutableNotificationContent alloc] init];
            content.title = nsTitle;
            content.body = nsMessage;
            content.sound = [UNNotificationSound defaultSound];
            
            // 添加应用名称
            if (nsApp.length > 0) {
                content.subtitle = nsApp;
            }
            
            // 添加图标 (macOS 11+)
            if (nsImagePath && nsImagePath.length > 0 && @available(macOS 11.0, *)) {
                NSURL* imageURL = [NSURL fileURLWithPath:nsImagePath];
                UNNotificationAttachment* attachment = [UNNotificationAttachment attachmentWithIdentifier:@"image" 
                                                                                                      URL:imageURL 
                                                                                                  options:nil 
                                                                                                    error:nil];
                if (attachment) {
                    content.attachments = @[attachment];
                }
            }
            
            // 创建通知请求 (立即触发)
            UNNotificationRequest* request = [UNNotificationRequest requestWithIdentifier:@"toast" 
                                                                                  content:content 
                                                                                  trigger:nil];
            
            // 发送通知
            __block BOOL success = YES;
            dispatch_semaphore_t sendSema = dispatch_semaphore_create(0);
            
            [center addNotificationRequest:request 
                     withCompletionHandler:^(NSError * _Nullable error) {
                if (error) {
                    NSLog(@"Error showing notification: %@", error);
                    success = NO;
                }
                dispatch_semaphore_signal(sendSema);
            }];
            
            dispatch_semaphore_wait(sendSema, DISPATCH_TIME_FOREVER);
            return success;
        } 
        // 旧版 macOS 实现 (10.8-10.13)
        else {
            NSUserNotification* notification = [[NSUserNotification alloc] init];
            notification.title = nsTitle;
            notification.informativeText = nsMessage;
            notification.soundName = NSUserNotificationDefaultSoundName;
            
            if (nsApp.length > 0) {
                notification.subtitle = nsApp;
            }
            
            if (nsImagePath && nsImagePath.length > 0) {
                NSURL* imageURL = [NSURL fileURLWithPath:nsImagePath];
                NSImage* image = [[NSImage alloc] initWithContentsOfURL:imageURL];
                if (image) {
                    [image setSize:NSMakeSize(64, 64)];
                    notification.contentImage = image;
                }
            }
            
            // 设置通知样式为警报（确保显示在屏幕中央）
            notification.hasActionButton = YES;
            notification.actionButtonTitle = @"OK";
            
            [[NSUserNotificationCenter defaultUserNotificationCenter] deliverNotification:notification];
            return true;
        }
    }
}