<?php

namespace Caldav\Model\Db;

use Caldav\Model\Base\Db;

class TimeZone extends Db
{
    const TABLE = 'timezone';
    protected $_tbl = self::TABLE;
    protected $_fields = [
        'id',
        'calendar_id',
        'standard',
        'daylight',
        'sequence',
        'last_modified',
        'ics_data'
    ];

    public function add($calendarId, $info) {
        foreach ($info as $k => $timezone) {
            $info[$k] = [
                'calendar_id' => $calendarId,
                'tzid'     => $timezone['TZID'],
                'standard' => isset($timezone['STANDARD']) ? json_encode($timezone['STANDARD'][0], JSON_UNESCAPED_UNICODE) : '',
                'daylight' => isset($timezone['DAYLIGHT']) ? json_encode($timezone['DAYLIGHT'][0], JSON_UNESCAPED_UNICODE) : '',
                'ics_data' => \Caldav\Utils\Calendar::arrToIscText($timezone),
                'last_modified' => $timezone['LAST-MODIFIED']    ];
        }
        return $this->batchInsert($info);
    }
    public function getIcsDataByCalendarId($calendarId) {
        $data = $this->getData(['ics_data'], ['`calendar_id`=' => $calendarId, '`deleted`=' => self::DELETED_NO ]);
        if (empty($data)) {
            return '';
        }
        foreach ($data as $i => $tz) {
            $data[$i] = 'BEGIN:VTIMEZONE' . "\n" . $tz['ics_data'] . "\n" . 'END:VTIMEZONE';
        }
        return implode("\n", $data);
    }
}