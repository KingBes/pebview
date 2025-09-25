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
