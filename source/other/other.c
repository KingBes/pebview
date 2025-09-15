#include "other.h"

int window_close(const void *ptr, int (*fn)(void *))
{
#ifdef _WIN32
    HWND window = (HWND)ptr;
    if (window == NULL)
    {
        return 0;
    }
    

#elif __linux__
#elif __APPLE__
#endif
}