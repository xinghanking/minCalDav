<?php

namespace Caldav\Model\Db;

use Caldav\Model\Base\Db;
use DateTime;
use DateTimeZone;

class Comp extends Db
{
    const TABLE = 'comp';
    protected $_tbl = self::TABLE;

    protected $_fields = [
        'id',
        'uri',
        'calendar_id',
        'uid',
        'recurrence_id',
        'comp_type',
        'dtstamp',
        'dtstart',
        'dtend',
        'prop',
        'comp_prop',
        'ics_data',
        'last_modified',
        'etag',
        'sequence'
    ];

    const PROP_NAMES = [

    ];
    const TYPE_MAP = [
        'VEVENT'    => Calendar::COMP_VEVENT,
        'VTODO'     => Calendar::COMP_VTODO,
        'VJOURNAL'  => Calendar::COMP_VJOURNAL,
        'VFREEBUSY' => Calendar::COMP_VFREEBUSY,
    ];

    /**
     * @param $uri
     *
     * @return array|false|null
     */
    public function getBaseInfoByUri($uri) {
        $res = $this->getRow($this->_fields, ['`uri`=' => $uri, '`recurrence_id`=' => '']);
        if (!empty($res)) {
            $res['prop'] = empty($res['prop'])? [] : json_decode($res['prop'], true);
        }
        return $res;
    }

    public function addObject($uri, $calendarId, $type, $ics) {
        if (count($ics) == 1) {
            $ics  = current($ics);
            $dtTime = $this->getDtTime($ics);
            $info = [
                'uri'           => $uri,
                'calendar_id'   => $calendarId,
                'uid'           => $ics['UID'],
                'recurrence_id' => $this->formatCurrenceId($ics['RECURRENCE-ID'] ?? ''),
                'comp_type'     => $type,
                'dtstart'       => $dtTime['dtstart'],
                'dtend'         => $dtTime['dtend'],
                'prop'          => json_encode(['d:getlastmodified' => '', 'd:getetag' => ''], JSON_UNESCAPED_SLASHES),
                'comp_prop'      => json_encode($ics, JSON_UNESCAPED_SLASHES),
                'ics_data'      => \Caldav\Utils\Calendar::arrToIscText($ics)
            ];
            return $this->insert($info);
        }
        $icsData = [];
        foreach ($ics as $item) {
            $dtTime = $this->getDtTime($item);
            $icsData[] = [
                'uri'           => empty($item['RECURRENCE_ID']) ? $uri : '',
                'calendar_id'   => $calendarId,
                'uid'           => $item['UID'],
                'recurrence_id' => $this->formatCurrenceId($item['RECURRENCE-ID'] ?? ''),
                'comp_type'     => $type,
                'dtstart'       => $dtTime['dtstart'],
                'dtend'         => $dtTime['dtend'],
                'prop'          => json_encode(['d:getlastmodified' => '', 'd:getetag' => ''], JSON_UNESCAPED_SLASHES),
                'comp_prop'      => json_encode($item, JSON_UNESCAPED_SLASHES),
                'ics_data'      => \Caldav\Utils\Calendar::arrToIscText($item)
            ];
        }
        return $this->batchInsert($icsData);
    }

    public function updateInstance($id, $ics)
    {
        $info = $this->getDtTime($ics);
        $info['comp_prop'] = json_encode($ics, JSON_UNESCAPED_SLASHES);
        $info['ics_data'] = \Caldav\Utils\Calendar::arrToIscText($ics);
        return $this->update($info , ['`id`=' => $id]);
    }

    public function getIcsByCompUid($compUid, $compType)
    {
         $comp = array_search($compType, self::TYPE_MAP);
         $data = $this->getData('ics_data', ['`uid`=' => $compUid]);
         foreach($data as $c => $item) {
             $data[$c] = 'BEGIN:' . $comp . "\n" . $item['ics_data'] . "\nEND:$comp";
         }
         return implode("\n", $data);
    }

