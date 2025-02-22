#!/usr/bin/env php
<?php

declare(strict_types=1);

use Caldav\Controller\Options;
use Caldav\Middleware\Auth;
use Caldav\Model\Db\User;
use Caldav\Utils\CalDav;
use Swoole\Coroutine\Http\Server;

const BASE_ROOT = __DIR__.DIRECTORY_SEPARATOR;
if (file_exists(BASE_ROOT.'vendor/autoload.php')) {
    require_once BASE_ROOT.'vendor/autoload.php';
} else {
    require_once BASE_ROOT . 'lib' . DIRECTORY_SEPARATOR . 'autoload.php';
}

const PID_FILE = BASE_ROOT . 'run/caldav.pid';
const LOG_FILE = BASE_ROOT . 'log' . DIRECTORY_SEPARATOR . 'caldav.log';

if(!is_dir(BASE_ROOT . 'run')) {
    mkdir(BASE_ROOT . 'run');
}
if (!is_dir(BASE_ROOT.'log')) {
    mkdir(BASE_ROOT.'log');
}
function start() {
    if(file_exists(PID_FILE)) {
        $pid = intval(file_get_contents(PID_FILE));
        if (posix_kill($pid, 0)) {
            exit('CalDav Server already running');
        }
        unlink(PID_FILE);
    }
    $conf = require_once BASE_ROOT.'config/caldav.php';
    define('BASE_URI', $conf['server']['baseurl']);
    $server = new Server($conf['server']['host'], $conf['server']['port']);
    $server->set([
        'worker_num'      => $conf['server']['worker_num'] ?? swoole_cpu_num(),
        'max_request'     => $conf['server']['max_request'] ?? 1024,
        'http_parse_post' => false,
        'daemonize'       => true,
        'log_file'        => LOG_FILE
    ]);
    defined('NS_DAV_ID') || define('NS_DAV_ID', 1);
    defined('NS_DAV_URI') || define('NS_DAV_URI', 'DAV:');
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
        file_put_contents(PID_FILE, posix_getpid());
        $server->start();
    });
}

function useradd()
{
    $dbUser = User::getInstance();
    do {
        echo '请输入用户名: ';
        $username = trim(fgets(STDIN));
        if (!empty($username)) {
            if($dbUser->existUser($username)) {
                echo '用户名已存在，请重新操作.' . PHP_EOL;
                $username = false;
            }
        }
    } while (!$username);
    $password = getPassword();
    do {
        echo empty($email) ? '请输入用户电子邮箱：' : '邮箱已占用，请输入其它邮箱：';
        $email = trim(fgets(STDIN));
    } while ($email == '' || $dbUser->existEmail($email));
    if ($dbUser->add($username, $password, $email)) {
        echo '用户创建成功.' . PHP_EOL;
    }
}

function userdel()
{
    echo '请输入要删除的用户名：';
    $username = trim(fgets(STDIN));
    if (empty($username)) {
        userdel();
        exit();
    }
    $dbUser = User::getInstance();
    if (!$dbUser->existUser($username)) {
        echo '用户不存在，无需删除.' . PHP_EOL;
    }
    if ($dbUser->del($username)) {
        echo '用户' . $username . '已删除' . PHP_EOL;
    }
}

function passwd()
{
    echo '请输入要修改密码的用户名：';
    $username = trim(fgets(STDIN));
    if (empty($username)) {
        passwd();
    } else {
        $dbUser = User::getInstance();
        if (!$dbUser->existUser($username)) {
            echo '用户名不存在，请重新操作.' . PHP_EOL;
            passwd();
        } else {
            $password = getPassword('新');
            if ($dbUser->passwd($username, $password)) {
                 echo '密码更新成功.' . PHP_EOL;
            }
        }
    }
}

function getPassword($new = ''): string
{
    while (empty($password)) {
        echo "请输入".$new."密码: ";
        system('stty -echo');
        // 读取用户输入的密码
        $password = trim(fgets(STDIN));
        echo PHP_EOL;
    }
    while (empty($rePassWord)) {
        echo '请再次输入'.$new.'密码：';
        $rePassWord = trim(fgets(STDIN));
    }
    echo PHP_EOL;
    if ($rePassWord === $password) {
        system('stty echo');
        return $password;
    } else {
        echo  '两次输入密码不一致，请重新设置.' . PHP_EOL;
        return getPassword($new);
    }
}
switch($argv[1] ?? 'start') {
    case 'start':
        start();
        break;
    case 'useradd':
        useradd();
        break;
    case 'userdel':
        userdel();
        break;
    case 'passwd':
        passwd();
        break;
    default:
        echo 'Invalid command. Please usage { start | useradd | userdel | passwd }' . PHP_EOL;
};