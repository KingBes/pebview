#include "window.h"
#include <AppKit/AppKit.h>
#include <objc/objc-runtime.h>
#include <objc/runtime.h> // objc_getAssociatedObject / objc_setAssociatedObject

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

// ---------------------------------------------------------------------------
// 标题栏外观
// ---------------------------------------------------------------------------

int window_set_titlebar_theme(const void *ptr, int mode, int caption, int text)
{
    if (!ptr) {
        return 1; // WINDOW_NOT_FOUND
    }

    // AppKit 没有"只改标题栏配色"的接口 —— 标题栏由系统绘制，能控制的只有整个
    // 窗口的浅/深色外观（NSAppearance）。所以「只给了颜色、没给 mode」这种请求
    // 如实返回不支持，而不是静默地什么都不做、让调用方以为已经生效。
    if (mode < 0 && (caption >= 0 || text >= 0)) {
        return 3; // OS_UNSUPPORTED
    }

    NSWindow *window = (NSWindow *)ptr;

    // x86_64 的部署下限是 10.13，而浅/深色外观（NSAppearanceNameDarkAqua）是
    // 10.14 才有的，所以必须做可用性判断，不能直接引用那个常量。
    if (@available(macOS 10.14, *)) {
        if (mode < 0) {
            [window setAppearance:nil]; // 清除窗口级覆盖，回到跟随系统
            return 0;
        }
        NSAppearance *appearance =
            [NSAppearance appearanceNamed:(mode == 1 ? NSAppearanceNameDarkAqua
                                                      : NSAppearanceNameAqua)];
        if (!appearance) {
            return 3;
        }
        [window setAppearance:appearance];
        return 0;
    }

    return 3; // 10.14 以下没有浅/深色外观这个概念
}

// ---------------------------------------------------------------------------
// 自定义标题栏（准无边框）
// ---------------------------------------------------------------------------
//
// macOS 这边是三平台里最省事的：不用去掉标题栏，而是把它"透明化 + 让内容下延"。
//   - 保留 NSWindowStyleMaskTitled        → 红绿灯、拖动、双击缩放、全屏全都还是原生的
//   - 加 NSWindowStyleMaskFullSizeContentView → 内容延伸到标题栏下方，你才有地方画
//   - titlebarAppearsTransparent = YES    → 标题栏不再绘制底色
//   - titleVisibility = NSWindowTitleHidden → 隐藏系统标题文字
//
// 所以 macOS 完全不需要自己实现拖动逻辑 —— 这正是"准无边框"相对"完全无边框"的价值。

int window_set_titlebar_geometry(const void *ptr, int height, int controls_width, int drag)
{
    if (!ptr) {
        return 1; // WINDOW_NOT_FOUND
    }

    NSWindow *window = (NSWindow *)ptr;

    if (height <= 0) {
        // 关闭：退回系统标题栏
        window.styleMask &= ~NSWindowStyleMaskFullSizeContentView;
        [window setTitlebarAppearsTransparent:NO];
        [window setTitleVisibility:NSWindowTitleVisible];
        [window setMovableByWindowBackground:NO];
        return 0;
    }

    window.styleMask |= NSWindowStyleMaskFullSizeContentView;
    [window setTitlebarAppearsTransparent:YES];
    [window setTitleVisibility:NSWindowTitleHidden];
    // 允许拖动窗口背景的空白处，等价于 Windows 那边的 HTCAPTION
    [window setMovableByWindowBackground:(drag ? YES : NO)];

    (void)controls_width; // 红绿灯的位置与宽度由系统决定，这个参数在 macOS 上没有意义
    return 0;
}

int window_minimize(const void *ptr)
{
    if (!ptr) {
        return 1;
    }
    [(NSWindow *)ptr miniaturize:nil];
    return 0;
}

int window_toggle_maximize(const void *ptr)
{
    if (!ptr) {
        return 0;
    }
    NSWindow *window = (NSWindow *)ptr;
    [window zoom:nil]; // zoom: 本身就是"切换"语义
    return [window isZoomed] ? 1 : 0;
}

int window_is_maximized(const void *ptr)
{
    if (!ptr) {
        return 0;
    }
    NSWindow *window = (NSWindow *)ptr;
    // 全屏也算最大化 —— 从用户视角两者都是"铺满"
    return ([window isZoomed] || ([window styleMask] & NSWindowStyleMaskFullScreen)) ? 1 : 0;
}

// 状态回调的门面对象：监听尺寸变化，比对之后只在真的切换时上报一次
@interface PebViewStateWatcher : NSObject
{
@public
    void (*callback)(const void *ptr, int state);
    const void *windowPtr;
    int lastMaximized;
}
- (void)onWindowResize:(NSNotification *)note;
@end

@implementation PebViewStateWatcher

- (void)onWindowResize:(NSNotification *)note
{
    if (!callback || !windowPtr) {
        return;
    }
    NSWindow *w = (NSWindow *)windowPtr;
    int maximized = ([w isZoomed] || ([w styleMask] & NSWindowStyleMaskFullScreen)) ? 1 : 0;
    if (maximized != lastMaximized) {
        lastMaximized = maximized;
        callback(windowPtr, maximized);
    }
}

@end

// 用关联对象把 watcher 挂在 NSWindow 上：这里是非 ARC，通知中心并不持有 observer，
// 不自己保一份的话回调会被提前释放掉。
static const char kPebViewStateWatcherKey;

