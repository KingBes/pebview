#include "window.h"
#include <AppKit/AppKit.h>
#include <objc/runtime.h>
#include <objc/message.h>

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

// 内部数据结构，用于存储托盘相关信息
typedef struct {
    NSStatusItem *statusItem;
    NSMenu *menu;
    const void *windowPtr;
    NSMutableArray *handlers; // 存储所有菜单项处理器的数组
    NSMutableDictionary *menuItems; // 按ID存储菜单项的映射
} TrayData;

// 菜单项点击处理类
@interface TrayMenuItemHandler : NSObject
@property (assign) struct tray_menu *menuItem;
@end

@implementation TrayMenuItemHandler
- (void)handleMenuClick:(id)sender
{
    if (self.menuItem && self.menuItem->callback && !self.menuItem->disabled) {
        self.menuItem->callback(self.menuItem);
    }
}
@end

// 创建窗口托盘
void *window_tray(const void *ptr, const char *icon)
{
    if (!ptr || !icon) return NULL;
    
    // 分配内存存储托盘数据
    TrayData *trayData = malloc(sizeof(TrayData));
    if (!trayData) return NULL;
    
    trayData->windowPtr = ptr;
    
    // 创建状态栏项
    trayData->statusItem = [[NSStatusBar systemStatusBar] statusItemWithLength:NSVariableStatusItemLength];
    [trayData->statusItem retain];
    
    // 设置图标
    NSString *iconPath = [NSString stringWithUTF8String:icon];
    NSImage *image = [[NSImage alloc] initWithContentsOfFile:iconPath];
    if (image) {
        [image setSize:NSMakeSize(18, 18)];
        [trayData->statusItem setImage:image];
        [image release];
    }
    
    // 创建菜单
    trayData->menu = [[NSMenu alloc] initWithTitle:@"TrayMenu"];
    [trayData->menu setAutoenablesItems:NO];
    
    // 初始化处理器数组和菜单项映射
    trayData->handlers = [[NSMutableArray alloc] init];
    trayData->menuItems = [[NSMutableDictionary alloc] init];
    
    // 设置菜单到状态栏项
    [trayData->statusItem setMenu:trayData->menu];
    
    return trayData;
}

// 添加托盘菜单
void window_tray_add_menu(const void *tray, struct tray_menu *menu)
{
    if (!tray || !menu) return;
    
    TrayData *trayData = (TrayData *)tray;
    if (!trayData->menu) return;
    
    // 检查是否已存在相同ID的菜单项
    NSNumber *menuId = [NSNumber numberWithInt:menu->id];
    if ([trayData->menuItems objectForKey:menuId]) {
        // 已存在相同ID的菜单项，先移除旧的
        NSMenuItem *existingItem = [trayData->menuItems objectForKey:menuId];
        [trayData->menu removeItem:existingItem];
        [trayData->menuItems removeObjectForKey:menuId];
        
        // 也需要从handlers中移除对应的处理器
        for (TrayMenuItemHandler *handler in trayData->handlers) {
            if (handler.menuItem && handler.menuItem->id == menu->id) {
                [trayData->handlers removeObject:handler];
                break;
            }
        }
    }
    
    // 创建菜单项处理器
    TrayMenuItemHandler *handler = [[TrayMenuItemHandler alloc] init];
    handler.menuItem = menu;
    
    // 将处理器添加到数组中以保持强引用
    [trayData->handlers addObject:handler];
    [handler release];
    
    // 创建菜单项
    NSString *title = menu->text ? [NSString stringWithUTF8String:menu->text] : @"";
    NSMenuItem *menuItem = [[NSMenuItem alloc] initWithTitle:title 
                                                      action:@selector(handleMenuClick:) 
                                               keyEquivalent:@""];
    
    // 设置菜单项状态
    [menuItem setEnabled:!menu->disabled];
    [menuItem setState:menu->checked ? NSControlStateValueOn : NSControlStateValueOff];
    [menuItem setTarget:handler];
    
    // 设置菜单项的tag为菜单ID，以便区分
    [menuItem setTag:menu->id];
    
    // 存储菜单项引用
    [trayData->menuItems setObject:menuItem forKey:menuId];
    
    // 添加到菜单
    [trayData->menu addItem:menuItem];
    [menuItem release];
}

// 移除托盘菜单
void window_tray_remove(void *tray)
{
    if (!tray) return;
    
    TrayData *trayData = (TrayData *)tray;
    
    // 从状态栏移除项
    if (trayData->statusItem) {
        [[NSStatusBar systemStatusBar] removeStatusItem:trayData->statusItem];
        [trayData->statusItem release];
        trayData->statusItem = nil;
    }
    
    // 释放菜单
    if (trayData->menu) {
        [trayData->menu release];
        trayData->menu = nil;
    }
    
    // 释放处理器数组
    if (trayData->handlers) {
        [trayData->handlers release];
        trayData->handlers = nil;
    }
    
    // 释放菜单项映射
    if (trayData->menuItems) {
        [trayData->menuItems release];
        trayData->menuItems = nil;
    }
    
    // 释放内存
    free(trayData);
}