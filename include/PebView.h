// PebView 对外 C ABI —— FFI 与构建脚本共用的唯一契约
//
// 本文件是 PHP 侧 \FFI::cdef() 的输入，也是 source/exports.def 的依据：
// 这里声明的每个函数都应当被导出，反之导出的符号也应当在这里出现。
// 改动 ABI 时请同步更新 source/exports.def。

// ---------------------------------------------------------------------------
// webview（source/webview）
// ---------------------------------------------------------------------------

typedef void *webview_t;
typedef int webview_error_t;
webview_t webview_create(int debug, void *window);
webview_error_t webview_destroy(webview_t w);
webview_error_t webview_run(webview_t w);
webview_error_t webview_terminate(webview_t w);
webview_error_t webview_dispatch(webview_t w, void (*fn)(webview_t w, void *arg), void *arg);
void *webview_get_window(webview_t w);
void *webview_get_native_handle(webview_t w, int kind);
webview_error_t webview_set_title(webview_t w, const char *title);
webview_error_t webview_set_size(webview_t w, int width, int height, int hints);
webview_error_t webview_navigate(webview_t w, const char *url);
webview_error_t webview_set_html(webview_t w, const char *html);
webview_error_t webview_init(webview_t w, const char *js);
webview_error_t webview_eval(webview_t w, const char *js);
webview_error_t webview_bind(webview_t w, const char *name, void (*fn)(const char *id, const char *req, void *arg), void *arg);
webview_error_t webview_unbind(webview_t w, const char *name);
webview_error_t webview_return(webview_t w, const char *id, int status, const char *result);
webview_error_t webview_set_close_callback(webview_t w, int (*fn)(void *));

// ---------------------------------------------------------------------------
// 窗口图标与显示控制（source/seticon、source/window）
// ---------------------------------------------------------------------------

struct tray_menu
{
	int id;
	char *text;
	int disabled;
	int checked;
	void (*callback)(const void *ptr);
};

int set_icon(const void *ptr, const char *iconFilePath);
int window_show(const void *ptr);
int window_hide(const void *ptr);
void *window_tray(const void *ptr, const char *icon);
void window_tray_add_menu(const void *tray, struct tray_menu *menu);
void window_tray_remove(void *tray);

// 标题栏外观：mode 为 -1/0/1（跟随系统 / 浅色 / 深色），
// caption 与 text 是 0xRRGGBB，传 -1 表示不覆盖。
// 返回 0=OK、1=窗口不存在、3=该平台不支持这组配置。
//
// 注意这只改"外观"（颜色/深浅），标题栏本身仍由系统绘制。
// 要自己画标题栏（自定义高度、按钮、任意内容）用下面的 window_set_titlebar_geometry。
int window_set_titlebar_theme(const void *ptr, int mode, int caption, int text);

// ---------------------------------------------------------------------------
// 自定义标题栏（真正无边框 + JS 驱动缩放）
// ---------------------------------------------------------------------------
// height > 0 时进入自定义标题栏模式：顶部 height 像素被当作标题栏，交给系统
// 处理拖动/双击最大化等；height = 0 则关闭、退回系统标题栏。
// controls_width 是右侧按钮区宽度，该区域不响应拖动（让 HTML 里的按钮能收到点击）。
// drag 控制标题栏区域是否可拖动。
int window_set_titlebar_geometry(const void *ptr, int height, int controls_width, int drag);

// 窗口状态操作
int window_minimize(const void *ptr);
int window_toggle_maximize(const void *ptr);
int window_is_maximized(const void *ptr);

// 状态变化回调：窗口最大化/还原时触发，state 为 0（普通）或 1（最大化）
int window_set_state_callback(const void *ptr, void (*cb)(const void *ptr, int state));

// 从外部发起窗口拖动（x/y 为屏幕坐标）。
// 三平台统一：JS 在标题栏的 mousedown 里把 event.screenX / screenY 传进来即可，
// 页面侧不需要按平台分支。各平台内部各自用最合适的方式发起拖动：
//   Windows  合成 WM_NCLBUTTONDOWN(HTCAPTION)，进入系统模态移动循环
//            （贴边、双击最大化等原生行为都在）
//   Linux    gdk_window_begin_move_drag
//   macOS    按光标位移搬窗口
// 只在左键真正按着时同步发起一次，不阻塞到拖动结束；左键已松开则直接返回（不报错）。
// 返回 0=OK、1=窗口不存在、4=调用时左键已松开（忽略即可）。
int window_begin_move_drag(const void *ptr, int x, int y);

// 从外部发起窗口缩放（edge 为 WMSZ_* 编码：1=LEFT 2=RIGHT 3=TOP 4=TOPLEFT 5=TOPRIGHT
// 6=BOTTOM 7=BOTTOMLEFT 8=BOTTOMRIGHT）。三平台统一：JS 在 mousedown 里判定边缘、
// 把 edge 传进来即可，页面侧不按平台分支。各平台内部各自发起缩放：
//   Windows  发 WM_SYSCOMMAND(SC_SIZE | edge)，进系统模态缩放循环（贴边等原生行为都在）
//   Linux    gdk_window_begin_resize_drag
//   macOS    按光标位移改窗口 frame
// 只在左键真正按着时同步发起一次；左键已松开则直接返回 4。
// 返回 0=OK、1=窗口不存在/非法边、4=调用时左键已松开（忽略即可）。
int window_begin_resize_drag(const void *ptr, int edge);

// ---------------------------------------------------------------------------
// 原生对话框（source/dialog）
// ---------------------------------------------------------------------------

typedef struct osdialog_filter_patterns
{
	char *pattern;
	struct osdialog_filter_patterns *next;
} osdialog_filter_patterns;
typedef struct osdialog_filters
{
	char *name;
	osdialog_filter_patterns *patterns;
	struct osdialog_filters *next;
} osdialog_filters;

int osdialog_message(int level, int buttons, const char *message);
const char *osdialog_prompt(int level, const char *message, const char *text);
osdialog_filters* osdialog_filters_parse(const char* str);
void osdialog_filters_free(osdialog_filters *filters);
const char *osdialog_file(int action, const char *dir, const char *filename, const osdialog_filters *filters);

// ---------------------------------------------------------------------------
// 系统通知（source/toast）
// ---------------------------------------------------------------------------

/**
 * @brief 显示系统通知
 *
 * @param app 应用名称
 * @param title 通知标题
 * @param message 通知消息
 * @param image_path 图片路径
 * @return bool 是否成功显示通知
 */
bool toastShow(
    const char *app,
    const char *title,
    const char *message,
    const char *image_path);
