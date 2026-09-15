## Introduction

PebView is a lightweight native webview wrapper that allows displaying HTML content in its own native GUI window. It empowers you to leverage web technologies within desktop applications while concealing the fact that the GUI is browser-based.

PebView is available for Windows, macOS, and Linux GTK. It utilizes native GUI to create a web component window: WinForms on Windows, Cocoa on macOS, and GTK on Linux. If you choose to freeze your application, PebView does not bundle heavy GUI toolkits or web rendering engines, keeping the executable file size small.

PebView provides window manipulation features (such as application menus and various dialogs), bidirectional communication between Javascript ↔ PHP, and DOM support.

## Requirements

- PHP 8.2 or later
- PHP-FFI extension
- Composer
- Windows x86_64 
- Linux x86_64 or aarch64
- MacOS x86_64 or arm64

## Installation

```bash
composer require kingbes/pebview
```

### API

 - `use Kingbes\PebView\Window;` Window Class->[View Details](./Window.md)
 - `use Kingbes\PebView\Dialog;` Dialog Class->[View Details](./Dialog.md)
 - `use Kingbes\PebView\Toast;` Toast Class->[View Details](./Toast.md)

### Enums

`use Kingbes\PebView\WindowHint;` Window Hints

  - `WindowHint::None` Free Transform
  - `WindowHint::Min` Minimize Window
  - `WindowHint::Max` Maximize Window
  - `WindowHint::Fixed` Fixed Window Size

`use Kingbes\PebView\DialogBtn;` Dialog Buttons

  - `DialogBtn::Ok` Ok Button
  - `DialogBtn::OkCancel` Ok Button/Cancel Button
  - `DialogBtn::YesNo` Yes Button/No Button

`use Kingbes\PebView\DialogLevel;` Dialog Levels

  - `DialogLevel::Info` Info Dialog
  - `DialogLevel::Warning` Warning Dialog
  - `DialogLevel::Error` Error Dialog

`use Kingbes\PebView\FileAction;` File Actions

 - `FileAction::Open` Open File
 - `FileAction::OpenDir` Open Directory
 - `FileAction::Save` Save File

### Building

Each platform produces a single dynamic library: `lib/<os>/<arch>/PebView.{dll,so,dylib}` (window, dialogs and system notifications are all inside it).

| Platform | Command | Prerequisites |
| --- | --- | --- |
| Windows | `source\build.cmd` | Visual Studio 2022 (C++ toolset) + Windows SDK |
| Linux | `./source/linux.sh` | gcc / g++ / pkg-config / gtk+-3.0 / webkit2gtk-4.1 / libnotify |
| macOS | `./source/macos.sh` | Xcode Command Line Tools (builds x86_64 and arm64 by default) |