#include "php++.h"
#include "webview.h"
#include "icon.h"
#include "window.h"
#include "osdialog.h"
#include "toast.h"
#include <map>

// Window API ------------------------------------------------------------------

// 定义 窗口提示 枚举
PhpEnum(WindowHint, t_int)
    EnumCase(WindowHint, None, 0)
        EnumCase(WindowHint, Min, 1)
            EnumCase(WindowHint, Max, 2)
                EnumCase(WindowHint, Fixed, 3);

webview_t wb;
void *tray;
// 关闭回调（webview_set_close_callback 无 userdata 槽位，用全局变量桥接）
static zval *close_cb = nullptr;
static zval close_this;

// 托盘菜单回调桥接（tray_menu::callback 无 userdata 槽位，用 id 映射）
struct TrayCb
{
    zval *cb;
    zval self;
};
static std::map<int, TrayCb> tray_cb_map;

// C 回调入口：ptr 指向 tray_menu（首个字段是 int id），查找映射 → 调用 PHP
static void TrayCbBrig(const void *ptr)
{
    int id = *(int *)ptr;
    auto it = tray_cb_map.find(id);
    if (it != tray_cb_map.end() && it->second.cb)
    {
        zval retval = p_func(it->second.cb).add(&it->second.self).call();
        zval_ptr_dtor(&retval);
    }
}

// 定义Window类
Class(Window);

/**
 * @brief 定义构造函数
 *
 * @param bool debug 是否开启调试模式
 */
Method(Window, __construct, t_void,
       Arg(t_bool, "debug"))
{
    // 构造函数
    bool debug = GetArgBool(1);
    wb = webview_create(debug, NULL);
}

/**
 * @brief 定义销毁窗口函数
 *
 */
Method(Window, __destruct, t_void)
{
    // 销毁窗口
    webview_destroy(wb);
}

/**
 * @brief 定义运行窗口函数
 *
 * @return t_Object
 */
Method(Window, run, t_object)
{
    // 运行窗口
    webview_run(wb);
    RetThis();
}

/**
 * @brief 定义终止窗口函数
 *
 */
Method(Window, terminate, t_void)
{
    window_tray_remove(tray);
    // 终止窗口
    webview_terminate(wb);
}

/**
 * @brief 定义设置窗口图标函数
 * @param string iconPath 图标文件路径
 * @return t_Object
 */
Method(Window, setIcon, t_object,
       Arg(t_str, "iconPath"))
{
    const char *iconPath = GetArgStr(1);
    enum SetIconErrorCode code = set_icon(webview_get_window(wb), iconPath);
    if (code == WINDOW_NOT_FOUND)
    {
        PhpError("Window not found");
    }
    else if (code == ICON_NOT_FOUND)
    {
        PhpError("Icon file not found");
    }
    else if (code == OS_UNSUPPORTED)
    {
        PhpError("Operating system not supported");
    }
    RetThis();
}

/**
 * @brief 定义设置窗口标题函数
 *
 * @param string title 窗口标题
 * @return t_Object
 */
Method(Window, setTitle, t_object,
       Arg(t_str, "title"))
{
    const char *title = GetArgStr(1);
    webview_set_title(wb, title);
    RetThis();
}

/**
 * @brief 定义设置窗口大小函数
 *
 * @param int width 窗口宽度
 * @param int height 窗口高度
 * @return t_Object
 */
Method(Window, setSize, t_object,
       Arg(t_int, "width")
           Arg(t_int, "height")
               ArgEnum(WindowHint, "hint"))
{
    int width = GetArgInt(1);
    int height = GetArgInt(2);
    int hint = GetArgEnumIntDfl(3, 0);
    webview_hint_t hint_val;
    if (hint == 0)
    {
        hint_val = WEBVIEW_HINT_NONE;
    }
    else if (hint == 1)
    {
        hint_val = WEBVIEW_HINT_MIN;
    }
    else if (hint == 2)
    {
        hint_val = WEBVIEW_HINT_MAX;
    }
    else if (hint == 3)
    {
        hint_val = WEBVIEW_HINT_FIXED;
    }
    else
    {
        hint_val = WEBVIEW_HINT_NONE;
    }
    webview_set_size(wb, width, height, hint_val);
    RetThis();
}

/**
 * @brief 初始化窗口
 *
 * @param string js 初始化js代码
 */
Method(Window, init, t_object,
       Arg(t_str, "js"))
{
    webview_init(wb, GetArgStr(1));
    RetThis();
}

/**
 * @brief 定义执行js代码函数
 *
 * @param string js js代码
 * @return t_Object
 */
Method(Window, eval, t_object,
       Arg(t_str, "js"))
{
    webview_eval(wb, GetArgStr(1));
    RetThis();
}

/**
 * @brief 定义设置html内容函数
 *
 * @param string html html内容
 * @return t_Object
 */
Method(Window, setHtml, t_object,
       Arg(t_str, "html"))
{
    const char *html = GetArgStr(1);
    webview_set_html(wb, html);
    RetThis();
}

