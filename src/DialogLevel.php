<?php

namespace Kingbes\PebView;

/**
 * 对话框级别
 */
enum DialogLevel: int
{
    case Info = 0;
    case Warning = 1;
    case Error = 2;
}