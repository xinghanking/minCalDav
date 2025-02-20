<?php

namespace Caldav\Controller;

use Caldav\Model\Base\Controller;
use Caldav\Model\Db\Calendar;
use Caldav\Model\Db\Comp;

class Put extends Controller
{

    /**
     * @inheritDoc
     */
    public function handler()
    {
        if(empty($this->header['content-length'])) {
            return ['code' => 400];
        }
        if (!str_starts_with($this->uri, CALENDAR_ROOT)) {
            return ['code' => 403];
        }
        $uri = $this->uri;
        if (empty($this->header['content-type']) || strtok($this->header['content-type'], ';') != 'text/calendar' || (substr($uri, -1) !='/' && !in_array(substr($uri, -4), ['.ics', '.ifb']))) {
            return [
                'code' => 415,
                'body' => '<?xml version="1.0" encoding="UTF-8"?>
<d:error xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav"><d:supported-media-type><d:mediatype>text/calendar</d:mediatype></d:supported-media-type><cal:supported-calendar-data><cal:calendar-data content-type="text/calendar" version="2.0"/></cal:supported-calendar-data></d:error>'
            ];
        }
        $ics = $this->getIcsInfo();
        if(empty($ics)) {
            return ['code' => 400];
        }
        $dbCalendar = Calendar::getInstance();
        if(substr($uri, -1) == '/') {
            $info = $dbCalendar->getBaseInfoByUri($uri);
            if($info === false) {
                return ['code' => 503];
            }
            $ics = $this->getIcsInfo();
            if(empty($info)) {
                $dbCalendar->beginTransaction();
                $info = ['uri' => $uri, 'owner_id' => $_SESSION['uid']];
                $dbCalendar->addCalendar($info, $ics);
                $dbCalendar->commit();
                return ['code' => 201];
            }else {
                if(empty($this->header['if-match'])) {
                    return ['code' => 409];
                }
                if ($this->header['if-match'] != $info['etag']) {
                    return ['code' => 412, 'header' => ['ETag' => $info['etag']]];
                }
                if(empty($this->header['overwrite']) || $this->header['overwrite'] != 'T') {
                    return ['code' => 428, 'header' => ['ETag' => $info['etag']]];
                }
                $dbCalendar->beginTransaction();
                $dbCalendar->overWrite($info['id'], $uri, $ics);
                $dbCalendar->updateEtag($info['id']);
                $dbCalendar->commit();
                return ['code' => 200];
            }
        }
        else {
            $upper = $dbCalendar->getBaseInfoByUri(dirname($uri) . '/');
            if (empty($upper)) {
                return ['code' => 404];
            }
            $dbCalComp = Comp::getInstance();
            $info = $dbCalComp->getBaseInfoByUri($uri);
            if($info === false) {
                return ['code' => 503];
            }
            $tz = $ics['VTIMEZONE'] ?? [];
            $ics = array_intersect_key($ics, Comp::TYPE_MAP);
            if(count($ics) > 1) {
                return ['code' => 409];
            }
            $type = Comp::TYPE_MAP[key($ics)];
            $ics = current($ics);
            $compUid = '';
            $recurrenceIds = [];
            foreach($ics as $item) {
               if ($compUid != '' && $compUid != $item['UID']) {
                   return ['code' => 400];
               }
               $compUid = $item['UID'];
               $item['RECURRENCE-ID'] = $dbCalComp->formatCurrenceId($item['RECURRENCE-ID'] ?? '');
               if(in_array($item['RECURRENCE-ID'], $recurrenceIds)) {
                   return ['code' => 400];
               }
               $recurrenceIds[] = $item['RECURRENCE-ID'];
            }
            if(empty($info)){
                $obj = $dbCalComp->getRow('id', ['`calendar_id`=' => $upper['id'], '`uid`=' => $compUid, '`recurrence_id`=' => '']);
                if(!empty($obj)) {
                    return ['code' => 409];
                }
                $dbCalendar->beginTransaction();
                $dbCalComp->addObject($uri,$upper['id'], $type,$ics);
                $dbCalComp->updateEtag($uri);
                $dbCalendar->updateEtag($upper['id']);
                $dbCalendar->commit();
                return ['code' => 201];
            }
            else
            {
                if($type != $info['comp_type'] || $compUid != $info['uid']) {
                    return ['code' => 409];
                }
                $instances = $dbCalComp->getData(['id', 'recurrence_id'], ['`calendar_id`=' => $upper['id'], '`uid`=' => $compUid, '`recurrence_id` IN ' => $recurrenceIds]);
                if (empty($instances)) {
                    $dbCalComp->beginTransaction();
                    $dbCalComp->addObject($uri,$upper['id'], $type,$ics);
                    $dbCalComp->updateEtag($uri);
                    $dbCalendar->updateEtag($info['calendar_id']);
                    $dbCalComp->commit();
                } else{
                    if (empty($this->header['if-match']) || $this->header['if-match'] != $info['etag']) {
                        //return ['code' => 412, 'header' => ['ETag' => $info['etag']]];
                    }
                    $existIns = [];
                    foreach($instances as $instance) {
                        $existIns[$instance['recurrence_id']] = $instance['id'];
                    }
                    $newIns = [];
                    foreach ($ics as $item) {
                        $recurrenceId = $dbCalComp->formatCurrenceId($item['RECURRENCE-ID'] ?? '');
                        if(isset($existIns[$recurrenceId])) {
                            $existIns[$recurrenceId] = ['id' => $existIns[$recurrenceId], 'ics' => $item];
                        } else {
                            $newIns[] = $item;
                        }
                    }
                    $dbCalComp->beginTransaction();
                    foreach ($existIns as $ins) {
                        $dbCalComp->updateInstance($ins['id'], $ins['ics']);
                    }
                    if (!empty($newIns)) {
                        $dbCalComp->addObject($uri,$upper['id'], $type,$newIns);
                    }
                    $dbCalComp->updateEtag($uri);
                    $dbCalendar->updateEtag($info['calendar_id']);
                    $dbCalComp->commit();
                }
                return ['code' => 200];
            }
        }
    }

    /**
     * @inheritDoc
     */
    protected function getArrInput()
    {
        // TODO: Implement getArrInput() method.
    }

    private function getIcsInfo() {
       return \Caldav\Utils\Calendar::icsToArr(trim($this->getBody()));
    }
}