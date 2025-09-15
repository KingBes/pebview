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
int set_icon(const void* ptr, const char* iconFilePath);

// dialog
typedef void osdialog_message_callback(int result, void* user);
int osdialog_message(int level, int buttons, const char* message);
void osdialog_message_async(int level, int buttons, const char* message, osdialog_message_callback* cb, void* user);
const char* osdialog_prompt(int level, const char* message, const char* text);
typedef void osdialog_prompt_callback(char* result, void* user);
void osdialog_prompt_async(int level, const char* message, const char* text, osdialog_prompt_callback* cb, void* user);
typedef struct osdialog_filter_patterns {
	char* pattern;
	struct osdialog_filter_patterns* next;
} osdialog_filter_patterns;
typedef struct osdialog_filters {
	char* name;
	osdialog_filter_patterns* patterns;
	struct osdialog_filters* next;
} osdialog_filters;
osdialog_filters* osdialog_filters_parse(const char* str);
void osdialog_filter_patterns_free(osdialog_filter_patterns* patterns);
void osdialog_filters_free(osdialog_filters* filters);
osdialog_filters* osdialog_filters_copy(const osdialog_filters* src);
const char* osdialog_file(int action, const char* dir, const char* filename, const osdialog_filters* filters);
typedef void osdialog_file_callback(const char* result, void* user);
void osdialog_file_async(int action, const char* dir, const char* filename, const osdialog_filters* filters, osdialog_file_callback* cb, void* user);
typedef struct {
	unsigned char r, g, b, a;
} osdialog_color;
int osdialog_color_picker(osdialog_color* color, int opacity);
typedef void osdialog_color_picker_callback(int result, osdialog_color color, void* user);
void osdialog_color_picker_async(osdialog_color color, int opacity, osdialog_color_picker_callback* cb, void* user);
typedef void* osdialog_save_callback(void);
typedef void osdialog_restore_callback(void* ptr);
void osdialog_set_save_callback(osdialog_save_callback* cb);
void osdialog_set_restore_callback(osdialog_restore_callback* cb);