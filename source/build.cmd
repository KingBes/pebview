@REM 编译PebView.dll要求
@REM 1. 安装 mingw64
@REM 2. 设置环境变量 PATH 包含 mingw64 目录

@REM 获取当前执行文件的目录
set current_dir=%~dp0

@REM 删除所有.o文件
del /s /q %current_dir%*.o
@REM 删除所有.dll文件
del /s /q %current_dir%..\lib\windows\PebView.dll

gcc -c %current_dir%seticon\icon.c -o %current_dir%seticon\icon.o -I%current_dir%seticon
gcc -c %current_dir%webview\webview.cc -o %current_dir%webview\webview.o -DWEBVIEW_STATIC -std=c++14 -I%current_dir%webview
gcc -c %current_dir%dialog\osdialog.c -o %current_dir%dialog\osdialog.o -I%current_dir%dialog
gcc -c %current_dir%dialog\osdialog_win.c -o %current_dir%dialog\osdialog_win.o -I%current_dir%dialog

gcc -c %current_dir%window\window_win.c -o %current_dir%window\window_win.o -I%current_dir%window

g++ -shared ^
-o %current_dir%..\lib\windows\PebView.dll ^
%current_dir%seticon\icon.o ^
%current_dir%webview\webview.o ^
%current_dir%dialog\osdialog.o ^
%current_dir%dialog\osdialog_win.o ^
%current_dir%window\window_win.o ^
-DWEBVIEW_EDGE -static ^
-ladvapi32 -lole32 -lshell32 -lshlwapi -luser32 -lversion -lstdc++ -lcomdlg32 ^
-I%current_dir%webview ^
-I%current_dir%dialog ^
-I%current_dir%seticon ^
-I%current_dir%window

@REM 编译Toast.dll要求
@REM 1. 安装 msvc 和 Windows sdk 和 cmake
@REM 2. 设置环境变量 PATH 包含 msvc 目录
@REM 3. 设置环境变量 INCLUDE 包含 Windows sdk 目录
@REM 4. 设置环境变量 LIB 包含 Windows sdk 目录

@REM 删除所有.dll文件
del /s /q %current_dir%..\lib\windows\Toast.dll

cd %current_dir%toast
mkdir build
cd build
cmake ..
cmake --build . --config Release
copy Release\WinToast.dll %current_dir%..\lib\windows\Toast.dll