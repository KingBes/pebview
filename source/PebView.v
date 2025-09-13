import ttytm.webview

// v -cc gcc -shared -o .\lib\PebView.dll .\source\PebView.v

// 创建
@[export:'pv_create']
fn create(debug bool) &webview.Webview
{
	return webview.create(debug:debug)
}

// 销毁
@[export:'pv_destroy']
fn destroy(wv &webview.Webview)
{
	wv.destroy()
}

// 运行
@[export:'pv_run']
fn run(wv &webview.Webview)
{
	wv.run()
}

// 停止
@[export:'pv_terminate']
fn terminate(wv &webview.Webview)
{
	wv.terminate()
}

// 分发
@[export:'pv_dispatch']
fn dispatch(wv &webview.Webview,func fn())
{
	wv.dispatch(func)
}

// 分发上下文
@[export:'pv_dispatch_ctx']
fn dispatch_ctx(wv &webview.Webview,func fn (ctx voidptr), ctx voidptr)
{
	wv.dispatch_ctx(func,ctx)
}

// 获取窗口
@[export:'pv_get_window']
fn get_window(wv &webview.Webview) voidptr
{
	return wv.get_window()
}

// 设置小图标
@[export:'pv_set_icon']
fn set_icon(wv &webview.Webview,icon_file_path &char) bool
{
	v_str := unsafe{cstring_to_vstring(icon_file_path)}
	wv.set_icon(v_str) or {
		return false
	}
	return true
}

// 设置标题
@[export:'pv_set_title']
fn set_title(wv &webview.Webview,title &char)
{
	v_str := unsafe{cstring_to_vstring(title)}
	wv.set_title(v_str)
}

// 设置大小
@[export:'pv_set_size']
fn set_size(wv &webview.Webview,width int,height int, hint &webview.Hint)
{
	wv.set_size(width,height,hint)
}

// 导航
@[export:'pv_navigate']
fn navigate(wv &webview.Webview,url &char)
{
	v_str := unsafe{cstring_to_vstring(url)}
	wv.navigate(v_str)
}

// 设置HTML
@[export:'pv_set_html']
fn set_html(wv &webview.Webview,html &char)
{
	v_str := unsafe{cstring_to_vstring(html)}
	wv.set_html(v_str)
}

// 初始化
@[export:'pv_init']
fn init(wv &webview.Webview,code &char)
{
	v_str := unsafe{cstring_to_vstring(code)}
	wv.init(v_str)
}

// 执行JS
@[export:'pv_eval']
fn eval(wv &webview.Webview,code &char)
{
	v_str := unsafe{cstring_to_vstring(code)}
	wv.eval(v_str)
}

// 绑定事件
@[export:'pv_bind']
fn bind(wv &webview.Webview,name &char,func fn (event_id &char, args &char, ctx voidptr), ctx voidptr)
{
	webview.webview_bind(wv,name,func,ctx)
}

// 返回
@[export:'pv_return']
fn returns(wv &webview.Webview,event_id &char, status int, result &char)
{
	webview.webview_return(wv,event_id,status,result)
}

// 解绑事件
@[export:'pv_unbind']
fn unbind(wv &webview.Webview,name &char)
{
	v_str := unsafe{cstring_to_vstring(name)}
	wv.unbind(v_str)
}