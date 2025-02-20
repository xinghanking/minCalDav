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
    public $defProp = [
        'd:resourcetype'         => [['collection'],['calendar', '', PropNs::CAL_ID]],
        'c:supported-calendar-component-set' => '<c:comp name="VEVENT" /><c:comp name="VTODO" /><c:comp name="VJOURNAL" /><c:comp name="VFREEBUSY" />',
        'd:supported-report-set' => '<d:report><d:sync-collection /></d:report><d:report><c:calendar-multiget /></d:report><d:report><c:calendar-query /></d:report><d:report><c:free-busy-query /></d:report>',
        'd:displayname'          => '',
        'd:supported-privilege-set' => [
            ['privilege>', '<c:read-free-busy />'],
            ['privilege>', '<d:read />'],
            ['privilege>', '<d:read-acl />'],
            ['privilege>', '<d:read-current-user-privilege-set />'],
            ['privilege>', '<d:write-properties />'],
            ['privilege>', '<d:write />'],
            ['privilege>', '<d:write-content />'],
            ['privilege>', '<d:unlock />'],
            ['privilege>', '<d:bind />'],
            ['privilege>', '<d:unbind />'],
            ['privilege>', '<d:write-acl />'],
            ['privilege>', '<d:share />']
        ],
        'd:creationdate'         => '',
        'd:getlastmodified'      => '',
        'd:getetag'              => '',
        'd:sync-token'           => '',
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
        $this->defProp['d:displayname'] = basename($uri);
        $this->defProp['d:creationdate'] = gmdate('Y-m-d\TH:i:s\Z', time());
        $this->defProp['d:getlastmodified'] = gmdate('D, d M Y H:i:s', time()) . ' GMT';
        $props = $this->getSetProp();
        $props = array_merge($this->defProp, $props);
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