<?php

require dirname(__DIR__) . "/vendor/autoload.php";

use Kingbes\PebView\Core;
use Kingbes\PebView\Dialog;

// Dialog::message(1, 1, "Hello PebView!");

$core = new Core(true);
$core->setIcon(__DIR__ . "/php.ico")
    ->setHtml("<h1>Hello PebView!</h1>")
    ->run()
    ->destroy();
