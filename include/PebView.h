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
