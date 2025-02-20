#!/usr/bin/env php
<?php

declare(strict_types=1);

use Caldav\Controller\Options;
use Caldav\Middleware\Auth;
use Caldav\Utils\CalDav;
use Swoole\Coroutine\Http\Server;
const BASE_ROOT = __DIR__.DIRECTORY_SEPARATOR;
if (file_exists(BASE_ROOT.'vendor/autoload.php')) {
    require_once BASE_ROOT.'vendor/autoload.php';
} else {
    require_once BASE_ROOT . 'lib' . DIRECTORY_SEPARATOR . 'autoload.php';
}
defined('NS_DVA_ID') || define('NS_DAV_ID', 0);
defined('NS_DAV_URI') || define('NS_DAV_URI', 'DAV:');
$conf = require_once BASE_ROOT.'config/calendar.php';
define('BASE_URI', $conf['server']['baseurl']);
$server = new Server($conf['server']['host'], $conf['server']['port']);
$server->set([
    'worker_num'      => $conf['server']['worker_num'] ?? swoole_cpu_num(),
    'max_request'     => $conf['server']['max_request'] ?? 1024,
    'daemonize'       => true,
    'http_parse_post' => false
]);
Swoole\Coroutine\run(function () use ($server) {
$server->handle('/', function ($request, $response) {
    $uri   = $request->server['request_uri'];
    $_REQUEST['resource'] = $uri;
    $_REQUEST['header'] = $request->header;
    $start = strlen(BASE_URI);
    if ($start > 0) {
        if (substr($uri, 0, $start) != BASE_URI) {
            return $response->status(404);
        }
        $uri = substr($uri, $start);
    }
    if ($uri === '') {
        $uri = '/';
    }
    if (substr($uri, 0, 1) != '/') {
        $response->status(404);
        return $response->end();
    }
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $method = $request->server['request_method'];
    if (!in_array($method, Options::ALLOW)) {
        $response->status(405);
        return $response->end();
    }
    $handleAuth = new Auth();
    if (false === $handleAuth->handle($request, $response)) {
        return false;
    }
    $_REQUEST['server']  = $request->server;
    $_REQUEST['HEADERS'] = $request->header;
    $method = '\Caldav\Controller\\' . ucfirst(strtolower($method));
    $handle = new $method($request);
    $msg = $handle->handler();
    if(isset($msg['code'])) {
        $response->status($msg['code']);
    }
    if (isset($msg['header'])) {
        foreach ($msg['header'] as $k => $v) {
            $response->header($k, $v);
        }
    }
    if (!empty($msg['body'])) {
        if (is_array($msg['body'])) {
            $msg['body'] = CalDav::xml_encode($msg['body']);
            $response->header('Content-Type', 'application/xml');
        }
        $response->end($msg['body']);
    }
    return true;
});
    $server->start();
});