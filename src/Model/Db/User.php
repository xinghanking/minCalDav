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

}