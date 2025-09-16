#  获取当前执行文件的目录
current_dir=$(dirname "$(readlink -f "$0")")

# 删除所有.o文件
find "$current_dir"/ -type f -name "*.o" -exec rm -f {} +

# 判断系统架构
arch=$(uname -m)
if [ "$arch" == "x86_64" ]; then
    # 64位架构
    dll_file="$current_dir/../lib/linux/x86_64/PebView.so"
elif [ "$arch" == "aarch64" ]; then
    # 32位架构
    dll_file="$current_dir/../lib/linux/aarch64/PebView.so"
fi

# 删除动态链接库
if [ -f "$dll_file" ]; then
    rm -f "$dll_file"
fi

gtk="-Ilibs $(pkg-config --cflags --libs gtk+-3.0 webkit2gtk-4.1) -ldl -fPIC"

icon_o="$current_dir/seticon/icon.o"
dialog_o="$current_dir/dialog/osdialog_gtk.o"
webview_o="$current_dir/webview/webview.o"

icon_i="$current_dir/seticon"
dialog_i="$current_dir/dialog"
webview_i="$current_dir/webview"

gcc -c "$current_dir/seticon/icon.c" -o "$icon_o" -I$icon_i $gtk
gcc -c "$current_dir/webview/webview.cc" -o "$webview_o" -I$webview_i $gtk
gcc -c "$current_dir/dialog/osdialog_gtk.c" -o "$dialog_o" -I$dialog_i $gtk

g++ -O2 --std=c++11 -shared -o "$dll_file" "$icon_o" "$dialog_o" "$webview_o" -I$icon_i -I$dialog_i -I$webview_i $gtk