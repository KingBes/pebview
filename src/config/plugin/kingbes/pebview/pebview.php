<?php

use Kingbes\PebView\WindowHint;

return [
    "debug" => true,
    "title" => "PebView",
    "size" => [640, 480, WindowHint::None],
    "icon" => base_path() . "/public/favicon.ico",
    "closeCallback" => function ($win) {
        $win->hide();
    },
    "tray" => [
        "icon" => base_path() . "/public/favicon." . (PHP_OS_FAMILY === "Linux" ? "png" : "ico"),
        "menu" => [
            [
                "text" => "显示窗口",
                "cb" => function ($win) {
                    $win->show();
                }
            ],
            [
                "text" => "退出应用",
                "cb" => function ($win) {
                    $win->terminate();
                }
            ]
        ]
    ],
    "bind" => []
];
