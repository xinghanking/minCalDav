<?php

namespace Caldav\Controller;

use Caldav\Model\Base\Controller;
use Caldav\Model\Db\Calendar;
use Caldav\Model\Db\Comp;
use Caldav\Model\Db\PropNs;
use Caldav\Utils\Dav_Status;

class Proppatch extends Controller
{
    protected $arrInput = [
        'props' => []
    ];

    /**
     * @inheritDoc
     */
    public function handler()
    {
        if (!str_starts_with($this->uri, CALENDAR_ROOT)) {
            return ['code' => 403];
        }
        if (str_ends_with($this->uri, '/')) {
            $dbCalendar = Calendar::getInstance();
            $cal = $dbCalendar->getRow(['id', 'prop'], ['`uri`=' => $this->uri]);
            if (empty($cal)) {
                return ['code' => 404];
            }
            $cal['prop'] = $this->getProp($cal['prop']);
            $cal['prop']['d:getetag'] = $dbCalendar->createEtag();
            $cal['prop'] = json_encode($cal['prop'], JSON_UNESCAPED_UNICODE);
            $dbCalendar->update(['prop' => $cal['prop']], ['`id`=' => $cal['id']]);
        } else {
            $dbComp = Comp::getInstance();
            $comp = $dbComp->getRow(['id', 'prop'], ['`uri`=' => $this->uri, '`recurrence_id`=' => '']);
            if (empty($comp)) {
                return ['code' => 404];
            }
            $comp['prop'] = $this->getProp($comp['prop']);
            $comp['prop']['d:getetag'] = $dbComp->createEtag();
            $comp['prop'] = json_encode($comp['prop'], JSON_UNESCAPED_UNICODE);
            $dbComp->update(['prop' => $comp['prop']], ['`id`=' => $comp['id']]);
        }
        return [
            'code' => 207,
            'body' => [
                'multistatus',
                [[
                     'response',
                     [
                         ['href', $this->uri],
                         ['propstat', [['prop', $this->arrInput['props']], ['status', Dav_Status::$Msg[200]]]]
                     ]
                 ]]
            ]
        ];
    }

    private function getProp($allProp) {
        $allProp = json_decode($allProp, true);
        if (!empty($this->arrInput['remove'])) {
            $allProp = array_diff_key($allProp, $this->arrInput['remove']);
        }
        if (!empty($this->arrInput['set'])) {
            foreach($this->arrInput['set'] as $prop){
                $this->arrInput['props'][] = [$prop['prop_name'], $prop['prop_value'], $prop['ns_id']];
                $allProp[PropNs::getInstance()->getPrefixById($prop['ns_id']) . ':' . $prop['prop_name']] = $prop['prop_value'];
            }
        }
        $allProp['d:getlastmodified'] = gmdate('D, d M Y H:i:s', time());
        return $allProp;
    }
    protected function getRemoveProp()
    {
        $objXml = $this->xpath('remove/prop');
        if(empty($objXml)){
            return [];
        }
        $objXml = $objXml[0]->childNodes;
        $props = [];
        for ($i=0; $i<$objXml->length; $i++){
            $node = $objXml->item($i);
            if(!empty($node->tagName)) {
                $props[PropNs::getInstance()->getPrefixByUri($node->namespaceURI) . ':' . $node->localName] = '';
            }
        }
        return $props;
    }

    /**
     * @inheritDoc
     */
    protected function getArrInput()
    {
        $this->arrInput['set'] = $this->getSetProp();
        $this->arrInput['remove'] = $this->getRemoveProp();
    }
}