/**
 * @brief 定义导航函数
 *
 * @param string url 导航url
 * @return t_Object
 */
Method(Window, navigate, t_object,
       Arg(t_str, "url"))
{
    const char *url = GetArgStr(1);
    webview_navigate(wb, url);
    RetThis();
}

/**
 * @brief 定义绑定js函数
 *
 * @param string name 绑定函数名称
 * @param callable callback PHP回调函数，接收一个参数（JS传来的JSON字符串），返回值会被传回JS
 * @return t_Object
 */
Method(Window, bind, t_object,
       Arg(t_str, "name")
           Arg(t_func, "callback"))
{
    const char *name = GetArgStr(1);
    zval *cb = PhpCbDup(_C(2));

    webview_bind(wb, name, [](const char *s, const char *r, void *a)
                 {
                    // ① 解码 JS 传来的 JSON 参数
                    zval decoded;
                    PhpCall("json_decode", &decoded, r ? r : "", true);

                    // ② 调用 PHP 回调: callback($seq, $decoded)，拿到返回值
                    zval retval = p_func((zval *)a).add(&decoded).call();
                    zval_ptr_dtor(&decoded);

                    // ③ 返回值统一 json_encode，传给 JS
                    zval json;
                    PhpCall("json_encode", &json, &retval);
                    zval_ptr_dtor(&retval);

                    webview_return(wb, s, 0,
                        Z_TYPE(json) == t_str ? Z_STRVAL(json) : "");
                    zval_ptr_dtor(&json); }, cb);

    RetThis();
}

/**
 * @brief 解绑js函数
 *
 * @param string name 绑定函数名称
 * @return t_Object
 */
Method(Window, unBind, t_object,
       Arg(t_str, "name"))
{
    const char *name = GetArgStr(1);
    webview_unbind(wb, name);
    RetThis();
}

/**
 * @brief 定义设置窗口关闭回调函数
 *
 * @param callable callback PHP回调函数，返回值为true时允许窗口关闭，false时阻止窗口关闭
 * @return t_Object
 */
Method(Window, setCloseCallback, t_object,
       Arg(t_func, "callback"))
{
    // 释放旧回调
    if (close_cb)
    {
        zval_ptr_dtor(close_cb);
    }
    close_cb = PhpCbDup(_C(1));

    // 存储 $this（close 回调触发时 getThis() 不可用）
    zval_ptr_dtor(&close_this);
    ZVAL_COPY(&close_this, getThis());

    webview_set_close_callback(wb, [](void * /*unused, always nullptr*/) -> int
                               {
        if (!close_cb) return 0;
        zval retval = p_func(close_cb).add(&close_this).call();
        int result = (Z_TYPE(retval) == IS_TRUE) ? 1 : 0;  // true=关闭, false=阻止
        zval_ptr_dtor(&retval);
        return result; });
    RetThis();
}

/**
 * @brief 定义显示窗口函数
 * @return t_Object
 */
Method(Window, show, t_object)
{
    window_show(webview_get_window(wb));
    RetThis();
}

/**
 * @brief 定义隐藏窗口函数
 * @return t_Object
 */
Method(Window, hide, t_object)
{
    window_hide(webview_get_window(wb));
    RetThis();
}

/**
 * @brief 定义创建托盘函数
 *
 * @param string icon 托盘图标路径
 * @return t_Object
 */
Method(Window, tray, t_object,
       Arg(t_str, "icon"))
{
    const char *icon = GetArgStr(1);
    tray = window_tray(wb, icon);
    RetThis();
}

/**
 * @brief 定义添加托盘菜单函数
 *
 * @arg array menus 菜单项数组，每项为关联数组，支持键：text（字符串）、id（整数，可选）、disabled（布尔值，可选）、checked（布尔值，可选）、cb（PHP回调函数，可选）
 */
Method(Window, trayMenu, t_object,
       Arg(t_array, "menus"))
{
    p_arr arr = GetArgArr(1);
    zval *self = getThis();
    static int idx = 1;

    arr.values([&](zval *val)
               {
        if (PType(val) != IS_ARRAY) return;
        HashTable* ht = Z_ARRVAL_P(val);

        struct tray_menu menu = {0};
        menu.id = idx++;

        zval* zv;
        if ((zv = zend_hash_str_find(ht, "text",  4)) && Z_TYPE_P(zv) == IS_STRING) menu.text     = (char*)Z_STRVAL_P(zv);
        if ((zv = zend_hash_str_find(ht, "id",    2)) && Z_TYPE_P(zv) == IS_LONG)   menu.id       = (int)Z_LVAL_P(zv);
        if ((zv = zend_hash_str_find(ht, "disabled", 8)) && Z_TYPE_P(zv) == IS_TRUE)   menu.disabled = 1;
        if ((zv = zend_hash_str_find(ht, "checked",  7)) && Z_TYPE_P(zv) == IS_TRUE)   menu.checked  = 1;

        if ((zv = zend_hash_str_find(ht, "cb", 2)) && Z_TYPE_P(zv) == IS_OBJECT) {
            auto& slot = tray_cb_map[menu.id];
            if (slot.cb) zval_ptr_dtor(slot.cb);
            zval_ptr_dtor(&slot.self);
            slot.cb   = PhpCbDup(zv);
            ZVAL_COPY(&slot.self, self);
            menu.callback = TrayCbBrig;
        }

        window_tray_add_menu(tray, &menu); });

    RetThis();
}

