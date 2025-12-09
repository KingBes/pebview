#include "../toast.h"
#include "wintoastlib.h"
#include <string>
#include <codecvt>
#include <locale>

using namespace WinToastLib;

// Helper class to handle toast events
class SimpleToastHandler : public IWinToastHandler
{
public:
    void toastActivated() const override
    {
        // Do nothing
    }

    void toastActivated(int actionIndex) const override
    {
        // Do nothing
    }

    void toastActivated(std::wstring response) const override
    {
        // Do nothing
    }

    void toastDismissed(WinToastDismissalReason state) const override
    {
        // Do nothing
    }

    void toastFailed() const override
    {
        // Do nothing
    }
};

// Helper function to convert UTF-8 string to wide string
std::wstring utf8_to_wstring(const char *str)
{
    if (!str)
        return L"";
    std::wstring_convert<std::codecvt_utf8_utf16<wchar_t>> converter;
    try
    {
        return converter.from_bytes(str);
    }
    catch (const std::range_error &e)
    {
        // Fallback for invalid UTF-8
        std::wstring result;
        while (*str)
        {
            result += static_cast<wchar_t>(*str++);
        }
        return result;
    }
}

/**
 * @brief 显示 toast 通知
 *
 * @param app 应用名称
 * @param title 通知标题
 * @param message 通知消息
 * @param image_path 图片路径
 * @return TOAST_API bool 是否成功显示通知
 */
bool toastShow(
    const char *app,
    const char *title,
    const char *message,
    const char *image_path)
{
    WinToast *instance = WinToast::instance();
    instance->setAppName(utf8_to_wstring(app));
    instance->setAppUserModelId(WinToast::configureAUMI(utf8_to_wstring(app), L"", L""));
    WinToast::WinToastError error;
    if (!instance->initialize(&error))
    {
        return false;
    }
    WinToastTemplate templ(WinToastTemplate::ImageAndText02);
    if (title)
    {
        templ.setFirstLine(utf8_to_wstring(title));
    }
    if (message)
    {
        templ.setSecondLine(utf8_to_wstring(message));
    }
    if (image_path)
    {
        templ.setImagePath(utf8_to_wstring(image_path));
    }
    SimpleToastHandler *handler = new SimpleToastHandler();
    INT64 toastId = WinToast::instance()->showToast(templ, handler, &error);
    if (toastId > 0)
    {
        return true;
    }
    delete handler;
    return false;
}