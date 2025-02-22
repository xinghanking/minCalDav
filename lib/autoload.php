<?php

function myAutoloader($class)
{
    if (str_starts_with($class, 'Caldav\\')) {
        $class = dirname(__DIR__) . '\\src\\'.substr($class, 7);
    }
    $file = str_replace("\\", DIRECTORY_SEPARATOR, $class).".php";
    if (file_exists($file)) {
        require_once $file;
    }
}

spl_autoload_register("myAutoloader");
