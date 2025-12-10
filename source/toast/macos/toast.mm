#include "../toast.h"
#import <Foundation/Foundation.h>
#import <UserNotifications/UserNotifications.h>
#include <AppKit/AppKit.h>

bool toastShow(const char* app, const char* title, const char* message, const char* image_path) {
    @autoreleasepool {
        // 准备参数
        NSString* nsApp = @"";
        NSString* nsTitle = @"";
        NSString* nsMessage = @"";
        
        if (app) nsApp = [NSString stringWithUTF8String:app] ?: @"";
        if (title) nsTitle = [NSString stringWithUTF8String:title] ?: @"";
        if (message) nsMessage = [NSString stringWithUTF8String:message] ?: @"";
        
        // 构建命令
        NSString* command = [NSString stringWithFormat:
            @"display notification \"%@\" with title \"%@\" subtitle \"%@\"",
            nsMessage, nsTitle, nsApp];
        
        NSString* fullCommand = [NSString stringWithFormat:
            @"osascript -e '%@'", command];
        
        // 使用 NSTask 执行
        NSTask* task = [[NSTask alloc] init];
        [task setLaunchPath:@"/bin/bash"];
        [task setArguments:@[@"-c", fullCommand]];
        
        NSPipe* pipe = [NSPipe pipe];
        [task setStandardOutput:pipe];
        [task setStandardError:pipe];
        
        @try {
            [task launch];
            [task waitUntilExit];
            return [task terminationStatus] == 0;
        } @catch (NSException* exception) {
            NSLog(@"执行通知出错: %@", exception);
            return false;
        }
    }
}