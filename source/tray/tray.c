#include "tray.h"
#include <stdbool.h> // 引入 bool 类型定义

// cc -g -Wall -DTRAY_WINAPI=1 -Wall -Wextra -std=c99 -pedantic   -c -o example.o example.c
// cc example.o -g  -o example

#ifdef __cplusplus
extern "C" {
#endif

// 显示托盘
bool tray_create(struct tray *tray)
{
    return tray_init(tray) >= 0;
}

// 托盘事件循环
bool tray_loops(int boking)
{
    return tray_loop(boking) == 0;
}

// 关闭托盘
void tray_close()
{
    tray_exit();
}

#ifdef __cplusplus
}
#endif