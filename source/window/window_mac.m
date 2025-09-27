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
    NSMutableArray *menuHandlers; // 存储所有菜单处理器的数组
} TrayData;

// 菜单项点击处理类
@interface TrayMenuItemHandler : NSObject
@property (assign) struct tray_menu *menuItem;
@end

@implementation TrayMenuItemHandler
- (void)handleMenuClick:(id)sender
{
    if (_menuItem && _menuItem->callback && !_menuItem->disabled) {
        _menuItem->callback(_menuItem);
    }
}
@end

// 自定义状态项视图，用于处理鼠标点击
@interface CustomStatusItemView : NSView
@property (assign) TrayData *trayData;
@end

@implementation CustomStatusItemView

- (void)mouseDown:(NSEvent *)event
{
    // 左键单击显示窗口
    if (self.trayData && self.trayData->windowPtr) {
        NSWindow *window = (NSWindow *)self.trayData->windowPtr;
        [window makeKeyAndOrderFront:nil];
    }
}

- (void)rightMouseDown:(NSEvent *)event
{
    // 右键单击显示菜单
    if (self.trayData && self.trayData->statusItem) {
        [self.trayData->statusItem popUpStatusItemMenu:self.trayData->menu];
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
    trayData->menuHandlers = [[NSMutableArray alloc] init];
    
    // 创建状态栏项
    trayData->statusItem = [[NSStatusBar systemStatusBar] statusItemWithLength:NSVariableStatusItemLength];
    
    // 设置图标
    NSString *iconPath = [NSString stringWithUTF8String:icon];
    NSImage *image = [[NSImage alloc] initWithContentsOfFile:iconPath];
    if (image) {
        [image setSize:NSMakeSize(18, 18)];
        [trayData->statusItem setImage:image];
    }
    
    // 创建菜单
    trayData->menu = [[NSMenu alloc] initWithTitle:@"TrayMenu"];
    [trayData->menu setAutoenablesItems:NO];
    
    // 创建自定义视图来处理鼠标点击
    CustomStatusItemView *customView = [[CustomStatusItemView alloc] init];
    customView.trayData = trayData;
    [trayData->statusItem setView:customView];
    
    return trayData;
}

// 添加托盘菜单
void window_tray_add_menu(const void *tray, struct tray_menu *menu)
{
    if (!tray || !menu) return;
    
    TrayData *trayData = (TrayData *)tray;
    if (!trayData->menu) return;
    
    // 创建菜单项处理器
    TrayMenuItemHandler *handler = [[TrayMenuItemHandler alloc] init];
    handler.menuItem = menu;
    
    // 将处理器添加到数组中以保持其活性
    [trayData->menuHandlers addObject:handler];
    
    // 创建菜单项
    NSString *title = menu->text ? [NSString stringWithUTF8String:menu->text] : @"";
    NSMenuItem *menuItem = [[NSMenuItem alloc] initWithTitle:title 
                                                      action:@selector(handleMenuClick:) 
                                               keyEquivalent:@""];
    
    // 设置菜单项状态
    [menuItem setEnabled:!menu->disabled];
    [menuItem setState:menu->checked ? NSControlStateValueOn : NSControlStateValueOff];
    [menuItem setTarget:handler];
    
    // 将处理器与菜单项关联（防止被释放）
    objc_setAssociatedObject(menuItem, "handler", handler, OBJC_ASSOCIATION_RETAIN);
    
    // 添加到菜单
    [trayData->menu addItem:menuItem];
}

// 移除托盘菜单
void window_tray_remove(void *tray)
{
    if (!tray) return;
    
    TrayData *trayData = (TrayData *)tray;
    
    // 从状态栏移除项
    if (trayData->statusItem) {
        [[NSStatusBar systemStatusBar] removeStatusItem:trayData->statusItem];
    }
    
    // 释放菜单
    if (trayData->menu) {
        [trayData->menu release];
    }
    
    // 释放菜单处理器数组
    if (trayData->menuHandlers) {
        [trayData->menuHandlers release];
    }
    
    // 释放内存
    free(trayData);
}