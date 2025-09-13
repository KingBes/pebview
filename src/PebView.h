typedef struct PebView PebView;

PebView *pv_create(bool debug);
void pv_destroy(PebView *wv);
void pv_run(PebView *wv);
void pv_terminate(PebView *wv);
void pv_dispatch(PebView *wv, void (*func)(void));
void pv_dispatch_ctx(PebView *wv, void (*func)(void *ctx), void *ctx);
void *pv_get_window(PebView *wv);
bool pv_set_icon(PebView *wv, const char *icon_file_path);
void pv_set_title(PebView *wv, const char *title);
void pv_set_size(PebView *wv, int width, int height, int hint);
void pv_navigate(PebView *wv, const char *url);
void pv_set_html(PebView *wv, const char *html);
void pv_init(PebView *wv, const char *code);
void pv_eval(PebView *wv, const char *code);
void pv_bind(PebView *wv, const char *name, void (*func)(char *event_id, const char *args, void *ctx), void *ctx);
void pv_return(PebView *wv, const char *event_id, int status, const char *result);
void pv_unbind(PebView *wv, const char *name);