int window_set_state_callback(const void *ptr, void (*cb)(const void *ptr, int state))
{
    if (!ptr || !cb) {
        return 1;
    }

    NSWindow *window = (NSWindow *)ptr;

    PebViewStateWatcher *old =
        (PebViewStateWatcher *)objc_getAssociatedObject(window, &kPebViewStateWatcherKey);
    if (old) {
        [[NSNotificationCenter defaultCenter] removeObserver:old];
        objc_setAssociatedObject(window, &kPebViewStateWatcherKey, nil, OBJC_ASSOCIATION_RETAIN);
    }

    PebViewStateWatcher *watcher = [[PebViewStateWatcher alloc] init];
    watcher->callback = cb;
    watcher->windowPtr = ptr;
    watcher->lastMaximized = ([window isZoomed] ? 1 : 0);

    objc_setAssociatedObject(window, &kPebViewStateWatcherKey, watcher, OBJC_ASSOCIATION_RETAIN);

    [[NSNotificationCenter defaultCenter] addObserver:watcher
                                            selector:@selector(onWindowResize:)
                                                name:NSWindowDidResizeNotification
                                              object:window];
    return 0;
}

// JS 在标题栏 mousedown 里调过来，发起一次窗口拖动。
//
// macOS 的标题栏区域本来就是原生可拖的，但自绘标题栏往往比系统标题栏高，
// 高出来的那截拖不动；而且在 FullSizeContentView 下也拿不到可用的 NSEvent
// （performWindowDragWithEvent: 需要事件对象，桥接消息不是鼠标事件）。
// 所以这里自己搬窗口：按光标位移 setFrameOrigin，直到左键松开。
//
// 循环里要跑一下 run loop，否则主线程被占住、界面完全不刷新。
// 有 30 秒上限兜底，避免万一收不到"松开"就一直转。
//
// ⚠️ 本机没有 macOS 环境，这段只做了语法层面的实现，未在真机验证过；
//    Linux 走 gdk_window_begin_move_drag，Windows 走合成的 caption 拖动。
int window_begin_move_drag(const void *ptr, int x, int y)
{
    (void)x;
    (void)y;

    if (!ptr)
    {
        return 1; // WINDOW_NOT_FOUND
    }

    NSWindow *window = (NSWindow *)ptr;

    // 左键得是按着的，否则会白转一圈（也顺手挡住"没在拖却调用"的情况）
    if (([NSEvent pressedMouseButtons] & 1) == 0)
    {
        return 4; // NOT_DRAGGING
    }

    NSPoint last = [NSEvent mouseLocation];
    NSRect frame = [window frame];
    NSDate *deadline = [NSDate dateWithTimeIntervalSinceNow:30.0];

    while (([NSEvent pressedMouseButtons] & 1) != 0 &&
           [deadline timeIntervalSinceNow] > 0)
    {
        NSPoint now = [NSEvent mouseLocation];
        frame.origin.x += now.x - last.x;
        frame.origin.y += now.y - last.y;
        [window setFrameOrigin:frame.origin];
        last = now;

        // 让主线程喘口气：不跑 run loop 的话界面在拖动期间完全不重绘
        [[NSRunLoop currentRunLoop]
            runMode:NSDefaultRunLoopMode
            beforeDate:[NSDate dateWithTimeIntervalSinceNow:0.01]];
    }

    return 0;
}

// 从外部发起窗口缩放（edge 为 WMSZ_* 编码：1=LEFT 2=RIGHT 3=TOP 4=TOPLEFT
// 5=TOPRIGHT 6=BOTTOM 7=BOTTOMLEFT 8=BOTTOMRIGHT）。
//
// macOS 没有 gdk 那种 resize-drag 原语，这里按光标位移在 run loop 里手动改窗口 frame，
// 与 begin_move_drag 的搬运思路一致（拖动就是搬 origin，缩放就是改 size，
// 屏幕坐标 y 向上为正，所以 TOP 边要同时动 origin.y 与 height）。
// 三平台同一句 JS，页面侧不按平台分支。只在左键真按着时发起；左键已松开返回 4。
int window_begin_resize_drag(const void *ptr, int edge)
{
    if (!ptr)
    {
        return 1; // WINDOW_NOT_FOUND
    }

    NSWindow *window = (NSWindow *)ptr;

    if (([NSEvent pressedMouseButtons] & 1) == 0)
    {
        return 4; // NOT_DRAGGING
    }

    if (edge < 1 || edge > 8)
    {
        return 1;
    }

    NSPoint last = [NSEvent mouseLocation];
    NSRect frame = [window frame];
    NSDate *deadline = [NSDate dateWithTimeIntervalSinceNow:30.0];

    while (([NSEvent pressedMouseButtons] & 1) != 0 &&
           [deadline timeIntervalSinceNow] > 0)
    {
        NSPoint now = [NSEvent mouseLocation];
        CGFloat dx = now.x - last.x;
        CGFloat dy = now.y - last.y;

        // 屏幕坐标 y 向上为正：dy>0 表示光标上移。frame.origin.y 是窗口底边，
        // size.height 向上增长，所以 TOP 边要动 origin.y 与 height 两个量。
        if (edge == 1 || edge == 4 || edge == 7) { // LEFT
            frame.origin.x += dx;
            frame.size.width -= dx;
        }
        if (edge == 2 || edge == 5 || edge == 8) { // RIGHT
            frame.size.width += dx;
        }
        if (edge == 3 || edge == 4 || edge == 5) { // TOP
            frame.origin.y += dy;
            frame.size.height -= dy;
        }
        if (edge == 6 || edge == 7 || edge == 8) { // BOTTOM
            frame.size.height += dy;
        }

        if (frame.size.width < 1) frame.size.width = 1;
        if (frame.size.height < 1) frame.size.height = 1;

        [window setFrame:frame display:YES];
        last = now;

        [[NSRunLoop currentRunLoop]
            runMode:NSDefaultRunLoopMode
            beforeDate:[NSDate dateWithTimeIntervalSinceNow:0.01]];
    }
    return 0;
}