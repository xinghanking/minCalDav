<?php

namespace Caldav\Middleware;

use Caldav\Model\Db\User;
use Swoole\Http\Request;
use Swoole\Http\Response;

class Auth
{
    public function handle(Request $request, Response $response)
    {
        if (!empty($_SESSION['username'])) {
            return true;
        }
        // 获取请求头中的 Authorization 信息
        $authHeader = $request->header['authorization'] ?? '';

        // 检查 Authorization 信息是否存在并且是否有效
        if ($authHeader != '' && $this->validateToken($authHeader)) {
            return true;
        }

        // 如果身份验证失败，返回 401 未授权响应
        $response->status(401);
        $response->header('WWW-Authenticate', 'Basic realm="Access to the site"');
        return false;
    }

    private function validateToken(string $token): bool
    {
        $token = preg_split('/\s+/', $token, 2);
        $token = base64_decode($token[1]);
        [$username, $password] = explode(':', $token, 2);
        $dbUser = User::getInstance();
        $currentUser = $dbUser->getRow(['id', 'username', 'password', 'email'], ['`username`=' => $username]);
        if(empty($currentUser)) {
            return false;
        }
        $hash = md5($username.':'.$password);
        if ($currentUser['password'] === $hash) {
            $_SESSION['username'] = $username;
            $_SESSION['auth_token'] = $token;
            $_SESSION['uid'] = $currentUser['id'];
            $_SESSION['email'] = $currentUser['email'];
            define('CALENDAR_ROOT', '/' . $username . '/calendars/');
            return true;
        }
        return false;
    }
}