<?php

use Kingbes\PebView\WindowHint;

return [
    "debug" => true, // 是否开启调试模式
    "title" => "PebView", // 窗口标题
    "size" => [640, 480, WindowHint::None], // 窗口大小
    "icon" => base_path() . "/public/favicon.ico", // 窗口图标
    "closeCallback" => function ($win) { // 窗口关闭回调
        $win->hide();
    },
    "tray" => [ // 系统托盘
        "icon" => base_path() . "/public/favicon." . (PHP_OS_FAMILY === "Linux" ? "png" : "ico"), // 系统托盘图标
        "menu" => [ // 系统托盘菜单
            [
                "text" => "显示窗口", // 菜单名称
                "cb" => function ($win) { // 菜单回调
                    $win->show();
                }
            ],
            [
                "text" => "退出应用", // 菜单名称
                "cb" => function ($win) { // 菜单回调
                    $win->terminate();
                }
            ]
        ]
    ],
    "bind" => [ // 绑定js事件
        [
            "name" => "hello", // 事件名称
            "cb" => function (...$params) { // 事件回调
                return "hello";
            }
        ]
    ]
];
