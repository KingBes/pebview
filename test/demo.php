<?php

require dirname(__DIR__) . "/vendor/autoload.php";

use Kingbes\PebView\Window;
use Kingbes\PebView\Dialog;


$pv = Window::create(true);

Window::bind($pv, "demo", function (...$params) {
    Dialog::msg("Hello PebView!");
});

Window::setHtml($pv, 
<<<HTML
    <h1>hello</h1><button onClick="onBtn()">click</button>
    <script>
    function onBtn() {
    demo();
    }
    </script>
HTML);

Window::run($pv);

Window::destroy($pv);
