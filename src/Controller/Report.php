<?php

namespace Caldav\Controller;

use Caldav\Model\Base\Controller;
use Caldav\Model\Db\Calendar;
use Caldav\Model\Db\CalendarChange;
use Caldav\Model\Db\Comp;
use Caldav\Model\Db\PropNs;
use Caldav\Utils\Dav_Status;
use Caldav\Utils\CalDav;

class Report extends Controller
{

    /**
     * @inheritDoc
     */
    public function handler()
    {
        if (!str_starts_with($this->uri, CALENDAR_ROOT) || $this->uri === CALENDAR_ROOT) {
            return ['code' => 403];
        }
        $objReqXml = $this->getObjXml()->documentElement;
        $requireType = $objReqXml->localName;
        if (in_array($requireType, ['sync-collection', 'calendar-query', 'calendar-multiget'])) {
            $nodeProp = $objReqXml->getElementsByTagName('prop');
            if ($nodeProp->length == 0) {
                return ['code' => 400];
            }
            $nodeProp = $nodeProp->item(0)->childNodes;
            $props    = [];
            $qp       = [];
            for ($i = 0; $i < $nodeProp->length; $i++) {
                $node = $nodeProp->item($i);
                if(!empty($node->localName)) {
                    $props[PropNs::getInstance()->getPrefixByUri($node->namespaceURI).':'.$node->localName] = '';
                    $qp[PropNs::getInstance()->getNsIdByUri($node->namespaceURI)][] = $node->localName;
                }
            }
            if (empty($props)) {
                return ['code' => 400];
            }
        }
        $dbCalendar = Calendar::getInstance();
        $calInfo = $dbCalendar->getBaseInfo($this->uri);
        if (empty($calInfo)) {
            return ['code' => 404];
        }
        $dbCalComp = Comp::getInstance();
        if ($requireType == 'sync-collection') {
            if (substr($this->uri, -1) != '/') {
                return ['code' => 403];
            }
            if ($objReqXml->childNodes->length == 0) {
                return ['code' => 400];
            }
            if (empty($calInfo['ics_data'])) {
                $calInfo['ics_data'] = $dbCalendar->getIcsData($calInfo);
            }
            $nodeSyncToken = $objReqXml->getElementsByTagName('sync-token');
            $nodeLevel = $objReqXml->getElementsByTagName('sync-level');
            $nodeLevel = ($nodeLevel->length > 0 && !empty($nodeLevel->item(0)->nodeValue)) ? $nodeLevel->item(0)->nodeValue : 0;
            if ($nodeSyncToken->length > 0) {
                $syncToken = empty($nodeSyncToken->item(0)->nodeValue) ? '' : trim($nodeSyncToken->item(0)->nodeValue);
                if($calInfo['sync_token'] == $syncToken) {
                    return ['code' => 207, 'body' => ['multistatus',  [['sync-token', $calInfo['sync_token']]]]];
                }
                $dbCalChange = CalendarChange::getInstance();
                $changes = $dbCalChange->getChanges($calInfo['id'], $syncToken, $calInfo['sync_token']);
            }
            $propStat = [];
            if ($nodeLevel == 0) {
                $calProps = empty($calInfo['prop']) ? $props : array_merge($props, array_intersect_key(json_decode($calInfo['prop'], true), $props));
                if (!empty($calInfo['c:time-zone'])) {
                    $calProps['c:time-zone'] = '<![CDATA[' . $calInfo['c:time-zone'] . ']]>';
                }
                foreach ($calProps as $nodeName => $nodeValue) {
                    [$prefix, $name] = explode(':', $nodeName);
                    $calProps[$nodeName] = [
                        $name, $nodeValue, PropNs::getNsIdByPrefix($prefix)
                    ];
                }
                if (isset($props['c:calendar-data']) && (isset($changes['prop']) || isset($changes['timezone']))) {
                    $calProps['c:calendar-data'] = ['calendar-data', '<![CDATA['.$calInfo['ics_data']."\n]]>", PropNs::getNsIdByPrefix('c')];
                }
                $propStat[] = [
                    'response',
                    [
                        ['href', $this->uri],
                        [
                            'propstat', [
                            ['prop', array_values($calProps)],
                            ['status', Dav_Status::$Msg[200]]
                        ]
                        ]
                    ]
                ];
            }
            if (!empty($changes['comp'])) {
                $compUris = array_keys($changes['comp']);
                $comps = $dbCalComp->getData(['prop', 'comp_type', 'uri', 'ics_data'], ['`uri` IN ' => $compUris]);
                $calProps = json_decode($calInfo['comp_prop'], true);
                $calProps = \Caldav\Utils\Calendar::arrToIscText($calProps);
                $ics = "<![CDATA[BEGIN:VCALENDAR\n" . $calProps . "\n";
                $typeMap = array_flip(Comp::TYPE_MAP);
                foreach ($comps as $obj) {
                    $compProps = json_decode($obj['prop'], true);
                    $objProp = array_intersect_key($compProps, $props);
                    if (isset($props['c:calendar-data'])) {
                        $objProp['c:calendar-data'] = $ics . "BEGIN:" . $typeMap[$obj['comp_type']] . "\n" . $obj['ics_data'] . "\nEND:" . $typeMap[$obj['comp_type']] . "\nEND:VCALENDAR\n]]>";
                    }
                    $compProps  = array_merge($props, $objProp);
                    $propStat[] = [
                        'response',
                        [
                            ['href', $obj['uri']],
                            ['propstat', [['prop', self::propsChange($compProps)], ['status', Dav_Status::$Msg[200]]]]
                        ]
                    ];
                }
            }
            if (!empty($changes['del_comp'])) {
                foreach ($changes['del_comp'] as $uri) {
                    $propStat[] = [
                        'response',
                        [
                            ['href', $uri],
                            ['propstat', [['status', Dav_Status::$Msg[404]]]]
                        ]
                    ];
                }
            }
            $propStat[] = ['sync-token', $calInfo['sync_token']];
            return ['code' => 207, 'body' => ['multistatus',  $propStat]];
        }
        if ($requireType == 'calendar-query') {
            $comps = $dbCalComp->getData(['prop', 'comp_type', 'comp_prop', 'uri', 'ics_data'], ['`calendar_id`=' => $calInfo['id']]);
            $calProps = empty($calInfo['comp_prop']) ? [] : \Caldav\Utils\Calendar::arrToIscText(json_decode($calInfo['comp_prop'],true));
            $ics = "<![CDATA[BEGIN:VCALENDAR\n".$calProps."\n";
            $propStat = [];
            foreach ($comps as $obj) {
                $compProps = empty($obj['prop']) ? [] : json_decode($obj['prop'], true);
                $objProp   = array_intersect_key($compProps, $props);
                if (isset($props['c:calendar-data'])) {
                    $compType = array_search($obj['comp_type'],Comp::TYPE_MAP);
                    $objProp['c:calendar-data'] = $ics."BEGIN:".$compType."\n".$obj['ics_data']."\nEND:".$compType."\nEND:VCALENDAR\n]]>";
                }
                $compProps  = array_merge($props, $objProp);
                $propStat[] = [
                    'response',
                    [
                        ['href', $obj['uri']],
                        [
                            'propstat', [
                                ['prop', self::propsChange($compProps)],
                                ['status', Dav_Status::$Msg[200]]
                        ]
                        ]
                    ]
                ];
            }
            return ['code' => 207, 'body' => ['multistatus', $propStat]];
        }
        if ($requireType == 'calendar-multiget') {
            $objHrefs = $objReqXml->getElementsByTagName('href');
            if($objHrefs->length == 0) {
                return ['code' => 404];
            }
            $hrefs = [];
            for($i = 0; $i < $objHrefs->length; $i++) {
                if(!empty($objHrefs->item($i)->nodeValue)) {
                    $hrefs[] = trim($objHrefs->item($i)->nodeValue);
                }
            }
            $fields = ['prop'];
            if (isset($props['c:calendar-data'])) {
                $fields = ['prop', 'comp_type', 'comp_prop', 'uri', 'ics_data'];
            }
            $comps = $dbCalComp->getData($fields, ['`uri` IN ' => $hrefs]);
            $calProps = \Caldav\Utils\Calendar::arrToIscText(json_decode($calInfo['comp_prop'], true));
            $ics = "<![CDATA[BEGIN:VCALENDAR\n" . $calProps . "\n";
            $propStat = [];
            foreach ($comps as $obj) {
                $compProps = empty($obj['prop']) ? [] : json_decode($obj['prop'], true);
                $objProp = array_intersect_key($compProps, $props);
                if (isset($props['c:calendar-data'])) {
                    $compType = array_search($obj['comp_type'], Comp::TYPE_MAP);
                    $objProp['c:calendar-data'] = $ics . "BEGIN:" . $compType . "\n" . $obj['ics_data'] . "\nEND:" . $compType . "\nEND:VCALENDAR\n]]>";
                }
                $compProps = array_merge($props, $objProp);
                $propStat[] = [
                    'response',
                    [
                        ['href', $obj['uri']],
                        ['propstat', [['prop', self::propsChange($compProps)], ['status', Dav_Status::$Msg[200]]]]
                    ]
                ];
                $hrefs = array_diff($hrefs, [$obj['uri']]);
            }
            if (!empty($hrefs)) {
                foreach ($hrefs as $uri) {
                    $propStat[] = [
                        'response',
                        [
                            ['href', $uri],
                            ['propstat', [['status', Dav_Status::$Msg[404]]]]
                        ]
                    ];
                }
            }
            return ['code' => 207, 'body' => ['multistatus',  $propStat]];
       }
       if ($requireType == 'free-busy-query') {
           $qStart = $objReqXml->getElementsByTagName('time-range')->item(0)->attributes->getNamedItem('start')->value;
           $qEnd = $objReqXml->getElementsByTagName('time-range')->item(0)->attributes->getNamedItem('end')->value;
           $comps = $dbCalComp->getData('ics_data', ['`calendar_id`=' => $calInfo['id'], '`comp_type`=' => Calendar::COMP_VFREEBUSY, '`dtstart`<' => strtotime($qEnd), '`dtend`>' => strtotime($qStart)]);
           if (empty($comps)) {
               return ['code' => 404];
           }
           $calProps = \Caldav\Utils\Calendar::arrToIscText(json_decode($calInfo['comp_prop'], true));
           $ics = "<![CDATA[BEGIN:VCALENDAR\n" . $calProps . "\n";
           foreach ($comps as $obj) {
               $ics .= "BEGIN:VFREEBUSY\n" . $obj['ics_data'] . "\nEND:VFREEBUSY\n";
           }
           $ics .= "END:VCALENDAR\n]]>";
           return ['code' => 200, 'header' => ['Content-Type' => 'text/calendar; charset=utf-8'], 'body' => $ics];
        }
        return ['code' => 405];
    }

    /**
     * @inheritDoc
     */
    protected function getArrInput()
    {
        // TODO: Implement getArrInput() method.
    }

    public static function propsChange($props) {
        $arr = [];
        foreach ($props as $nodeName => $prop) {
            [$prefix, $name] = explode(':', $nodeName);
            $arr[] = [$name, $prop, PropNs::getNsIdByPrefix($prefix)];
        }
        return $arr;
    }
}