<?php

namespace Caldav\Controller;

use Caldav\Model\Base\Controller;
use Caldav\Model\Dav\Resource;
use Caldav\Model\Db\Calendar;
use Caldav\Model\Db\Comp;

class Delete extends Controller
{

    /**
     * @inheritDoc
     */
    public function handler()
    {
        if (!str_starts_with($this->uri, CALENDAR_ROOT)) {
            return ['code' => 403];
        }
        if(in_array(substr($this->uri, -4), ['.ics', '.ifb'])){
            $dbComp = Comp::getInstance();
            $isDel = $dbComp->delByUri($this->uri);
        } else {
            $dbCalendar = Calendar::getInstance();
            $isDel = $dbCalendar->delByUri(rtrim($this->uri, '/') . '/');
        }
        if (null === $isDel){
            return ['code' => 404];
        }

        return ['code' => 200];
    }

    /**
     * @inheritDoc
     */
    protected function getArrInput()
    {
        // TODO: Implement getArrInput() method.
    }
}