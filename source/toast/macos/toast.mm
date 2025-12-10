#include "../toast.h"
#import <Foundation/Foundation.h>
#import <UserNotifications/UserNotifications.h>
#include <AppKit/AppKit.h>

@interface NotificationDelegate : NSObject <UNUserNotificationCenterDelegate>
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
    completionHandler(UNAuthorizationOptionAlert | UNAuthorizationOptionSound);
}
@end

bool toastShow(const char* app, const char* title, const char* message, const char* image_path) {
    @autoreleasepool {
        // Initialize notification center
        UNUserNotificationCenter* center = [UNUserNotificationCenter currentNotificationCenter];
        [center setDelegate:[NotificationDelegate shared]];
        
        // Request permissions if needed
        static dispatch_once_t onceToken;
        dispatch_once(&onceToken, ^{
            [center requestAuthorizationWithOptions:(UNAuthorizationOptionAlert | UNAuthorizationOptionSound) 
                                  completionHandler:^(BOOL granted, NSError * _Nullable error) {
                if (!granted) NSLog(@"Notification permission denied");
            }];
        });
        
        // Create notification content
        UNMutableNotificationContent* content = [[UNMutableNotificationContent alloc] init];
        content.title = [NSString stringWithUTF8String:title ?: ""];
        content.subtitle = [NSString stringWithUTF8String:app ?: ""];
        content.body = [NSString stringWithUTF8String:message ?: ""];
        content.sound = [UNNotificationSound defaultSound];
        
        // Add image attachment
        if (image_path && strlen(image_path) > 0) {
            NSString* path = [NSString stringWithUTF8String:image_path];
            NSURL* url = [NSURL fileURLWithPath:path];
            
            if ([[NSFileManager defaultManager] fileExistsAtPath:path]) {
                NSError* error;
                UNNotificationAttachment* attachment = [UNNotificationAttachment 
                    attachmentWithIdentifier:@"img" 
                    URL:url 
                    options:@{UNNotificationAttachmentOptionsTypeHintKey: @"public.image"} 
                    error:&error];
                
                if (attachment && !error) {
                    content.attachments = @[attachment];
                }
            }
        }
        
        // Create and schedule request
        UNNotificationRequest* request = [UNNotificationRequest 
            requestWithIdentifier:[[NSUUID UUID] UUIDString]
            content:content
            trigger:[UNTimeIntervalNotificationTrigger triggerWithTimeInterval:0.1 repeats:NO]];
        
        __block bool success = false;
        dispatch_semaphore_t semaphore = dispatch_semaphore_create(0);
        
        [center addNotificationRequest:request withCompletionHandler:^(NSError * _Nullable error) {
            if (error) {
                NSLog(@"Error sending notification: %@", error.localizedDescription);
            } else {
                success = true;
            }
            dispatch_semaphore_signal(semaphore);
        }];
        
        dispatch_semaphore_wait(semaphore, DISPATCH_TIME_FOREVER);
        return success;
    }
}