<?php

namespace Caldav\Model\Db;

use Caldav\Model\Base\Db;

class Calendar extends Db
{
    const TABLE = '`calendar`';
    protected $_tbl = self::TABLE;

    protected array $_fields = [
        'id',
        'owner_id',
        'uri',
        'etag',
        'sync_token',
        'component_set',
        'prop',
        'comp_prop',
        'ics_data',
        'last_modified',
        'etag'
    ];

    const COMP_CALENDAR = 0;
    const COMP_VEVENT = 1;
    const COMP_VTODO = 2;
    const COMP_VJOURNAL = 3;
    const COMP_VFREEBUSY = 4;
    const PRODID = '-//Han Dress//CalDav//ZH_CN';
    const VERSION = '2.0';
    const CALSCALE = 'GREGORIAN';
    const BASE_PROP = [
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
        'c:calendar-timezone' => 'Asia/Shanghai',
        'd:creationdate'         => '',
        'd:getlastmodified'      => '',
        'd:getetag'              => '',
        'd:sync-token'           => '',
    ];
    protected array $iscExplodeProp = ['PRODID', 'SOURCE', 'METHOD'];

    private $fieldMap = [
        'displayname'                      => 'name',
        'calendar-description'             => 'description',
        'zid'                              => 'zid',
        'max-resource-size'                => 'max_resource_size',
        'min-date-time'                    => 'min_date_time',
        'max-date-time'                    => 'max_date_time',
        'max-instances'                    => 'max_instances',
        'max-attendees-per-instance'       => 'max_attendees_per_instance',
    ];

    public function getBaseInfo($uri){
        return $this->getRow($this->_fields, ['`uri`=' => $uri]);
    }
    public function getBaseInfoByResourceId($id)
    {
        return $this->getRow($this->_fields, ['`resource_id`=' => $id]);
    }
    public function getBaseInfoByUri($uri)
    {
        return $this->getRow($this->_fields, ['`uri`=' => $uri]);
    }

    public function getIcsBasePropById($id)
    {
        $info = $this->getRow($this->_fields, ['`id`=' => $id]);
        if(empty($info['comp_prop'])){
            $baseProps = [
                'PRODID'  => self::PRODID,
                'VERSION' => self::VERSION,
                'CALSCALE' => self::CALSCALE
            ];
            $icsProps = json_encode($baseProps , JSON_UNESCAPED_SLASHES);
            $this->update(['comp_prop' => $icsProps], ['`id`=' => $id]);
        } else {
            $baseProps = json_decode($info['comp_prop'], true);
        }
        return \Caldav\Utils\Calendar::arrToIscText($baseProps);
    }
    public function getIcsData($info) {
        $ics        = "BEGIN:VCALENDAR\n";
        $currentVer = [];
        $dbCalObjs  = Comp::getInstance();
        if(!empty($info['comp_prop'])) {
            $info['comp_prop'] = json_decode($info['comp_prop'], true);
        }
        if (empty($info['comp_prop'])) {
            $currentVer['prop'] = [
                'PRODID'  => self::PRODID,
                'VERSION' => self::VERSION,
                'CALSCALE' => self::CALSCALE
            ];
        } else {
            $currentVer['prop'] = $info['comp_prop'];
        }
        $ics .= \Caldav\Utils\Calendar::arrToIscText($currentVer['prop']) . "\n";
        $data = $dbCalObjs->getData(['uri', 'comp_type', 'ics_data', 'sequence'], ['`calendar_id`=' => $info['id']]);
        $compMap = array_flip(Comp::TYPE_MAP);
        if (!empty($data)) {
            foreach ($data as $row) {
                $ics .= 'BEGIN:' . $compMap[$row['comp_type']] . "\n" . $row['ics_data'] . "\nEND:" . $compMap[$row['comp_type']] . "\n";
                $currentVer['comp'][$row['uri']] = $row['sequence'];
            }
        }
        $dbTimeZone = TimeZone::getInstance();
        $tz = $dbTimeZone->getData(['id', 'tzid', 'ics_data', 'sequence'], ['`calendar_id`=' => $info['id']]);
        if(!empty($tz)) {
            foreach ($tz as $v) {
                if(!empty($v['ics_data'])) {
                    $ics .= "BEGIN:VTIMEZONE\n" . $v['ics_data'] . "\nEND:VTIMEZONE\n";
                }
                $currentVer['timezone'][$v['id']] = $v['sequence'];
            }
        }
        $ics .= 'END:VCALENDAR';
        $this->update(['comp_prop' => json_encode($currentVer['prop'], JSON_UNESCAPED_SLASHES), 'ics_data' => $ics], ['`id`=' => $info['id']]);
        $dbChange = CalendarChange::getInstance();
        unset($currentVer['prop']['PRODID']);
        $currentVer = json_encode($currentVer, JSON_UNESCAPED_SLASHES);
        $data = [
            'calendar_id' => $info['id'],
            'ics'         => $currentVer,
            'sync_token'  => $info['sync_token']
        ];
        $dbChange->insert($data);
        return $ics;
    }
    public function create(&$info, $props = []){
        $props['d:creationdate'] = gmdate('Y-m-d\TH:i:s\Z', time());
        $props['d:getlastmodified'] = gmdate('D, d M Y H:i:s', time()) . ' GMT';
        $props = array_merge(self::BASE_PROP, $props);
        if(!empty($props['c:supported-calendar-component-set'])) {
            if(is_array($props['c:supported-calendar-component-set'])) {
                $components = [];
                foreach ($props['c:supported-calendar-component-set'] as $comp)
                {
                    if ($comp[0] == 'c:comp' && !empty($comp[3])) {
                        $components[] = $comp[3]['name'];
                    }
                }
                $info['component_set'] = implode(',', $components);
            }
        }
        $comp = [];
        if(isset($props['c:calendar-timezone'])){
            $tz =\Caldav\Utils\Calendar::icsToArr(trim($props['c:calendar-timezone']));
            if(isset($tz['VTIMEZONE'])){
                $comp['timezone'] = $tz['VTIMEZONE'];
                if(empty($props['zid']) && !empty($comp['timezone'][0]['TZID'])) {
                    $info['tzid'] = $comp['timezone'][0]['TZID'];
                }
            }
        }
        foreach ($props as $name => $value) {
            if (isset($this->fieldMap[$name])) {
                $info[$this->fieldMap[$name]] = $value[1];
            }
        }
        $info['owner_id'] = $info['owner_id'] ?? $_SESSION['uid'];
        $props['d:getetag'] = '';
        $props['cs:getctag'] = '';
        $props['d:sync-token'] = 1;
        $info['prop']=json_encode($props, JSON_UNESCAPED_SLASHES);
        $info['comp_prop'] = json_encode([], JSON_UNESCAPED_SLASHES);

        $id = $this->insert($info);
        if(!empty($comp['timezone'])){
            $dbTimeZone = TimeZone::getInstance();
            $dbTimeZone->add($id, $comp['timezone']);
        }
        return $id;
    }

