#include "window.h"
#include <AppKit/AppKit.h>
#include <objc/objc-runtime.h>

// 窗口显示
int window_show(const void *ptr)
{
    if (!ptr)
    {
        return 0;
    }
    NSWindow *window = (NSWindow *)ptr;
    [window makeKeyAndOrderFront:nil];
    return 1;
}

// 窗口隐藏
int window_hide(const void *ptr)
{
    if (!ptr)
    {
        return 0;
    }
    NSWindow *window = (NSWindow *)ptr;
    [window orderOut:nil];
    return 1;
}

// 内部数据结构定义
typedef struct {
    NSStatusItem *statusItem;
    NSMenu *menu;
    NSMutableArray *menuItems;
} TrayData;

// 调整图像大小到适合系统托盘的尺寸
NSImage *resizeImageForTray(NSImage *image) {
    // 系统托盘图标的推荐尺寸
    CGFloat trayIconSize = 18.0; // macOS 系统托盘的标准尺寸
    
    // 创建新尺寸的图像
    NSImage *resizedImage = [[NSImage alloc] initWithSize:NSMakeSize(trayIconSize, trayIconSize)];
    
    [resizedImage lockFocus];
    NSRect rect = NSMakeRect(0, 0, trayIconSize, trayIconSize);
    [[NSGraphicsContext currentContext] setImageInterpolation:NSImageInterpolationHigh];
    [image drawInRect:rect
             fromRect:NSMakeRect(0, 0, image.size.width, image.size.height)
            operation:NSCompositingOperationCopy
             fraction:1.0];
    [resizedImage unlockFocus];
    
    return [resizedImage autorelease];
}

// 创建窗口托盘
void *window_tray(const void *ptr, const char *icon)
{
    if (!ptr || !icon) return NULL;
    
    NSWindow *window = (NSWindow *)ptr;
    
    // 分配内存给托盘数据结构
    TrayData *trayData = malloc(sizeof(TrayData));
    if (!trayData) return NULL;
    
    // 创建状态栏项
    trayData->statusItem = [[NSStatusBar systemStatusBar] statusItemWithLength:NSVariableStatusItemLength];
    
    // 设置图标
    NSString *iconPath = [NSString stringWithUTF8String:icon];
    NSImage *image = [[NSImage alloc] initWithContentsOfFile:iconPath];
    if (image) {   
        // 调整图像大小以适应系统托盘
        NSImage *resizedImage = resizeImageForTray(image);
        [trayData->statusItem setImage:resizedImage];
        [image release];
    }
    
    // 创建菜单
    trayData->menu = [[NSMenu alloc] initWithTitle:@"TrayMenu"];
    trayData->menuItems = [[NSMutableArray alloc] init];
    
    [trayData->statusItem setMenu:trayData->menu];
    [trayData->statusItem setHighlightMode:YES];
    
    return trayData;
}

// Objective-C 辅助类用于处理菜单项点击
@interface TrayMenuTarget : NSObject
{
    void (*_callback)(const void *ptr);
    const void *_userData;
}
- (instancetype)initWithCallback:(void (*)(const void *))callback userData:(const void *)userData;
- (void)menuItemClicked:(id)sender;
@end

@implementation TrayMenuTarget

- (instancetype)initWithCallback:(void (*)(const void *))callback userData:(const void *)userData
{
    self = [super init];
    if (self) {
        _callback = callback;
        _userData = userData;
    }
    return self;
}

- (void)menuItemClicked:(id)sender
{
    if (_callback) {
        _callback(_userData);
    }
}

@end

// 添加托盘菜单
void window_tray_add_menu(const void *tray, struct tray_menu *menu)
{
    if (!tray || !menu) return;
    
    TrayData *trayData = (TrayData *)tray;
    
    // 创建菜单项
    NSString *title = menu->text ? [NSString stringWithUTF8String:menu->text] : @"";
    NSMenuItem *menuItem = [[NSMenuItem alloc] initWithTitle:title 
                                                      action:@selector(menuItemClicked:) 
                                               keyEquivalent:@""];
    
    // 设置菜单项状态
    [menuItem setEnabled:!menu->disabled];
    [menuItem setState:menu->checked ? NSControlStateValueOn : NSControlStateValueOff];
    
    // 创建目标对象处理回调
    TrayMenuTarget *target = [[TrayMenuTarget alloc] initWithCallback:menu->callback 
                                                            userData:NULL]; // 可根据需要传递用户数据
    
    [menuItem setTarget:target];
    [menuItem setTag:menu->id];
    
    // 保存菜单项和目标对象引用
    [trayData->menuItems addObject:menuItem];
    [trayData->menuItems addObject:target];
    
    // 添加到菜单
    [trayData->menu addItem:menuItem];
    
    [menuItem release];
    [target release];
}

// 移除托盘菜单
void window_tray_remove(void *tray)
{
    if (!tray) return;
    
    TrayData *trayData = (TrayData *)tray;
    
    // 从状态栏移除
    [[NSStatusBar systemStatusBar] removeStatusItem:trayData->statusItem];
    
    // 释放资源
    [trayData->menu release];
    [trayData->menuItems release];
    [trayData->statusItem release];
    
    free(trayData);
}