<?php

namespace Kingbes\PebView;

/**
 * 文件操作类型
 * 
 * - Open 打开文件
 * - OpenDir 打开目录
 * - Save 保存文件
 */
enum FileAction: int
{
    case Open = 0;
    case OpenDir = 1;
    case Save = 2;
}
