#!/bin/bash
set -e

# 构建 PebView.dylib（窗口 / 对话框 / 通知合并为单个动态库）
#
# 默认同时构建 x86_64 与 arm64 两套产物：
#   - macOS SDK 允许交叉编译，因此在 arm64 机器上也能产出 x86_64，
#     无需专门的 Intel 机器（GitHub Actions 已不再提供 macOS x86_64 runner）。
#   - 也可以只构建指定架构：./macos.sh arm64
#
# 要求：Xcode Command Line Tools（clang / clang++）

current_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

log_info() { echo "[INFO] $1"; }
log_error() { echo "[ERROR] $1" >&2; }

build_arch() {
    local arch="$1"
    local lib_dir="$current_dir/../lib/macos/$arch"
    local out_dylib="$lib_dir/PebView.dylib"
    local obj_dir="$current_dir/build/macos/$arch"

    # arm64 只在 macOS 11 之后存在，两平台的版本下限分别取 SDK 允许的最低值
    local min_ver
    case "$arch" in
        x86_64) min_ver="10.13" ;;
        arm64)  min_ver="11.0" ;;
    esac

    log_info "=== 构建 $arch (min macOS $min_ver) ==="
    mkdir -p "$lib_dir"

    # 中间产物按架构分目录，避免两套架构互相覆盖
    rm -rf "$obj_dir"
    mkdir -p "$obj_dir"

    local common="-arch $arch -Wall -Wextra -pedantic -O3 -mmacosx-version-min=$min_ver"
    local cflags="$common -std=c99"
    local cxxflags="$common -DWEBVIEW_STATIC -std=c++11"
    local objcflags="$common -DWEBVIEW_COCOA"
    local frameworks="-framework WebKit -framework Cocoa -framework Carbon -framework Foundation -framework AppKit"

    log_info "编译 icon.c / osdialog.c / osdialog_mac.m / window_mac.m"
    clang   $cflags    -I"$current_dir/seticon" -c "$current_dir/seticon/icon.c" -o "$obj_dir/icon.o"
    clang   $objcflags -I"$current_dir/dialog"  -c "$current_dir/dialog/osdialog.c" -o "$obj_dir/osdialog.o"
    clang   $objcflags -I"$current_dir/dialog"  -c "$current_dir/dialog/osdialog_mac.m" -o "$obj_dir/osdialog_mac.o"
    clang   $objcflags -I"$current_dir/window"  -c "$current_dir/window/window_mac.m" -o "$obj_dir/window_mac.o"

    log_info "编译 webview.cc"
    c++     $cxxflags  -I"$current_dir/webview" -c "$current_dir/webview/webview.cc" -o "$obj_dir/webview.o"

    log_info "编译 toast.mm"
    clang++ $objcflags -I"$current_dir/toast"   -c "$current_dir/toast/macos/toast.mm" -o "$obj_dir/toast.o"

    log_info "链接单个动态库"
    clang++ -arch "$arch" -dynamiclib \
        -install_name "@rpath/PebView.dylib" \
        -o "$out_dylib" \
        "$obj_dir/webview.o" \
        "$obj_dir/icon.o" \
        "$obj_dir/osdialog.o" \
        "$obj_dir/osdialog_mac.o" \
        "$obj_dir/window_mac.o" \
        "$obj_dir/toast.o" \
        $frameworks

    log_info "产物信息"
    ls -lh "$out_dylib"
    file "$out_dylib"

    log_info "校验 ABI"
    if command -v php >/dev/null 2>&1; then
        php "$current_dir/check-abi.php" --def "$current_dir/exports.def" --lib "$out_dylib"
    else
        log_error "未找到 php，跳过 ABI 校验。可手动执行：php source/check-abi.php --def source/exports.def --lib $out_dylib"
    fi
}

targets=("$@")
if [ ${#targets[@]} -eq 0 ]; then
    targets=(x86_64 arm64)
fi

for target in "${targets[@]}"; do
    case "$target" in
        x86_64 | arm64)
            build_arch "$target"
            ;;
        *)
            log_error "不支持的架构: $target（可选 x86_64 / arm64）"
            exit 1
            ;;
    esac
done

log_info "构建完成"
