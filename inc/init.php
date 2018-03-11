<?php
error_reporting(E_ALL);



spl_autoload_register(function($class) {
    $class = strtolower($class);

    include 'inc/' . $class . '.php';
});

$api = new Api();
$db  = Db::getInstance();
