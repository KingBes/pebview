#include "../toast.h"
#import <Foundation/Foundation.h>
#import <UserNotifications/UserNotifications.h>

// Objective-C 辅助类
@interface NotificationHelper : NSObject <UNUserNotificationCenterDelegate>
+ (void)initializeNotifications;
+ (void)sendNotificationWithApp:(NSString*)app 
                         title:(NSString*)title 
                       message:(NSString*)message 
                     imagePath:(NSString*)imagePath;
@end

@implementation NotificationHelper

+ (void)initializeNotifications {
    UNUserNotificationCenter* center = [UNUserNotificationCenter currentNotificationCenter];
    center.delegate = (id<UNUserNotificationCenterDelegate>)self;
    
    UNAuthorizationOptions options = UNAuthorizationOptionAlert | UNAuthorizationOptionSound;
    [center requestAuthorizationWithOptions:options completionHandler:^(BOOL granted, NSError * _Nullable error) {
        if (!granted) {
            NSLog(@"Notification permission denied");
        }
    }];
}

+ (void)sendNotificationWithApp:(NSString*)app 
                         title:(NSString*)title 
                       message:(NSString*)message 
                     imagePath:(NSString*)imagePath {
    
    UNMutableNotificationContent* content = [[UNMutableNotificationContent alloc] init];
    content.title = title ?: @"";
    content.subtitle = app ?: @"";
    content.body = message ?: @"";
    content.sound = [UNNotificationSound defaultSound];
    
    // 添加应用图标（可选）
    if (app) {
        NSDictionary* appIconDict = @{@"app_icon": app};
        content.userInfo = appIconDict;
    }
    
    // 处理图片附件
    if (imagePath && imagePath.length > 0) {
        NSURL* url = [NSURL fileURLWithPath:imagePath];
        NSError* error;
        UNNotificationAttachment* attachment = [UNNotificationAttachment attachmentWithIdentifier:@"image" URL:url options:nil error:&error];
        if (attachment && !error) {
            content.attachments = @[attachment];
        } else if (error) {
            NSLog(@"Image attachment error: %@", error.localizedDescription);
        }
    }
    
    // 创建通知请求
    UNNotificationRequest* request = [UNNotificationRequest 
        requestWithIdentifier:[[NSUUID UUID] UUIDString]
                      content:content
                      trigger:[UNTimeIntervalNotificationTrigger triggerWithTimeInterval:0.1 repeats:NO]];
    
    // 发送通知
    [[UNUserNotificationCenter currentNotificationCenter] addNotificationRequest:request withCompletionHandler:^(NSError * _Nullable error) {
        if (error) {
            NSLog(@"Add notification failed: %@", error.localizedDescription);
        }
    }];
}

// 前台显示通知
+ (void)userNotificationCenter:(UNUserNotificationCenter *)center 
       willPresentNotification:(UNNotification *)notification 
         withCompletionHandler:(void (^)(UNNotificationPresentationOptions))completionHandler {
    completionHandler(UNAuthorizationOptionAlert | UNAuthorizationOptionSound);
}

@end

bool toastShow(const char* app, const char* title, const char* message, const char* image_path) {
    @autoreleasepool {
        static dispatch_once_t onceToken;
        dispatch_once(&onceToken, ^{
            [NotificationHelper initializeNotifications];
        });
        
        NSString* nsApp = app ? [NSString stringWithUTF8String:app] : nil;
        NSString* nsTitle = title ? [NSString stringWithUTF8String:title] : nil;
        NSString* nsMessage = message ? [NSString stringWithUTF8String:message] : nil;
        NSString* nsImagePath = image_path ? [NSString stringWithUTF8String:image_path] : nil;
        
        [NotificationHelper sendNotificationWithApp:nsApp 
                                            title:nsTitle 
                                          message:nsMessage 
                                        imagePath:nsImagePath];
        return true;
    }
}