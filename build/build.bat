@echo on
setlocal
echo "dialog"
gcc -c ./dialog/osdialog.c -I./dialog ^
-g -Wall -Wextra -std=c99 -pedantic -o osdialog.o
gcc -c ./dialog/osdialog_win.c -I./dialog ^
-g -Wall -Wextra -std=c99 -pedantic -o osdialog_win.o
echo "weview.o"
g++ -c ./webview/webview.cpp ^
    -I./webview ^
    -I./webview/webview2/include ^
    -Ilibs ^
    -Os --std=c++14 -DWEBVIEW_STATIC ^
    -mwindows ^
    -ffunction-sections -fdata-sections ^
    -o webview.o
echo "dll"
g++ ./*.o ^
    -shared ^
    -static ^
    -Wl,--gc-sections ^
    -ladvapi32 -lole32 -lshell32 -lshlwapi -luser32 -lversion ^
    -lcomdlg32 ^
    -mwindows ^
    -o ../lib/windows/PebView.dll
echo "del"
del *.o
echo "finish"