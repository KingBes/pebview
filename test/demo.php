<?php

require dirname(__DIR__) . "/vendor/autoload.php";

use Kingbes\PebView\Window;
use Kingbes\PebView\Dialog;

// Dialog::message(1, 1, "Hello PebView!");


$pv = Window::create(true);

Window::bind($pv, "demo", function (...$params) {
    return 123.33;
});

Window::setHtml($pv, <<<HTML
<h1>hello</h1><button onClick="onBtn()">click</button>
<script>
function onBtn() {
   demo(123, 'asd').then(res => {
       console.log(res);
   });
}
</script>
HTML);

Window::run($pv);

Window::destroy($pv);
