<?php

namespace Caldav\Controller;

use Caldav\Model\Base\Controller;
use Caldav\Model\Db\Calendar;
use Caldav\Model\Db\Comp;

class Get extends Controller
{

    /**
     * @inheritDoc
     */
    public function handler()
    {
        if(!str_starts_with($this->uri, CALENDAR_ROOT)) {
            return ['code' => 403];
        }
        if (substr($this->uri, -1) != '/' && in_array(substr($this->uri, -4), ['.ics', '.ifb' ])) {
            return ['code'=> 404];
        }
        if (substr($this->uri, -1) === '/') {
            $dbCalendar = Calendar::getInstance();
            $info = $dbCalendar->getBaseInfo($this->uri);
            if ($info === false) {
                return ['code' => 503];
            }
            if (empty($info)) {
                return ['code' => 404];
            }
            if(empty($info['ics_data'])) {
                $dbCalendar->beginTransaction();
                $info['ics_data'] = $dbCalendar->getIcsData($info);
                $dbCalendar->commit();
            }
            $header = ['content-type' => 'text/calendar', 'last-modified' => $info['last_modified'], 'etag' => $info['etag']];
            return ['code' => 200, 'header' => $header, 'body' => $info['ics_data']];
        }
        $dbCalObjs = Comp::getInstance();
        $info = $dbCalObjs->getBaseInfoByUri($this->uri);
        if ($info === false) {
            return ['code' => 503];
        }
        if (empty($info)) {
            return ['code' => 404];
        }
        $dbCalendar = Calendar::getInstance();
        $icsBaseProp = $dbCalendar->getIcsBasePropById($info['calendar_id']);
        $comp = $dbCalObjs->getIcsByCompUid($info['uid'], $info['comp_type']);
        $ics = 'BEGIN:VCALENDAR' . "\n" . $icsBaseProp . "\n" . $comp . "\n" . 'END:VCALENDAR';
        $header = ['content-type' => 'text/calendar', 'last-modified' => $info['last_modified'], 'etag' => $info['etag']];
        return ['code' => 200, 'header' => $header, 'body' => $ics];
    }

    /**
     * @inheritDoc
     */
    protected function getArrInput()
    {
        // TODO: Implement getArrInput() method.
    }
}