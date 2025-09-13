<?php

require dirname(__DIR__) . "/vendor/autoload.php";

use Kingbes\PebView\Core;

$core = new Core(true);
$core->setTitle("PebView Demo")
    ->setSize(800, 600, 0)
    ->setHtml("<h1>Hello PebView!</h1>")
    ->run()
    ->destroy();