    public function getDtTime($ics)
    {
        $info = [];
        if(isset($ics['DTSTART']['p']['TZID'])) {
            $dt = new DateTime($ics['DTSTART']['v'], new DateTimeZone($ics['DTSTART']['p']['TZID']));
            $info['dtstart'] = $dt->getTimestamp();
        } else {
            $info['dtstart'] = is_string($ics['DTSTART']) ? strtotime($ics['DTSTART']) : time();
        }
        if(isset($ics['DTEND']['p']['TZID'])) {
            $dt = new DateTime($ics['DTEND']['v'], new DateTimeZone($ics['DTEND']['p']['TZID']));
            $info['dtend'] = $dt->getTimestamp();
            return $info;
        }
        if (isset($ics['DTEND']) && is_string($ics['DTEND'])) {
            $info['dtend'] = strtotime($ics['DTEND']);
            return $info;
        }
        $info['dtend'] = $info['dtstart'] + (isset($ics['DURATION']) ? $this->totalDuration($ics['DURATION']) : 3600);
        return $info;
    }

    public function formatCurrenceId($recurrenceId) {
        if(is_string($recurrenceId)) {
            return $recurrenceId;
        }
        if(is_array($recurrenceId)) {
            return implode(';', $recurrenceId['p']) . ':' . $recurrenceId['v'];
        }
        return '';
    }
    public function updateCompObject($objId, $ics) {
        $etag = $this->quote(time() . '-' . $_SESSION['uid']);
        $ics = $this->quote(\Caldav\Utils\Calendar::arrToIscText($ics));
        $sql='UPDATE ' .$this->_tbl .
            ' SET `ics_data`= '.$ics.'
            ,`etag`= '.$etag.', `sequence`=`sequence`+1 WHERE id=' . $objId;
        return $this->exec($sql);
    }

    public function delByUri($uri) {
        $info = $this->getRow(['calendar_id', 'uid'], ['`uri`=' => $uri, '`recurrence_id`=' => '']);
        if (empty($info)) {
            return null;
        }
        self::beginTransaction();
        $sql = 'DELETE FROM ' . $this->_tbl . " WHERE uri='" . $uri . "'";
        $this->exec($sql);
        $dbCalendar = Calendar::getInstance();
        $dbCalendar->updateEtag($info['calendar_id']);
        return self::commit();
    }

    public function updateEtag($uri)
    {
        $sql = 'UPDATE ' . $this->_tbl . ' 
        SET `prop`=JSON_SET(`prop`, \'$."d:getlastmodified"\', :lastmodified, \'$."cs:getctag"\', :etag, \'$."d:getetag"\', :etag), `sequence`=`sequence`+1 WHERE `uri`=:uri';
        return $this->execute($sql, [':lastmodified' => gmdate('D, d M Y H:i:s', time()) . ' GMT', ':etag' => $this->createEtag(), ':uri' => $uri]);
    }

    public function createEtag() {
        return $_SESSION['uid'] . '-' .time();
    }
    public function totalDuration($duration) {
        $total = 0;
        if (preg_match('/P(\d+Y)?(\d+M)?(\d+D)?(\d+W)?/', $duration,
            $matches)
        ) {
            if (!empty($matches[1])) {
                $total += (int) substr($matches[1], 0, -1) * 365 * 86400;
            }
            if (!empty($matches[2])) {
                $total += (int) substr($matches[2], 0, -1) * 2592000;
            }
            if (!empty($matches[3])) {
                $total += (int) substr($matches[3], 0, -1) * 86400;
            }
            if (!empty($matches[4])) {
                $total += (int) substr($matches[4], 0, -1) * 7 * 86400;
            }
        }
        if (preg_match('/T(\d+H)?(\d+M)?(\d+S)?/', $duration, $matches)) {
            if (!empty($matches[1])) {
                $total += (int) substr($matches[1], 0, -1) * 3600;
            }
            if (!empty($matches[2])) {
                $total += (int) substr($matches[2], 0, -1) * 60;
            }
            if (!empty($matches[3])) {
                $total += (int) substr($matches[3], 0, -1);
            }
        }
        return $total;
    }
}