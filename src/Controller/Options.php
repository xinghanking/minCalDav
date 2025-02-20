<?php
namespace Caldav\Controller;

use Swoole\Http\Request;
use Swoole\Http\Response;
class Options {
    const ALLOW = [
        'OPTIONS', 'GET', 'HEAD', 'PUT', 'DELETE', 'REPORT', 'PROPFIND', 'PROPPATCH', 'MKCALENDAR'
    ];
    public function handler() {
        $dav = [
            '1,2,3',
            'access-control',
            'calendar-access',
            'calendar-auto-schedule',
            'calendar-availability',
            'calendarserver-sharing',
            'calendarserver-principal-property-search',
            'version-control',
            'activity',
            'sync-collection'
        ];
        return ['code' => 200, 'header' => [
            'Allow' => implode(', ', self::ALLOW),
            'Dav'   => implode(', ', $dav),
            'MS-Author-Via' => 'DAV'
        ]];
    }
}