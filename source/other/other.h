#pragma once

#ifdef __cplusplus
extern "C"
{
#endif

    // 窗口显示
    int other_window_show(const void *window);

    // 窗口隐藏
    int other_window_hide(const void *window);

    // 恢复原窗口大小
    void other_window_restore_size(const void *window);

    // 窗口最小化
    int other_window_minimize(const void *window);

    // 窗口最大化
    int other_window_maximize(const void *window);

    // 窗口关闭
    int other_window_close(const void *window);

#ifdef __cplusplus
}
#endif