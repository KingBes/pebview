## 介绍

PebView 是一个轻量级原生网络视图包装器，允许在它自己的原生 GUI 窗口中显示 HTML 内容。它使你在桌面应用程序中拥有网络技术的力量，同时隐藏了 GUI 的事实是基于浏览器的。

PebView 可用于 Windows、macOS、Linux GTK。它使用原生 GUI 来创建一个网络组件窗口：Windows 上使用 WinForms，macOS 上使用 Cocoa，在 Linux 上使用 GTK。如果你选择冻结你的应用程序，PebView 不会捆绑沉重的 GUI 工具包或网络渲染器，从而保持可执行文件大小小巧。

PebView 提供了窗口操作功能（如应用程序菜单和各种对话框）、Javascript ↔ PHP 的双向通信以及 DOM 支持。

## 要求

- PHP 8.2 或更高版本
- PHP-FFI 扩展
- Composer
- Windows x86_64 
- Linux x86_64 或 arrch64
- MacOS x86_64 或 arm64(待)

## 安装

```bash
composer require kingbes/pebview
```

### API

 - `use Kingbes\PebView\Window;` 窗口类->[进入详情](./Window.md)
 - `use Kingbes\PebView\Dialog;` 对话框类->[进入详情](./Dialog.md)

### 枚举

`use Kingbes\PebView\WindowHint;` 窗口提示

  - `WindowHint::None` 自由变换
  - `WindowHint::Min` 最小化窗口
  - `WindowHint::Max` 最大化窗口
  - `WindowHint::Fixed` 固定窗口大小

`use Kingbes\PebView\DialogBtn;` 对话框按钮

  - `DialogBtn::Ok` 确定按钮
  - `DialogBtn::OkCancel` 确定按钮/取消按钮
  - `DialogBtn::YesNo` 是按钮/否按钮

`use Kingbes\PebView\DialogLevel;` 对话框级别

  - `DialogLevel::Info` 信息对话框
  - `DialogLevel::Warning` 警告对话框
  - `DialogLevel::Error` 错误对话框

`use Kingbes\PebView\FileAction;` 文件操作

 - `FileAction::Open` 打开文件
 - `FileAction::OpenDir` 打开目录
 - `FileAction::Save` 保存文件

### 编译

有关先决条件，请阅读[link](https://github.com/webview/webview#prerequisites)

```bash
./source/build.cmd // windows

./source/linux.sh // linux

./source/macos.sh // macos
```