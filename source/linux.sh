#!/bin/bash

# 获取当前执行文件的目录
current_dir=$(dirname "$(readlink -f "$0")")

# 删除所有.o文件
find "$current_dir"/ -type f -name "*.o" -exec rm -f {} +

# 判断系统架构
arch=$(uname -m)
if [ "$arch" == "x86_64" ]; then
    # 64位架构
    dll_file="$current_dir/../lib/linux/x86_64/PebView.so"
    echo "检测到 x86_64 架构，目标文件: $dll_file"
elif [ "$arch" == "aarch64" ]; then
    # ARM64架构
    dll_file="$current_dir/../lib/linux/aarch64/PebView.so"
    echo "检测到 aarch64 架构，目标文件: $dll_file"
else
    echo "不支持的架构: $arch"
    exit 1
fi

# 确保目标目录存在
mkdir -p "$(dirname "$dll_file")"

# 删除动态链接库
if [ -f "$dll_file" ]; then
    echo "删除现有库文件: $dll_file"
    rm -f "$dll_file"
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

icon_i="$current_dir/seticon"
dialog_i="$current_dir/dialog"
webview_i="$current_dir/webview"

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
g++ -DWEBVIEW_GTK -std=c++11 -Wall -Wextra -pedantic -I"$webview_i" -c "$current_dir/webview/webview.cc" -o "$webview_o" $gtk_cflags -fPIC
if [ $? -ne 0 ]; then
    echo "编译 webview.cc 失败!"
    exit 1
fi

# 检查对象文件
echo "对象文件信息:"
ls -lh "$icon_o" "$dialog_o" "$webview_o"
echo "对象文件大小:"
du -h "$icon_o" "$dialog_o" "$webview_o"

# 检查对象文件中的符号
echo "检查 webview.o 中的符号..."
nm -gC "$webview_o" | head -n 20

# 链接生成共享库 - 使用详细输出
echo "链接共享库..."
# 尝试多种链接选项
link_success=0

# 选项1: 基本链接
echo "尝试选项1: 基本链接..."
g++ -v -shared -o "$dll_file" "$icon_o" "$dialog_o" "$webview_o" $gtk_libs -ldl
if [ $? -eq 0 ]; then
    link_success=1
    echo "选项1 链接成功!"
else
    echo "选项1 链接失败，尝试选项2..."
    
    # 选项2: 添加 C++ 标准库
    echo "尝试选项2: 添加 C++ 标准库..."
    g++ -v -shared -o "$dll_file" "$icon_o" "$dialog_o" "$webview_o" $gtk_libs -lstdc++ -ldl
    if [ $? -eq 0 ]; then
        link_success=1
        echo "选项2 链接成功!"
    else
        echo "选项2 链接失败，尝试选项3..."
        
        # 选项3: 使用 gcc 链接并添加所有必要库
        echo "尝试选项3: 使用 gcc 链接..."
        gcc -v -shared -o "$dll_file" "$icon_o" "$dialog_o" "$webview_o" $gtk_libs -lstdc++ -ldl -lpthread
        if [ $? -eq 0 ]; then
            link_success=1
            echo "选项3 链接成功!"
        else
            echo "选项3 链接失败..."
        fi
    fi
fi

# 如果所有尝试都失败，尝试最小链接
if [ $link_success -ne 1 ]; then
    echo "所有链接尝试都失败，尝试最小链接..."
    g++ -v -shared -o "$dll_file" "$webview_o" $gtk_libs -ldl
    if [ $? -eq 0 ]; then
        link_success=1
        echo "最小链接成功!"
    else
        echo "最小链接失败!"
        exit 1
    fi
fi

# 检查最终库文件大小
echo "生成的共享库信息:"
ls -lh "$dll_file"
echo "共享库大小:"
du -h "$dll_file"

# 验证共享库类型
echo "验证共享库类型:"
file "$dll_file"

# 检查共享库依赖
echo "检查共享库依赖:"
ldd "$dll_file"

# 检查共享库中的符号
echo "检查共享库中的关键符号..."
nm -gC "$dll_file" | grep -E 'icon|osdialog|webview' | head -n 20

echo "构建过程完成!"