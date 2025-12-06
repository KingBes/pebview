#pragma once

#ifdef _WIN32
#ifdef BUILDING_DLL
#define TOAST_API __declspec(dllexport)
#else
#define TOAST_API __declspec(dllimport)
#endif
#else
#define TOAST_API __attribute__((visibility("default")))
#endif

TOAST_API void toast_create();