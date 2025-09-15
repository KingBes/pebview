#  获取当前执行文件的目录
current_dir=$(pwd)

# 删除所有.o文件
find "$current_dir"/ -type f -name "*.o" -exec rm -f {} +

# 判断是Linux或者macos
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    # Linux系统
    find "$current_dir"/ -type f -name "*.so" -exec rm -f {} +
    webview="-DWEBVIEW_GTK -lstdc++"
    dll="os"
else
    # macos系统
    find "$current_dir"/ -type f -name "*.dylib" -exec rm -f {} +
    webview="-DWEBVIEW_COCOA -framework WebKit -stdlib=libc++ -lstdc++"
    dll="dylib"
fi

gcc -c "$current_dir"/seticon/icon.c -o "$current_dir"/seticon/icon.o -I"$current_dir/seticon"
gcc -c "$current_dir"/webview/webview.cc -o "$current_dir"/webview/webview.o "$webview"  -I"$current_dir/webview"
gcc -c "$current_dir"/dialog/osdialog.c -o "$current_dir"/dialog/osdialog.o -I"$current_dir/dialog"
gcc -c "$current_dir"/dialog/osdialog_win.c -o "$current_dir"/dialog/osdialog_win.o -I"$current_dir/dialog"

g++ -shared -o "$current_dir"/../lib/windows/PebView.$dll "$current_dir"/seticon/icon.o "$current_dir"/webview/webview.o "$current_dir"/dialog/osdialog.o "$current_dir"/dialog/osdialog_win.o "$webview" -I"$current_dir/webview" -I"$current_dir/dialog" -I"$current_dir/seticon"
