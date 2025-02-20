<?php

namespace Caldav\Controller;

use Caldav\Model\Base\Controller;

class Head extends Controller
{

    /**
     * @inheritDoc
     */
    public function handler()
    {
        $rqGet = new Get($this->request);
        $response = $rqGet->handler();
        $response['header']['content-length'] = strlen($response['body']);
        return ['code' => $response['code'], 'header' => $response['header']];
    }

    /**
     * @inheritDoc
     */
    protected function getArrInput()
    {
        // TODO: Implement getArrInput() method.
    }
}