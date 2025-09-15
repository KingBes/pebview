<?php

namespace Kingbes\PebView;

/**
 * 文件操作类型
 */
enum FileAction: int
{
    case Open = 0;
    case OpenDir = 1;
    case Save = 2;
}
