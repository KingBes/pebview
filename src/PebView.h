// 保存MAJOR.MINOR.PATCH版本号的元素。
typedef struct
{
    // 主要版本
    unsigned int major;
    // 小版本
    unsigned int minor;
    // 补丁版本
    unsigned int patch;
} webview_version_t;
// 保存库的版本信息。
typedef struct
{
    // 版本号的元素。
    webview_version_t version;
    // MAJOR.MINOR.PATCH格式的SemVer 2.0.0版本号。
    char version_number[32];
    // 如果指定，SemVer 2.0.0预发布标签前缀为“-”，否则
    // 为空字符串。
    char pre_release[48];
    // 如果指定，SemVer 2.0.0构建元数据前缀为“+”，否则
    char build_metadata[48];
} webview_version_info_t;
// 本地处理类型。实际的类型取决于后端。
typedef enum
{
    // UI窗口。@c GtkWindow指针(GTK)，@c NSWindow指针(Cocoa)
    // 或@c HWND(Win32)。
    WEBVIEW_NATIVE_HANDLE_KIND_UI_WINDOW,
    // UI小部件。@c GtkWidget指针(GTK)，@c NSView指针(Cocoa)或
    // @c HWND(Win32)。
    WEBVIEW_NATIVE_HANDLE_KIND_UI_WIDGET,
    // 浏览器控制器。@c WebKitWebView指针(WebKitGTK)，@c WKWebView
    // 指针(Cocoa/WebKit)或@c ICoreWebView2Controller指针(Win32/WebView2)。
    // @note 此类型仅在WebView2中可用。
    WEBVIEW_NATIVE_HANDLE_KIND_BROWSER_CONTROLLER
} webview_native_handle_kind_t;
// 窗口大小提示。
typedef enum
{
    // 没有提示。
    WEBVIEW_HINT_NONE,
    // 最小宽度和高度。
    WEBVIEW_HINT_MIN,
    // 最大宽度和高度。
    WEBVIEW_HINT_MAX,
    // 窗口大小不能由用户更改。
    WEBVIEW_HINT_FIXED
} webview_hint_t;
typedef enum
{
    // 窗口未找到。
    WEBVIEW_ERROR_MISSING_DEPENDENCY = -5,
    // 操作已被用户取消。
    WEBVIEW_ERROR_CANCELED = -4,
    // 无效状态检测到。
    WEBVIEW_ERROR_INVALID_STATE = -3,
    // 一个或多个无效参数已指定，例如在函数调用中。
    WEBVIEW_ERROR_INVALID_ARGUMENT = -2,
    // 发生了未指定的错误。可能需要更多特定的错误代码。
    WEBVIEW_ERROR_UNSPECIFIED = -1,
    // 成功操作。 函数返回错误代码以指示成功操作。
    // 成功操作的函数通常返回0，而不是其他值。
    WEBVIEW_ERROR_OK = 0,
    // 内存分配失败。
    WEBVIEW_ERROR_DUPLICATE = 1,
    // 操作失败。
    WEBVIEW_ERROR_NOT_FOUND = 2
} webview_error_t;
typedef void *webview_t;
webview_t webview_create(int debug, void *window);
webview_error_t webview_destroy(webview_t w);
webview_error_t webview_run(webview_t w);
webview_error_t webview_terminate(webview_t w);
webview_error_t webview_dispatch(webview_t w, void (*fn)(webview_t w, void *arg), void *arg);
void *webview_get_window(webview_t w);
void *webview_get_native_handle(webview_t w, webview_native_handle_kind_t kind);
webview_error_t webview_set_title(webview_t w, const char *title);
webview_error_t webview_set_size(webview_t w, int width, int height, webview_hint_t hints);
webview_error_t webview_navigate(webview_t w, const char *url);
webview_error_t webview_set_html(webview_t w, const char *html);
webview_error_t webview_init(webview_t w, const char *js);
webview_error_t webview_eval(webview_t w, const char *js);
webview_error_t webview_bind(webview_t w, const char *name, void (*fn)(const char *id, const char *req, void *arg), void *arg);
webview_error_t webview_unbind(webview_t w, const char *name);
webview_error_t webview_return(webview_t w, const char *id, int status, const char *result);
const webview_version_info_t *webview_version(void);

// dialog-------------------------------------------------------------------------------------------------------------------------
typedef enum
{
    OSDIALOG_INFO,
    OSDIALOG_WARNING,
    OSDIALOG_ERROR,
} osdialog_message_level;
typedef enum
{
    OSDIALOG_OK,
    OSDIALOG_OK_CANCEL,
    OSDIALOG_YES_NO,
} osdialog_message_buttons;
int osdialog_message(osdialog_message_level level, osdialog_message_buttons buttons, const char *message);