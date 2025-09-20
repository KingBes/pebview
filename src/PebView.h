// webview

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

// icon
int set_icon(const void *ptr, const char *iconFilePath);

// dialog
int osdialog_message(int level, int buttons, const char *message);
const char *osdialog_prompt(int level, const char *message, const char *text);
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
const char *osdialog_file(int action, const char *dir, const char *filename, const osdialog_filters *filters);

// other
void other_get_screen_size(int *width, int *height);
void *other_create_window(int x, int y, int width, int height);
int other_window_minimize(const void *window);