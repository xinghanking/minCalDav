<?php

namespace Caldav\Model\Db;

use Caldav\Model\Base\Db;

class CalendarChange extends Db
{
    const TABLE = 'change';
    protected $_tbl = '`' . self::TABLE . '`';
    protected $_fields = [
        'calendar_id',
        'ics',
        'sync_token'
    ];

    public function add($calendarId, $prop, $isc, $sync_token) {
        $prop = json_encode($prop, JSON_UNESCAPED_UNICODE);
        $isc = json_encode($isc, JSON_UNESCAPED_UNICODE);
        $info = [
            'calendar_id' => $calendarId,
            'isc' => $isc,
            'isc_etag' => md5($isc),
            'sync_token' => $sync_token
        ];
        return $this->insert($info);
    }

    public function getChanges($calendarId, $reqSyncToken, $currentSyncToken) {
        $tokens = [$currentSyncToken];
        if(!empty($reqSyncToken)) {
            $tokens[] = $reqSyncToken;
        }
        $data = $this->getData(['ics', 'sync_token'], ['`calendar_id`=' => $calendarId, '`sync_token` IN ' => $tokens]);
        $sc = [];
        foreach ($data as $v) {
            $sc[$v['sync_token']] = json_decode($v['ics'], true);
        }
        if (empty($sc[$reqSyncToken])) {
            return $sc[$currentSyncToken];
        }
        if ($sc[$reqSyncToken] == $sc[$currentSyncToken]) {
            return [];
        }
        $change = ['comp' => $sc[$currentSyncToken]['comp'], 'prop' => array_diff_assoc($sc[$currentSyncToken]['prop'], $sc[$reqSyncToken]['prop'])];
        if (!empty($sc[$reqSyncToken]['comp'])) {
            $change['comp'] = array_diff_assoc($change['comp'], $sc[$reqSyncToken]['comp']);
            $change['del_comp'] = array_keys(array_diff_key($sc[$reqSyncToken]['comp'], $sc[$currentSyncToken]['comp']));
        }
        return $change;
    }
}