#!/usr/bin/env php
<?php

use Caldav\Model\Base\Db;

const BASE_ROOT = __DIR__.DIRECTORY_SEPARATOR;
if (file_exists(BASE_ROOT.'vendor/autoload.php')) {
    require_once BASE_ROOT.'vendor/autoload.php';
} else {
    require_once BASE_ROOT . 'lib' . DIRECTORY_SEPARATOR . 'autoload.php';
}
$configFile = BASE_ROOT . 'config' . DIRECTORY_SEPARATOR . 'calendar.php';
$conf = require $configFile;
if (empty($conf['database']['host']) || empty($conf['database']['port']) || empty($conf['database']['dbname']) || empty($conf['database']['user']) || empty($conf['database']['pass'])) {
    exit('请先配置' . $configFile . '里的数据库信息');
}
try {
    $db = Db::getInstance();
    $db->exec(file_get_contents(BASE_ROOT.'config'.DIRECTORY_SEPARATOR.'init.sql'));
    echo '已成功安装数据库.' .PHP_EOL;
} catch (\Exception $e) {
    echo '安装数据库失败' . $e->getMessage() .PHP_EOL;
}