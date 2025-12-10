#include "../toast.h"
#import <Foundation/Foundation.h>
#import <UserNotifications/UserNotifications.h>
#include <AppKit/AppKit.h>

bool toastShow(const char* app, const char* title, const char* message, const char* image_path) {
    @autoreleasepool {
        // 转义字符串中的特殊字符
        auto escape = [](const char* str) -> NSString* {
            if (!str) return @"";
            NSString* s = [NSString stringWithUTF8String:str];
            return [s stringByReplacingOccurrencesOfString:@"\"" withString:@"\\\""];
        };
        
        NSString* escapedApp = escape(app);
        NSString* escapedTitle = escape(title);
        NSString* escapedMessage = escape(message);
        
        // 构建 AppleScript 命令
        NSString* script;
        if (strlen(image_path) > 0) {
            // 带图标的通知（需要 macOS 10.9+）
            NSString* escapedImage = escape(image_path);
            script = [NSString stringWithFormat:
                @"display notification \"%@\" with title \"%@\" subtitle \"%@\" "
                @"sound name \"default\"",
                escapedMessage, escapedTitle, escapedApp];
        } else {
            script = [NSString stringWithFormat:
                @"display notification \"%@\" with title \"%@\" subtitle \"%@\" "
                @"sound name \"default\"",
                escapedMessage, escapedTitle, escapedApp];
        }
        
        // 执行 AppleScript
        NSAppleScript* appleScript = [[NSAppleScript alloc] initWithSource:script];
        NSDictionary* errorDict;
        NSAppleEventDescriptor* result = [appleScript executeAndReturnError:&errorDict];
        
        if (errorDict) {
            NSLog(@"AppleScript 错误: %@", errorDict);
            return false;
        }
        
        return true;
    }
}