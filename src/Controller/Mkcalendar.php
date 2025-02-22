<?php

namespace Caldav\Controller;

use Caldav\Model\Base\Controller;
use Caldav\Model\Db\Calendar;
use Caldav\Model\Db\PropNs;

class Mkcalendar extends Controller
{
    private $fieldMap = [
        'displayname'                      => 'name',
        'calendar-description'             => 'description',
        'zid'                              => 'zid',
        'supported-calendar-component-set' => 'component_set',
        'max-resource-size'                => 'max_resource_size',
        'min-date-time'                    => 'min_date_time',
        'max-date-time'                    => 'max_date_time',
        'max-instances'                    => 'max_instances',
        'max-attendees-per-instance'       => 'max_attendees_per_instance'
    ];
    /**
     * @inheritDoc
     */
    public function handler()
    {
        $uri = rtrim($_REQUEST['resource'], '/') . '/';
        if (!str_starts_with($uri, CALENDAR_ROOT) || $uri === CALENDAR_ROOT) {
            return ['code' => 403];
        }
        $dbCalendar = Calendar::getInstance();
        $info = $dbCalendar->getBaseInfo($uri);
        if (!empty($info)) {
            return ['code' => 409];
        }
        if ($info === false) {
            return ['code' => 503];
        }
        $props = $this->getSetProp();
        $props['d:displayname'] = $props['d:displayname'] ?? basename($uri);
        $info = ['uri' => $uri];
        $dbCalendar->create($info, $props);
        return ['code' => 201];
    }

    /**
     * @inheritDoc
     */
    protected function getArrInput()
    {
        // TODO: Implement getArrInput() method.
    }

    public function getSetProp() {
        $objXml = $this->xpath('mkcalendar/set/prop');
        if(empty($objXml) || $objXml[0]->childNodes->length == 0) {
            return null;
        }
        $objXml = $objXml[0]->childNodes;
        $arrProps = [];
        for ($i =0; $i < $objXml->length; $i++){
            if(!empty($objXml->item($i)->tagName)) {
                if ($objXml->item($i)->childNodes->length == 1 && empty($objXml->item($i)->childNodes->item(0)->tagName)) {
                    $value = $objXml->item($i)->nodeValue;
                } else {
                    $value = $this->xmlToArray($objXml->item($i)->childNodes);
                }
                $uri = $objXml->item($i)->namespaceURI;
                $nsId =  PropNs::getInstance()->getNsIdByUri($uri);
                $prefix = PropNs::$nsList[$nsId]['prefix'];
                $arrProps[$prefix . ':' . $objXml->item($i)->localName] = $value;
            }
        }
        return $arrProps;
    }
}