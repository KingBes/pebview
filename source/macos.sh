#!/bin/bash

# 优化版 PebView macOS 构建脚本
# 支持架构检测、通用二进制、完善的错误处理和资源清理

# 设置中文字符支持
export LANG="zh_CN.UTF-8"
export LC_ALL="zh_CN.UTF-8"

# 工具函数定义
function log_info() {
    echo "[INFO] $1"
}

function log_error() {
    echo "[ERROR] $1" >&2
}

function check_dependencies() {
    # 检查必要的编译工具
    local tools=('gcc' 'clang++' 'file' 'ls' 'mkdir' 'rm' 'find')
    local missing=0
    
    for tool in "${tools[@]}"; do
        if ! command -v "$tool" &> /dev/null; then
            log_error "缺少必要的工具: $tool"
            missing=1
        fi
    done
    
    return $missing
}

function clean_build() {
    # 清理构建环境
    log_info "清理构建环境..."
    find "$current_dir" -type f -name "*.o" -exec rm -f {} \;
    if [ -f "$dylib_file" ]; then
        log_info "删除现有库文件: $dylib_file"
        rm -f "$dylib_file"
    fi
}

function build_library() {
    # 编译单个文件
    local compiler="$1"
    local flags="$2"
    local source="$3"
    local output="$4"
    local includes="$5"
    
    log_info "编译 $source..."
    $compiler $flags $includes -c "$source" -o "$output"
    
    if [ $? -ne 0 ]; then
        log_error "编译 $source 失败!"
        return 1
    fi
    
    return 0
}

# 主函数
function main() {
    # 获取当前执行文件的目录（macOS兼容方式）
    current_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    
    # 检查依赖工具
    if ! check_dependencies; then
        log_error "请安装缺少的工具后重试"
        exit 1
    fi
    
    # 判断系统架构
    arch="$(uname -m)"
    lib_dir="$current_dir/../lib/macos"
    
    case "$arch" in
        x86_64)
            # Intel 64位架构
            dylib_file="$lib_dir/x86_64/PebView.dylib"
            log_info "检测到 Intel 64位架构,目标文件: $dylib_file"
            extra_flags="-arch x86_64"
            ;;
        arm64)
            # Apple Silicon 架构
            dylib_file="$lib_dir/arm64/PebView.dylib"
            log_info "检测到 Apple Silicon 架构,目标文件: $dylib_file"
            extra_flags="-arch arm64"
            ;;
        *)
            log_error "不支持的架构: $arch"
            exit 1
            ;;
    esac
    
    # 确保目标目录存在
    mkdir -p "$(dirname "$dylib_file")" "$current_dir/seticon" "$current_dir/dialog" "$current_dir/webview" "$current_dir/window"
    
    # 清理构建环境
    clean_build
    
    # 统一编译标志
    COMMON_FLAGS="-Wall -Wextra -pedantic -O3 -mmacosx-version-min=10.10"
    CFLAGS="$COMMON_FLAGS -std=c99"
    CXXFLAGS="$COMMON_FLAGS -DWEBVIEW_STATIC -std=c++11"
    OBJCFLAGS="$COMMON_FLAGS -DWEBVIEW_COCOA"
    
    # 链接标志
    LDFLAGS="-ObjC"
    
    # macOS 框架
    FRAMEWORKS="-framework WebKit -framework Cocoa -framework Carbon"
    
    # 定义对象文件和包含路径
    icon_o="$current_dir/seticon/icon.o"
    dialog_o="$current_dir/dialog/osdialog_mac.o"
    dialogc_o="$current_dir/dialog/osdialog.o"
    webview_o="$current_dir/webview/webview.o"
    window_o="$current_dir/window/window_mac.o"
    
    icon_i="-I$current_dir/seticon"
    dialog_i="-I$current_dir/dialog"
    webview_i="-I$current_dir/webview"
    window_i="-I$current_dir/window"
    
    # 编译源文件
    if ! build_library "gcc" "$extra_flags $CFLAGS" "$current_dir/seticon/icon.c" "$icon_o" "$icon_i"; then
        exit 1
    fi

    if ! build_library "clang" "$extra_flags $OBJCFLAGS" "$current_dir/dialog/osdialog.c" "$dialogc_o" "$dialog_i"; then
        exit 1
    fi

    if ! build_library "clang" "$extra_flags $OBJCFLAGS" "$current_dir/window/window_mac.m" "$window_o" "$window_i"; then
        exit 1
    fi

    if ! build_library "clang" "$extra_flags $OBJCFLAGS" "$current_dir/dialog/osdialog_mac.m" "$dialog_o" "$dialog_i"; then
        exit 1
    fi
    
    if ! build_library "c++" "$extra_flags $CXXFLAGS" "$current_dir/webview/webview.cc" "$webview_o" "$webview_i"; then
        exit 1
    fi
    
    # 链接生成动态库
    log_info "链接动态库..."
    clang++ $extra_flags -dynamiclib $LDFLAGS -install_name "@rpath/$(basename "$dylib_file")" -o "$dylib_file" "$webview_o" "$icon_o" "$dialogc_o" "$dialog_o" "$window_o" $FRAMEWORKS
    
    if [ $? -ne 0 ]; then
        log_error "链接动态库失败!"
        exit 1
    fi
    
    # 检查最终库文件
    if [ -f "$dylib_file" ]; then
        log_info "生成的动态库信息:"
        ls -lh "$dylib_file"
        file "$dylib_file"
        nm -g "$dylib_file"
        log_info "构建过程完成!"
        return 0
    else
        log_error "动态库文件未生成!"
        return 1
    fi
}

# 执行主函数
main

# 设置脚本执行权限（在首次运行时自动设置）
chmod +x "$0"