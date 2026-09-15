@echo off
setlocal enabledelayedexpansion

@REM ==========================================================================
@REM  PebView Windows build script
@REM
@REM  Output : lib\windows\x86_64\PebView.dll   -- single dynamic library
@REM  Deps   : Windows system DLLs only. CRT and C++ runtime are linked
@REM           statically (/MT). At runtime only the system WebView2 Runtime is
@REM           needed -- webview has a built-in loader, so WebView2Loader.dll
@REM           is NOT required.
@REM
@REM  Needs  : Visual Studio 2022 (x64 C++ toolset) + Windows 10/11 SDK
@REM
@REM  Notes  : 1. MSVC / SDK paths are detected directly; vcvars64.bat is not
@REM              used, so this also works in restricted shells.
@REM           2. Keep this file ASCII-only. cmd.exe parses .cmd in the OEM
@REM              code page, so non-ASCII comments corrupt the script.
@REM           3. Paths derived from "Program Files (x86)" contain a ')' which
@REM              would close a ( ) block at parse time. Inside blocks always
@REM              reference them with delayed expansion: !var! not %var%.
@REM ==========================================================================

set "current_dir=%~dp0"
set "out_dir=%current_dir%..\lib\windows\x86_64"
set "build_dir=%current_dir%build\windows"
set "pf86=%ProgramFiles(x86)%"

@REM ---------- locate Visual Studio ----------
set "vs_path="
set "vswhere=%pf86%\Microsoft Visual Studio\Installer\vswhere.exe"
if exist "%vswhere%" (
    for /f "usebackq tokens=*" %%i in (`"%vswhere%" -latest -products * -requires Microsoft.VisualStudio.Component.VC.Tools.x86.x64 -property installationPath`) do set "vs_path=%%i"
)
if not defined vs_path (
    echo [ERROR] Visual Studio C++ toolset not found. Install the "Desktop development with C++" workload of VS2022.
    exit /b 1
)

@REM ---------- locate MSVC toolset (highest version) ----------
set "msvc_ver="
for /f "delims=" %%d in ('dir /b /ad /o-n "!vs_path!\VC\Tools\MSVC" 2^>nul') do (
    if not defined msvc_ver set "msvc_ver=%%d"
)
if not defined msvc_ver (
    echo [ERROR] MSVC toolset not found: !vs_path!\VC\Tools\MSVC
    exit /b 1
)

@REM ---------- locate Windows SDK (highest version containing windows.h) ----------
set "sdk_root=%pf86%\Windows Kits\10"
set "sdk_ver="
for /f "delims=" %%d in ('dir /b /ad /o-n "!sdk_root!\Include" 2^>nul') do (
    if not defined sdk_ver if exist "!sdk_root!\Include\%%d\um\windows.h" set "sdk_ver=%%d"
)
if not defined sdk_ver (
    echo [ERROR] Windows SDK not found: !sdk_root!\Include
    exit /b 1
)

set "msvc_root=%vs_path%\VC\Tools\MSVC\%msvc_ver%"
echo [INFO] MSVC : !msvc_root!
echo [INFO] SDK  : !sdk_ver!

@REM ---------- build environment ----------
set "PATH=!msvc_root!\bin\Hostx64\x64;%PATH%"
set "INCLUDE=!msvc_root!\include;!sdk_root!\Include\!sdk_ver!\ucrt;!sdk_root!\Include\!sdk_ver!\shared;!sdk_root!\Include\!sdk_ver!\um;!sdk_root!\Include\!sdk_ver!\winrt"
set "LIB=!msvc_root!\lib\x64;!sdk_root!\Lib\!sdk_ver!\ucrt\x64;!sdk_root!\Lib\!sdk_ver!\um\x64"

@REM ---------- clean ----------
if exist "%build_dir%" rmdir /s /q "%build_dir%"
mkdir "%build_dir%" 2>nul
if not exist "%out_dir%" mkdir "%out_dir%"
del /q "%out_dir%\PebView.dll" 2>nul
del /q "%out_dir%\PebView.lib" 2>nul
del /q "%out_dir%\PebView.exp" 2>nul

cd /d "%build_dir%"

@REM ---------- compile + link into a single DLL ----------
@REM  /MT      static CRT        /LD    produce a DLL
@REM  /DEF     explicit ABI       /utf-8 sources carry UTF-8 Chinese comments
@REM  /IMPLIB  keep the import lib and .exp in the build dir, not the release dir
cl /nologo /utf-8 /O2 /MT /EHsc /LD /D_CRT_SECURE_NO_WARNINGS /DWEBVIEW_STATIC ^
  "%current_dir%webview\webview.cc" ^
  "%current_dir%seticon\icon.c" ^
  "%current_dir%dialog\osdialog.c" ^
  "%current_dir%dialog\osdialog_win.c" ^
  "%current_dir%window\window_win.c" ^
  "%current_dir%toast\windows\wintoastlib.cpp" ^
  "%current_dir%toast\windows\wintoast_c.cpp" ^
  /I"%current_dir%webview" /I"%current_dir%seticon" /I"%current_dir%dialog" /I"%current_dir%window" /I"%current_dir%toast" ^
  /Fe:"%out_dir%\PebView.dll" ^
  /link /DEF:"%current_dir%exports.def" /IMPLIB:"%build_dir%\PebView.lib" ^
  ole32.lib shell32.lib shlwapi.lib user32.lib advapi32.lib version.lib ^
  comdlg32.lib propsys.lib psapi.lib windowsapp.lib

if errorlevel 1 (
    echo.
    echo [ERROR] build failed
    exit /b 1
)

echo.
echo [OK] %out_dir%\PebView.dll

@REM ---------- verify the exported ABI ----------
@REM  Build .def only proves that a listed name exists. The reverse -- a function
@REM  that is neither exported nor referenced internally -- gets dropped by the
@REM  linker as dead code, which breaks the PHP side silently. So verify that
@REM  every name in exports.def is actually present in the built DLL.
set "php_exe="
where php >nul 2>nul && set "php_exe=php"

if defined php_exe (
    !php_exe! "%current_dir%check-abi.php" --def "%current_dir%exports.def" --lib "%out_dir%\PebView.dll"
    if errorlevel 1 (
        echo.
        echo [ERROR] ABI check failed
        exit /b 1
    )
) else (
    echo [WARN] php not found, ABI check skipped. Run manually:
    echo [WARN]   php source\check-abi.php --def source\exports.def --lib lib\windows\x86_64\PebView.dll
)

endlocal
