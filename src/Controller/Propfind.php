<?php
namespace Caldav\Controller;

use Caldav\Model\Base\Controller;
use Caldav\Model\Dav\Resource;
use Caldav\Model\Db\Calendar;
use Caldav\Model\Db\Comp;
use Caldav\Model\Db\PropNs;
use Caldav\Utils\Dav_Status;
use Caldav\Utils\CalDav;
use Exception;
use Swoole\Http\Request;
use Swoole\Http\Response;
defined('BASE_URI') || define('BASE_URI', '');
defined('DAV_URI') || define('DAV_URI', 'DAV:');
class Propfind extends Controller {
    private $type = 'propname';
    private $prop = [];
    private $depth = 0;

    public function handler() {
        $props = $this->getInput();
        $this->prop = $props;
        if(!$this->type) {
            return ['code' => 405];
        }
        $baseProp = [
            'd:current-user-principal'    => ['current-user-principal', [['href', '/' . $_SESSION['username'] . '/']]],
            'c:calendar-home-set'         => ['calendar-home-set', [['href', '/' . $_SESSION['username'] . '/calendars/']], PropNs::CAL_ID],
            'd:resourcetype'              => ['resourcetype', '<d:collection/>'],
            'c:calendar-user-address-set' => ['calendar-user-address-set', '<d:href>mailto:' . $_SESSION['email'] . '</d:href><d:href>/' . $_SESSION['username'] . '/</d:href>'],
            'c:supported-calendar-component-set' => ['supported-calendar-component-set', '<c:comp name="VEVENT" /><c:comp name="VTODO" /><c:comp name="VJOURNAL" /><c:comp name="VFREEBUSY" />'],
            'd:current-user-privilege-set' => ['current-user-privilege-set', '<d:privilege><d:all/></d:privilege><d:privilege><c:read-free-busy/></d:privilege><d:privilege><d:read/></d:privilege><d:privilege><d:read-acl/></d:privilege><d:privilege><d:read-current-user-privilege-set/></d:privilege><d:privilege><d:write-properties/></d:privilege><d:privilege><d:write/></d:privilege><d:privilege><d:write-content/></d:privilege><d:privilege><d:unlock/></d:privilege><d:privilege><d:bind/></d:privilege><d:privilege><d:unbind/></d:privilege><d:privilege><d:write-acl/></d:privilege><d:privilege><d:share/></d:privilege>'],
            'd:owner'                     => ['owner', [['href', '/' . $_SESSION['username'] . '/']]],
        ];
        if(!str_starts_with($this->uri, CALENDAR_ROOT) || $this->uri === CALENDAR_ROOT) {
            if ($this->type == 'propname') {
                return ['code' => 207, 'body' => [
                    'multistatus',
                    [
                        [
                            'response',
                            [
                                ['href', $_REQUEST['resource']],
                                [
                                    'propstat',
                                    [
                                        ['prop', [['current-user-principal'], ['calendar-home-set', '', PropNs::CAL_ID]]],
                                        ['status', Dav_Status::$Msg[200]]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
                ];
            }
            $response = [['href', $_REQUEST['resource']]];
            if (empty($props)) {
                $response[] = ['propstat', [['prop', array_values($baseProp)], ['status', Dav_Status::$Msg[200]]]];
            } else {
                $allProp  = array_intersect_key($baseProp, $props);
                $missProp = array_diff_key($props, $allProp);
                if (!empty($allProp)) {
                    $response[] = ['propstat', [['prop', array_values($allProp)], ['status', Dav_Status::$Msg[200]]]];
                }
                if (!empty($missProp)) {
                    $response[] = ['propstat', [['prop', array_values($missProp)], ['status', Dav_Status::$Msg[404]]]];
                }
            }
            $multiStatus = [['response', $response]];
            if (rtrim($this->uri, '/') == '/' . $_SESSION['username'] . '/calendars' && !empty($this->depth)) {
                $dbCalendar = Calendar::getInstance();
                $cals = $dbCalendar->getCalendarByOwnerId($_SESSION['uid']);
                if (!empty($cals)) {
                    $depth = is_numeric($this->depth) ? --$this->depth : -1;
                    foreach ($cals as $cal) {
                        $multiStatus = array_merge($multiStatus, $this->getResponse($cal, $depth));
                    }
                }
            }
        }
        else {
            if (in_array(substr($_REQUEST['resource'], -4), ['.ics', '.ifb' ])){
               $dbComp = Comp::getInstance();
               $comp = $dbComp->getRow(['uri','prop'], ['`uri`=' => $_REQUEST['resource'], '`recurrence_id`=' => '']);
               if (empty($comp)) {
                   return ['code' => 404];
               }
               $multiStatus = [$this->propFindComp($comp)];
            } else {
                $dbCalendar = Calendar::getInstance();
                $cal = $dbCalendar->getBaseInfoByUri(rtrim($_REQUEST['resource'],'/').'/');
                if (empty($cal)) {
                    return ['code' => 404];
                }
                $multiStatus = $this->getResponse($cal, $this->depth);
            }
        }
        return [
            'code' => 207,
            'body' => ['multistatus', $multiStatus],
        ];
    }

    private function getInput()
    {
        $this->depth = $_REQUEST['HEADERS']['depth'] ?? 0;
        $xmlRequestBody = $this->getObjXml();
        if ($xmlRequestBody->getElementsByTagName('propname')->length > 0) {
            $this->type = 'propname';
            return [];
        }
        $props = [];
        $objXml = $xmlRequestBody->getElementsByTagName('prop');
        if ($objXml->length > 0) {
            $objXml = $objXml->item(0)->childNodes;
            if($objXml->length > 0) {
                for($i = 0; $i < $objXml->length; $i++) {
                    if(!empty($objXml->item($i)->localName)) {
                        $uri = $objXml->item($i)->namespaceURI ?? DAV_URI;
                        $nsId = PropNs::getInstance()->getNsIdByUri($uri);
                        $this->prop[$nsId][]= $objXml->item($i)->localName;
                        $props[PropNs::getInstance()->getPrefixByUri($uri) . ':' . $objXml->item($i)->localName] = [$objXml->item($i)->localName, '', $nsId];
                    }
                }
                if(!empty($props)) {
                    $this->type = 'prop';
                }
            }
        } elseif($xmlRequestBody->getElementsByTagName('allprop')->length > 0) {
            $this->type = 'allprop';
        } else {
            $this->type = false;
        }
        return $props;
    }

    /**
     * 获取对指定处理资源的执行按照请求的查询条件检索指定范围的属性值集合结果
     *
     * @param  Resource|null  $objResource
     * @param  int  $depth
     *
     * @return array
     * @throws Exception
     */
    private function getResponse(array $cal, int $depth = 0)
    {
        $arrResponseList = [];
        $calProp = json_decode($cal['prop'], true);
        $allProp = [];
        if ($this->type == 'propname') {
            foreach ($calProp as $propName => $propValue) {
                [$prefix, $name] = explode(':', $propName);
                $allProp[] = [$name, '', PropNs::getNsIdByPrefix($prefix)];
            }
            $arrResponseList[] = [
                'response',
                [
                    ['href', $cal['uri']],
                    ['propstat', [['prop', $allProp], ['status', Dav_Status::$Msg[200]]]]
                ]
            ];
        } else {
            foreach ($calProp as $propName => $propValue) {
                [$prefix, $name] = explode(':', $propName);
                $allProp[$propName] = [$name, $propValue, PropNs::getNsIdByPrefix($prefix)];
            }
            if(!empty($this->prop)) {
                $allProp = array_intersect_key($allProp, $this->prop);
            }
            if (isset($this->prop['d:current-user-privilege-set']) && empty($allProp['d:current-user-privilege-set'])) {
                $allProp['d:current-user-privilege-set'] = ['current-user-privilege-set', '<d:privilege><d:all/></d:privilege><d:privilege><c:read-free-busy/></d:privilege><d:privilege><d:read/></d:privilege><d:privilege><d:read-acl/></d:privilege><d:privilege><d:read-current-user-privilege-set/></d:privilege><d:privilege><d:write-properties/></d:privilege><d:privilege><d:write/></d:privilege><d:privilege><d:write-content/></d:privilege><d:privilege><d:unlock/></d:privilege><d:privilege><d:bind/></d:privilege><d:privilege><d:unbind/></d:privilege><d:privilege><d:write-acl/></d:privilege><d:privilege><d:share/></d:privilege>'];
            }
            if(isset($allProp['c:calendar-timezone'])) {
                $allProp['c:calendar-timezone'][1] = '<![CDATA[' . $allProp['c:calendar-timezone'][1] . "]]>";
            }
            if(isset($this->prop['d:owner'])) {
                $allProp['d:owner'] = ['owner', [['href', '/' . $_SESSION['username'] . '/']]];
            }
            $missProp = $this->prop;
            if(!empty($this->prop) && !empty($allProp)) {
                $missProp = array_diff_key($this->prop, $allProp);
            }
            $response = [['href', $cal['uri']]];
            if(!empty($allProp)) {
                $response[] = ['propstat', [['prop', array_values($allProp)], ['status', Dav_Status::$Msg[200]]]];
            }
            if(!empty($missProp)) {
                $response[] = ['propstat', [['prop', array_values($missProp)], ['status', Dav_Status::$Msg[404]]]];
            }
            $arrResponseList[] = ['response', $response];
        }
        if ($depth != 0) {
            $dbComp = Comp::getInstance();
            $comps = $dbComp->getData(['uri', 'prop'], ['`calendar_id`=' => $cal['id'], '`recurrence_id`=' => '']);
            if (!empty($comps)) {
                foreach ($comps as $comp) {
                    $arrResponseList[] = $this->propFindComp($comp);
                }
            }
        }
        return $arrResponseList;
    }

    private function propFindComp(array $comp) {
        $allProp = json_decode($comp['prop'], true);
        if ($this->type == 'propname') {
            foreach ($allProp as $propName => $propValue) {
                [$prefix, $name] = explode(':', $propName);
                $allProp[$propName] = [$name, '', PropNs::getNsIdByPrefix($prefix)];
            }
            $response = [['href', $comp['uri']], ['propstat', [['prop', array_values($allProp)], ['status', Dav_Status::$Msg[200]]]]];
        } else {
            foreach ($allProp as $propName => $propValue) {
                [$prefix, $name] = explode(':', $propName);
                $allProp[$propName] = [$name, $propValue, PropNs::getNsIdByPrefix($prefix)];
            }
            if(!empty($this->prop)) {
                $allProp = array_intersect_key($allProp, $this->prop);
            }
            if(isset($allProp['c:calendar-timezone'])) {
                $allProp['c:calendar-timezone'][1] = '<![CDATA[' . $allProp['c:calendar-timezone'][1] . ']]>';
            }
            if(isset($this->prop['d:owner'])) {
                $allProp['d:owner'] = ['owner', [['href', '/' . $_SESSION['username'] . '/']]];
            }
            $response = [['href', $comp['uri']]];
            $missProp = $this->prop;
            if(!empty($allProp)) {
                $response[] = ['propstat', [['prop', array_values($allProp)], ['status', Dav_Status::$Msg[200]]]];
                if(!empty($this->prop)) {
                    $missProp = array_diff_key($missProp, $allProp);
                }
            }
            if(!empty($missProp)) {
                $response[] = ['propstat', [['prop', array_values($missProp)], ['status', Dav_Status::$Msg[404]]]];
            }
        }
        return ['response', $response];
    }
}