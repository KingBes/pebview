#include "wintoast_c.h"
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

void *toastCreate()
{
    return WinToast::instance();
}

void toastSetAppName(void *instance, const char *app_name)
{
    WinToast *winToastInstance = static_cast<WinToast *>(instance);
    winToastInstance->setAppName(utf8_to_wstring(app_name));
}

void toastSetAppUserModelId(void *instance, const char *name, const char *app_user_model_id, const char *version)
{
    WinToast *winToastInstance = static_cast<WinToast *>(instance);
    winToastInstance->setAppUserModelId(WinToast::configureAUMI(utf8_to_wstring(name), utf8_to_wstring(app_user_model_id), utf8_to_wstring(version)));
}

bool toastInitialize(void *instance)
{
    WinToast *winToastInstance = static_cast<WinToast *>(instance);
    WinToast::WinToastError error;
    bool result = winToastInstance->initialize(&error);
    return result;
}

void *toastCreateTemplate(int template_type)
{
    WinToastTemplate *templ = new WinToastTemplate((WinToastTemplate::WinToastTemplateType)template_type);
    return templ;
}

void toastSetFirstLine(void *template_ptr, const char *first_line)
{
    WinToastTemplate *templ = static_cast<WinToastTemplate *>(template_ptr);
    templ->setFirstLine(utf8_to_wstring(first_line));
}

void toastSetSecondLine(void *template_ptr, const char *second_line)
{
    WinToastTemplate *templ = static_cast<WinToastTemplate *>(template_ptr);
    templ->setSecondLine(utf8_to_wstring(second_line));
}

void toastSetImagePath(void *template_ptr, const char *image_path)
{
    WinToastTemplate *templ = static_cast<WinToastTemplate *>(template_ptr);
    templ->setImagePath(utf8_to_wstring(image_path));
}

bool toastShow(void *instance, void *template_ptr)
{
    SimpleToastHandler *handler = new SimpleToastHandler();
    WinToast *winToastInstance = static_cast<WinToast *>(instance);
    WinToastTemplate *templ = static_cast<WinToastTemplate *>(template_ptr);
    WinToast::WinToastError error;
    INT64 toastId = winToastInstance->showToast(*templ, handler, &error);
    return toastId >= 0;
}
