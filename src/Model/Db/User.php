<?php

namespace Caldav\Model\Db;

use Caldav\Model\Base\Db;

class User extends Db
{
    protected $_tbl = 'user';

    protected $_fields = [
        'id',
        'username',
        'password',
        'email',
    ];

    public function existUser($username): bool
    {
        $info = $this->getRow('id', ['`username`=' => $username]);
        return !empty($info);
    }

    public function existEmail($email): bool
    {
        $info = $this->getRow('id', ['`email`=' => $email]);
        return !empty($info);
    }
    public function add($username, $password, $email)
    {
        Db::beginTransaction();
        $id = $this->insert(['username' => $username, 'password' => md5($username . ':' . $password), 'email' => $email]);
        $dbCalendar = Calendar::getInstance();
        $info = ['uri' => '/' . $username . '/calendars/han-dress/', 'owner_id' => $id];
        $dbCalendar->create($info, ['d:displayname' => 'han-dress', 'c:description' => '默认日历']);
        Db::commit();
        return $id;
    }

    public function del($username) {
        $dbCalendar = Calendar::getInstance();
        $id = $this->getColumn('id', ['`username`=' => $username]);
        $dbCalendar->delete(['`owner_id`=' => $id]);
        return $this->delete(['`username`=' => $username]);
    }

    public function validPassword($username, $password)
    {
        $user = $this->getColumn('password', ['`username`=' => $username]);
        return isset($user['password'])  && $user['password'] == md5($username . ':' . $password);
    }
    public function passwd($username, $password) {
        $password = md5($username . ':' . $password);
        return $this->update(['password' => $password], ['`username`=' => $username]);
    }
}