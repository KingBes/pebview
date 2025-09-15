<?php

namespace Kingbes\PebView;

/**
 * 窗口提示
 * 
 * - None：自由变换
 * - Min：最小化窗口
 * - Max：最大化窗口
 * - Fixed：固定窗口大小
 */
enum WindowHint: int
{
    case None = 0;
    case Min = 1;
    case Max = 2;
    case Fixed = 3;
}
