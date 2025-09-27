<?php

namespace Kingbes\PebView;

/**
 * 对话框级别
 * 
 * - Info 信息对话框
 * - Warning 警告对话框
 * - Error 错误对话框
 */
enum DialogLevel: int
{
    case Info = 0;
    case Warning = 1;
    case Error = 2;
}