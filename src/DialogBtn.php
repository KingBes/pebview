<?php

namespace Kingbes\PebView;

/**
 * 对话框按钮
 * 
 * - Ok 确定按钮
 * - OkCancel 确定取消按钮
 * - YesNo 是否按钮
 */
enum DialogBtn: int
{
    case Ok = 0;
    case OkCancel = 1;
    case YesNo = 2;
}