    public function getCalendarByOwnerId($uid){
        return $this->getData(['id', 'uri', 'prop', 'comp_prop'], ['`owner_id`=' => $uid]);
    }
    public function updateByIsc($uri, $ics){

    }

    public function addCalendar($info = [], $ics = []){
        unset($ics['VCALENDAR']['METHOD']);
        unset($ics['VCALENDAR']['SOURCE']);
        unset($ics['VCALENDAR']['PRODID']);
        $info['isc_prop'] = json_encode($ics['VCALENDAR'], JSON_UNESCAPED_UNICODE);
        $info['ics_data'] = \Caldav\Utils\Calendar::arrToIscText($ics['VCALENDAR']);
        $id = $this->create($info);
        $ics = array_intersect_key($ics, Comp::TYPE_MAP);
        $this->addObjs($info['uri'], $id, $ics);
    }

    public function overWrite($id, $uri, $ics){
        unset($ics['VCALENDAR']['METHOD']);
        unset($ics['VCALENDAR']['SOURCE']);
        unset($ics['VCALENDAR']['PRODID']);
        $etag = \Caldav\Model\Dav\Resource::createEtag();
        $syncToken = $this->createSyncToken();
        $info['isc_prop'] = json_encode($ics['VCALENDAR'], JSON_UNESCAPED_UNICODE);
        $info['ics_data'] = \Caldav\Utils\Calendar::arrToIscText($ics['VCALENDAR']);
        $this->update($info, ['`id`=' => $id]);
        $sql = 'UPDATE ' . $this->_tbl . ' AS `a`, ' . Resource::TABLE . ' AS b 
        SET `b`.`getetag`=:etag
        WHERE `a .`resource_id`=`b`.`id` AND `a`.`id`=' . $id;
        $this->execute($sql, [':etag' => $etag]);
        $dbCalObjs = Comp::getInstance();
        $dbCalObjs->delete(['`calendar_id`=' => $id]);
        return $this->addObjs($uri, $id, $ics);
    }

    public function addObjs($uri, $id, $ics)
    {
        $dbCalObjs = Comp::getInstance();
        $dataChange = [];
        foreach ($ics as $compName => $obj) {
            foreach($obj as $k => $v) {
                $objId = $dbCalObjs->addObject($uri . $compName . '-' .$k . '.ics', $id, Comp::TYPE_MAP[$compName], $v);
                $dataChange[$objId] = 0;
            }
        }
        return $dataChange;
    }

    public function createEtag()
    {
        return $_SESSION['uid'] . '-' . time();
    }

    public function createSyncToken(){
        return $_SESSION['uid'] . '-' . time();
    }

    public function delByUri($uri)
    {
        $id = $this->getColumn('id', ['`uri`=' => $uri]);
        if (empty($id)) {
            return null;
        }
        self::beginTransaction();
        $this->delete(['`uri`=' => $uri]);
        $dbComp = Comp::getInstance();
        $dbComp->delete(['`calendar_id`=' => $id]);
        $dbTimeZone = TimeZone::getInstance();
        $dbTimeZone->delete(['`calendar_id`=' => $id]);
        $dbCalChange = CalendarChange::getInstance();
        $dbCalChange->delete(['`calendar_id`=' => $id]);
        return self::commit();
    }
    public function updateEtag($id) {
        $syncToken = $this->createSyncToken();
        $etag = $this->createEtag();
        $lastModified = gmdate('D, d M Y H:i:s', time()) . ' GMT';
        $sql = 'UPDATE ' . $this->_tbl . " 
        SET `ics_data`='', `prop`=JSON_SET(`prop`, '$.\"d:getetag\"', :etag, '$.\"cs:getctag\"', :etag, '$.\"d:getlastmodified\"', :lastmodified, '$.\"d:sync-token\"', :sync_token)
        WHERE `id`=" . $id;
        $res = $this->execute($sql, [':etag' => $etag, ':lastmodified' => $lastModified, ':sync_token' => $syncToken]);
        return $res !== false;
    }

}