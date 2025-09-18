#! /usr/bin/env bash

# 获取当前执行文件的目录（macOS兼容方式）
current_dir=$(cd "$(dirname "$0")"; pwd)

# 删除所有.o文件
find "$current_dir"/ -type f -name "*.o" -exec rm -f {} +

# 判断系统架构（macOS使用不同架构标识）
arch=$(uname -m)
if [ "$arch" = "x86_64" ]; then
    # Intel 64位架构
    dylib_file="$current_dir/../lib/macos/x86_64/PebView.dylib"
    echo "检测到 Intel 64位架构,目标文件: $dylib_file"
    # 为Intel架构设置额外标志
    extra_flags="-arch x86_64"
elif [ "$arch" = "arm64" ]; then
    # Apple Silicon 架构
    dylib_file="$current_dir/../lib/macos/arm64/PebView.dylib"
    echo "检测到 Apple Silicon 架构,目标文件: $dylib_file"
    # 为ARM架构设置额外标志
    extra_flags="-arch arm64"
else
    echo "不支持的架构: $arch"
    exit 1
fi

# 确保目标目录存在
mkdir -p "$(dirname "$dylib_file")"

# 删除现有动态库
if [ -f "$dylib_file" ]; then
    echo "删除现有库文件: $dylib_file"
    rm -f "$dylib_file"
fi

FLAGS="-Wall -Wextra -pedantic"
CFLAGS="-std=c99 $FLAGS"
CXXFLAGS="-DWEBVIEW_COCOA -std=c++11 $FLAGS -framework WebKit"
CXXSHARED="-DWEBVIEW_COCOA -std=c++11 $FLAGS -framework WebKit -fPIC -O3 -shared"

# 定义对象文件和包含路径
icon_o="$current_dir/seticon/icon.o"
dialog_o="$current_dir/dialog/osdialog_mac.o"
webview_o="$current_dir/webview/webview.o"

icon_i="$current_dir/seticon"
dialog_i="$current_dir/dialog"
webview_i="$current_dir/webview"

echo "编译 icon.c..."
gcc $extra_flags $FLAGS -c "$current_dir/seticon/icon.c" -o "$icon_o" -I"$icon_i" -fPIC
if [ $? -ne 0 ]; then
    echo "编译 icon.c 失败!"
    exit 1
fi

echo "编译 osdialog_mac.m..."
gcc $extra_flags $CXXSHARED -c "$current_dir/dialog/osdialog_mac.m" -o "$dialog_o" -I"$dialog_i"
if [ $? -ne 0 ]; then
    echo "编译 osdialog_mac.m 失败!"
    exit 1
fi

echo "编译 webview.cc..."
c++ $extra_flags $CXXSHARED -I"$webview_i" \
    -c "$current_dir/webview/webview.cc" -o "$webview_o" -fPIC
if [ $? -ne 0 ]; then
    echo "编译 webview.cc 失败!"
    exit 1
fi

# 链接生成动态库（添加必要的框架）
echo "链接动态库..."
c++ $extra_flags $CXXSHARED "$webview_o" "$icon_o" "$dialog_o" -o "$dylib_file"

# 检查最终库文件
echo "生成的动态库信息:"
ls -lh "$dylib_file"
file "$dylib_file"

echo "构建过程完成!"