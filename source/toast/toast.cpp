#include "toast.h"
#include "wintoastlib.h"

using namespace WinToastLib;

const wchar_t* to_wstring(const char* str)
{
    static wchar_t buffer[256];
    mbstowcs(buffer, str, sizeof(buffer) / sizeof(buffer[0]));
    return buffer;
}

void toast_create(const char* title, const char* subtitle, const char* image_path)
{
    WinToastTemplate templ = WinToastTemplate(WinToastTemplate::ImageAndText02);
    templ.setTextField(to_wstring(title), WinToastTemplate::FirstLine);
    templ.setTextField(to_wstring(subtitle), WinToastTemplate::SecondLine);
    templ.setImagePath(to_wstring(image_path));
}