// Dialog API ------------------------------------------------------------------

PhpEnum(DialogLevel, t_int)
    EnumCase(DialogLevel, Info, 0)
        EnumCase(DialogLevel, Warning, 1)
            EnumCase(DialogLevel, Error, 2);

PhpEnum(DialogBtn, t_int)
    EnumCase(DialogBtn, Ok, 0)
        EnumCase(DialogBtn, OkCancel, 1)
            EnumCase(DialogBtn, YesNo, 2);

PhpEnum(FileAction, t_int)
    EnumCase(FileAction, Open, 0)
        EnumCase(FileAction, OpenDir, 1)
            EnumCase(FileAction, Save, 2);

Class(Dialog);
StaticMethod(Dialog, msg, t_bool,
             Arg(t_str, "message")
                 ArgEnum(DialogLevel, "level")
                     ArgEnum(DialogBtn, "buttons"))
{
    const char *message = GetArgStr(1);
    int level = GetArgEnumIntDfl(2, 0);
    int buttons = GetArgEnumIntDfl(3, 0);

    osdialog_message_level msg_level;
    switch (level)
    {
    case 0:
        msg_level = OSDIALOG_INFO;
        break;
    case 1:
        msg_level = OSDIALOG_WARNING;
        break;
    case 2:
        msg_level = OSDIALOG_ERROR;
        break;
    default:
        msg_level = OSDIALOG_INFO;
    }

    osdialog_message_buttons msg_buttons;
    switch (buttons)
    {
    case 0:
        msg_buttons = OSDIALOG_OK;
        break;
    case 1:
        msg_buttons = OSDIALOG_OK_CANCEL;
        break;
    case 2:
        msg_buttons = OSDIALOG_YES_NO;
        break;
    default:
        msg_buttons = OSDIALOG_OK;
    }
    bool result = osdialog_message(msg_level, msg_buttons, message) == 1;
    RetBool(result);
}

StaticMethod(Dialog, prompt, t_str,
             Arg(t_str, "message")
                 ArgEnum(DialogLevel, "level")
                     Arg(t_str, "text"))
{
    const char *message = GetArgStr(1);
    int level = GetArgEnumIntDfl(2, 0);
    const char *text = GetArgStrDfl(3, "");

    osdialog_message_level msg_level;
    switch (level)
    {
    case 0:
        msg_level = OSDIALOG_INFO;
        break;
    case 1:
        msg_level = OSDIALOG_WARNING;
        break;
    case 2:
        msg_level = OSDIALOG_ERROR;
        break;
    default:
        msg_level = OSDIALOG_INFO;
    }

    char *result = osdialog_prompt(msg_level, message, text);
    if (result)
    {
        RetStr(result);
        free(result);
    }
    else
    {
        RetStr("");
    }
}

StaticMethod(Dialog, file, t_str,
             Arg(t_str, "dir")
                 Arg(t_str, "filename")
                     ArgEnum(FileAction, "action")
                         Arg(t_str, "filters"))
{
    const char *dir = GetArgStr(1);
    const char *filename = GetArgStr(2);
    int action = GetArgEnumInt(3);
    const char *filters_str = GetArgStrDfl(4, NULL);

    osdialog_file_action file_action;
    switch (action)
    {
    case 0:
        file_action = OSDIALOG_OPEN;
        break;
    case 1:
        file_action = OSDIALOG_OPEN_DIR;
        break;
    case 2:
        file_action = OSDIALOG_SAVE;
        break;
    default:
        file_action = OSDIALOG_OPEN;
    }

    osdialog_filters *filters = nullptr;
    if (filters_str)
    {
        filters = osdialog_filters_parse(filters_str);
    }

    char *result = osdialog_file(file_action, dir, filename, filters);
    if (filters)
    {
        osdialog_filters_free(filters);
    }
    if (result)
    {
        RetStr(result);
        free(result);
    }
    else
    {
        RetStr("");
    }
}

// Toast API ------------------------------------------------------------------
Class(Toast);
StaticMethod(Toast, show, t_bool,
             Arg(t_str, "app")
                 Arg(t_str, "title")
                     Arg(t_str, "message")
                         Arg(t_str, "icon"))
{
    const char *app = GetArgStr(1);
    const char *title = GetArgStr(2);
    const char *message = GetArgStr(3);
    const char *icon = GetArgStrDfl(4, NULL);

    bool result = toastShow(app, title, message, icon);
    RetBool(result);
}

Module("pebview", "v0.0.1", "Kingbes\\PebView");