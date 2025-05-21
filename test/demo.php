<?php

require_once dirname(__DIR__)
    . DIRECTORY_SEPARATOR . "src"
    . DIRECTORY_SEPARATOR . "PebView.php";

$pv = new PebView()
    ->setTitle("PebView Demo")
    ->alert("Hello, PebView!")
    ->navigate("http://www.baidu.com")
    ->run()
    ->destroy();
