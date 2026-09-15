#!/bin/bash
set -e

# 构建 PebView.so（窗口 / 对话框 / 通知合并为单个动态库）
#
# 要求：
#   1. gcc, g++, pkg-config
#   2. GTK 开发包：gtk+-3.0, webkit2gtk-4.1
#   3. 通知开发包：libnotify
#   4. PKG_CONFIG_PATH 能定位到上述 .pc 文件

# 获取当前执行文件的目录
current_dir=$(dirname "$(readlink -f "$0")")

# 判断系统架构
arch=$(uname -m)
case "$arch" in
    x86_64)
        lib_dir="$current_dir/../lib/linux/x86_64"
        ;;
    aarch64)
        lib_dir="$current_dir/../lib/linux/aarch64"
        ;;
    *)
        echo "不支持的架构: $arch"
        exit 1
        ;;
esac

out_so="$lib_dir/PebView.so"
echo "[INFO] 架构: $arch"
echo "[INFO] 产物: $out_so"

# 清理上一次的中间产物
find "$current_dir" -type f -name "*.o" -exec rm -f {} +
rm -f "$out_so"
mkdir -p "$lib_dir"

# 依赖的编译 / 链接选项
echo "[INFO] 读取 GTK 编译选项..."
gtk_cflags=$(pkg-config --cflags gtk+-3.0 webkit2gtk-4.1)
echo "[INFO] 读取 GTK 链接选项..."
gtk_libs=$(pkg-config --libs gtk+-3.0 webkit2gtk-4.1)
echo "[INFO] 读取 libnotify 编译选项..."
notify_cflags=$(pkg-config --cflags libnotify glib-2.0)
notify_libs=$(pkg-config --libs libnotify glib-2.0)

icon_o="$current_dir/seticon/icon.o"
dialog_common_o="$current_dir/dialog/osdialog.o"
dialog_gtk_o="$current_dir/dialog/osdialog_gtk.o"
webview_o="$current_dir/webview/webview.o"
window_o="$current_dir/window/window_gtk.o"
toast_o="$current_dir/toast/linux/toast.o"

icon_i="$current_dir/seticon"
dialog_i="$current_dir/dialog"
webview_i="$current_dir/webview"
window_i="$current_dir/window"
toast_i="$current_dir/toast"

cflags="-Wall -Wextra -pedantic -fPIC -O2"

echo "[INFO] 编译 icon.c..."
gcc $cflags -c "$current_dir/seticon/icon.c" -o "$icon_o" -I"$icon_i" $gtk_cflags

echo "[INFO] 编译 osdialog.c..."
gcc $cflags -c "$current_dir/dialog/osdialog.c" -o "$dialog_common_o" -I"$dialog_i" $gtk_cflags

echo "[INFO] 编译 osdialog_gtk.c..."
gcc $cflags -c "$current_dir/dialog/osdialog_gtk.c" -o "$dialog_gtk_o" -I"$dialog_i" $gtk_cflags

echo "[INFO] 编译 webview.cc..."
c++ -DWEBVIEW_STATIC -std=c++11 -fvisibility=default -fvisibility-inlines-hidden \
    $cflags -I"$webview_i" \
    -c "$current_dir/webview/webview.cc" -o "$webview_o" $gtk_cflags

echo "[INFO] 编译 window_gtk.c..."
gcc $cflags -c "$current_dir/window/window_gtk.c" -o "$window_o" -I"$window_i" $gtk_cflags

echo "[INFO] 编译 toast.c..."
gcc $cflags -c "$current_dir/toast/linux/toast.c" -o "$toast_o" -I"$toast_i" $notify_cflags

echo "[INFO] 链接单个共享库..."
g++ -shared -o "$out_so" \
    "$webview_o" "$icon_o" "$dialog_common_o" "$dialog_gtk_o" "$window_o" "$toast_o" \
    $gtk_libs $notify_libs -ldl -lstdc++

echo "[INFO] 产物信息:"
ls -lh "$out_so"
file "$out_so"

echo "[INFO] 校验 ABI..."
if command -v php >/dev/null 2>&1; then
    php "$current_dir/check-abi.php" --def "$current_dir/exports.def" --lib "$out_so"
else
    echo "[WARN] 未找到 php，跳过 ABI 校验。可手动执行："
    echo "[WARN]   php source/check-abi.php --def source/exports.def --lib $out_so"
fi

echo "[OK] 构建 PebView.so 完成"
