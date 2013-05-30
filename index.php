<?php

include "vendor/autoload.php";

$proxy = new \phpproxy\Proxy();
$proxy->forward($_SERVER['REQUEST_URI']);