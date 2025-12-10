#!/bin/bash

# 编译PebView.so 和 Toast.so要求
# 1. 安装 gcc, g++, cmake, pkg-config, gtk+-3.0, webkit2gtk-4.1 libnotify-dev
# 2. 设置环境变量 PATH 包含 gcc, g++, cmake, pkg-config 目录
# 3. 设置环境变量 PKG_CONFIG_PATH 包含 gtk+-3.0, webkit2gtk-4.1 目录

# 获取当前执行文件的目录
current_dir=$(dirname "$(readlink -f "$0")")

# 删除所有.o文件
find "$current_dir"/ -type f -name "*.o" -exec rm -f {} +

# 判断系统架构
arch=$(uname -m)
if [ "$arch" == "x86_64" ]; then
    # 64位架构
    PebView_dll_file="$current_dir/../lib/linux/x86_64/PebView.so"
    echo "检测到 x86_64 架构，目标文件: $PebView_dll_file"
    Toast_dll_file="$current_dir/../lib/linux/x86_64/Toast.so"
    echo "检测到 x86_64 架构，目标文件: $Toast_dll_file"
elif [ "$arch" == "aarch64" ]; then
    # ARM64架构
    PebView_dll_file="$current_dir/../lib/linux/aarch64/PebView.so"
    echo "检测到 aarch64 架构，目标文件: $PebView_dll_file"
    Toast_dll_file="$current_dir/../lib/linux/aarch64/Toast.so"
    echo "检测到 aarch64 架构，目标文件: $Toast_dll_file"
else
    echo "不支持的架构: $arch"
    exit 1
fi

# 确保目标目录存在
mkdir -p "$(dirname "$PebView_dll_file")"
mkdir -p "$(dirname "$Toast_dll_file")"

# 删除动态链接库
if [ -f "$PebView_dll_file" ]; then
    echo "删除现有库文件: $PebView_dll_file"
    rm -f "$PebView_dll_file"
fi
if [ -f "$Toast_dll_file" ]; then
    echo "删除现有库文件: $Toast_dll_file"
    rm -f "$Toast_dll_file"
fi

# 获取GTK编译和链接选项
echo "获取GTK编译选项..."
gtk_cflags=$(pkg-config --cflags gtk+-3.0 webkit2gtk-4.1)
echo "GTK CFLAGS: $gtk_cflags"

echo "获取GTK链接选项..."
gtk_libs=$(pkg-config --libs gtk+-3.0 webkit2gtk-4.1)
echo "GTK LIBS: $gtk_libs"

# 定义对象文件和包含路径
icon_o="$current_dir/seticon/icon.o"
dialog_o="$current_dir/dialog/osdialog_gtk.o"
webview_o="$current_dir/webview/webview.o"
window_o="$current_dir/window/window_gtk.o"

icon_i="$current_dir/seticon"
dialog_i="$current_dir/dialog"
webview_i="$current_dir/webview"
window_i="$current_dir/window"

# 编译各个组件
echo "编译 icon.c..."
gcc -Wall -Wextra -pedantic -c "$current_dir/seticon/icon.c" -o "$icon_o" -I"$icon_i" $gtk_cflags -fPIC
if [ $? -ne 0 ]; then
    echo "编译 icon.c 失败!"
    exit 1
fi

echo "编译 osdialog_gtk.c..."
gcc -Wall -Wextra -pedantic -c "$current_dir/dialog/osdialog_gtk.c" -o "$dialog_o" -I"$dialog_i" $gtk_cflags -fPIC
if [ $? -ne 0 ]; then
    echo "编译 osdialog_gtk.c 失败!"
    exit 1
fi

echo "编译 webview.cc..."
c++ -DWEBVIEW_STATIC -std=c++11 -fvisibility=default -fvisibility-inlines-hidden \
    -Wall -Wextra -pedantic -I"$webview_i" \
    -c "$current_dir/webview/webview.cc" -o "$webview_o" $gtk_cflags -fPIC
if [ $? -ne 0 ]; then
    echo "编译 webview.cc 失败!"
    exit 1
fi

echo "编译 window_gtk.c..."
gcc -Wall -Wextra -pedantic -c "$current_dir/window/window_gtk.c" -o "$window_o" -I"$window_i" $gtk_cflags -fPIC
if [ $? -ne 0 ]; then
    echo "编译 window_gtk.c 失败!"
    exit 1
fi

# 检查对象文件
echo "对象文件信息:"
ls -lh "$icon_o" "$dialog_o" "$webview_o" "$window_o"
echo "对象文件大小:"
du -h "$icon_o" "$dialog_o" "$webview_o" "$window_o"

# 检查对象文件中的符号
echo "检查 webview.o 中的符号..."
nm -gC "$webview_o" | head -n 20

echo "检查 window.o 中的符号..."
nm -gC "$window_o" | head -n 20

# 链接生成共享库 - 使用详细输出
echo "链接共享库..."
# 尝试多种链接选项
link_success=0

echo "基本链接..."
g++ -shared -o "$PebView_dll_file" "$webview_o" "$icon_o" "$dialog_o" "$window_o" $gtk_libs -ldl -lstdc++

# 检查最终库文件大小
echo "生成的共享库信息:"
ls -lh "$PebView_dll_file"
echo "共享库大小:"
du -h "$PebView_dll_file"

# 验证共享库类型
echo "验证共享库类型:"
file "$PebView_dll_file"

# 检查共享库中的符号
echo "检查共享库中的关键符号..."
nm -gC "$PebView_dll_file" | grep -E 'icon|osdialog|webview|window' 

echo "构建 PebView.so 完成!"

echo "构建 Toast.so 开始..."
toast_o="$current_dir/toast/linux/toast.o"
toast_i="$current_dir/toast"

toast_cflags=$(pkg-config --cflags gtk+-3.0 libnotify)

g++ -Wall -Wextra -pedantic -c "$current_dir/toast/linux/toast.c" -o "$toast_o" -I"$toast_i" $toast_cflags -fPIC

g++ -shared -o "$Toast_dll_file" "$toast_o" $toast_cflags -fPIC

echo "检查 Toast.so 中的符号..."
nm -gC "$Toast_dll_file" | grep -E 'toastShow'

echo "构建 Toast.so 完成!"