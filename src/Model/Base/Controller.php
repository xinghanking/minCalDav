<?php

namespace Caldav\Model\Base;

use Caldav\Model\Db\PropNs;
use Caldav\Model\Db\ResourceProp;
use Caldav\Utils\Dav_Status;
use Caldav\Utils\CalDav;
use DOMDocument;
use DOMElement;
use Exception;

abstract class Controller
{
    protected $header;
    protected $request;
    protected $uri;
    protected $objXml;
    protected $response;
    protected $arrInput = [];
    protected $prefixUri = [];
    protected bool $formatStatus = true;

    /**
     * 构造函数，初始化并数组格式化前端发来的请求数据
     */
    public function __construct($request, $response = null)
    {
        try {
            $this->request   = $request;
            $this->header    = $request->header;
            $this->uri       = $_REQUEST['resource'];
            $this->response  = $response;
            $this->prefixUri = array_merge(PropNs::$prefixUri, $this->prefixUri);
            $this->getArrInput();
        } catch (Exception $e) {
            $this->formatStatus = false;
            //Dav_Log::error($e);
        }
    }

    /**
     * 调用执行程序处理客户端发来的请求任务，并返回数组格式化的处理结果
     * @return array
     */
    public function execute(&$responseHeader = null, &$responseBody = null)
    {
        if (false === $this->formatStatus) {
            $response = ['code' => 422];
        } else {
            try {
                //Db::beginTransaction();
                $response = $this->handler();
                //Db::commit();
            } catch (Exception $e) {
                //Db::rollback();
                $code = $e->getCode();
                $msg = $e->getMessage();
                if (!isset(Dav_Status::$Msg[$code]) || $msg != Dav_Status::$Msg[$code]) {
                    $response['code'] = 503;
                    //Dav_Log::error($e);
                } else {
                    $response['code'] = $code;
                }
            }
        }
        if (isset($response['code']) && isset(Dav_Status::$Msg[$response['code']])) {
            $response['header'] = [Dav_Status::$Msg[$response['code']]];
            if (isset($response['headers']) && is_array($response['headers'])) {
                $response['header'] = array_merge($response['header'], $response['headers']);
                unset($response['headers']);
            }
            if (isset($response['body'])) {
                if (is_array($response['body'])) {
                    $response['body'] = CalDav::xml_encode($response['body']);
                }
                $response['header'][] = 'Content-Length: ' . strlen($response['body']);
            } else {
                $response['header'][] = 'Content-Length: 0';
            }
        }
        return $response;
    }

    /**
     * 执行客户端发来的请求任务并返回执行结果
     * @return mixed
     */
    abstract protected function handler();

    /**
     * 数组格式化客户端发来的请求数据项
     * @return mixed
     */
    protected function getArrInput() {}

    protected function getBody()
    {
        $_REQUEST['body'] = $this->request->getContent();
        return $_REQUEST['body'];
    }

    protected function getObjXml() {
        if(empty($this->objXml)) {
            $this->getBody();
            if(empty($_REQUEST['body'])) {
                return null;
            }
            $inputEncoding = mb_detect_encoding($_REQUEST['body']);
            if (!empty($inputEncoding) && 'UTF-8' != $inputEncoding) {
                $_REQUEST['body'] = mb_convert_encoding($_REQUEST['body'], 'UTF-8', $inputEncoding);
            }
            $this->objXml = new DOMDocument();
            $this->objXml->loadXML($_REQUEST['body']);
        }
        return $this->objXml;
    }

    /**
     * @return array
     */
    public function getSetProp() {
        $objXml = $this->xpath('set/prop');
        if(empty($objXml)){
            return [];
        }
        $objXml = $objXml[0]->childNodes;
        $props = [];
        for ($i=0; $i<$objXml->length; $i++){
            $node = $objXml->item($i);
            if(!empty($node->localName)) {
                if ($node->childNodes->length == 1 && empty($node->childNodes->item(0)->tagName)) {
                    $nodeValue = trim($node->childNodes->item(0)->nodeValue);
                } else {
                    $nodeValue = $this->xmlToArray($node->childNodes);
                }
                $props[] = [
                    'prop_name'  => trim($node->localName),
                    'prop_value' => $nodeValue,
                    'ns_id'      => PropNs::getInstance()->getNsIdByUri($node->namespaceURI)
                ];
            }
        }
        return $props;
    }

    /**
     * @param $path
     * @param  DOMDocument|null  $objXml
     *
     * @return array|null|DOMDocument[]
     */
    protected function xpath($path, DOMElement $objXml = null) {
        if (empty($objXml)) {
            $objXml = $this->getObjXml();
        }
        $path = explode('/', $path, 2);
        if (!str_contains($path[0], ':')) {
            $obj = $objXml->getElementsByTagName($path[0]);
        } else {
            [$prefix, $name] = explode(':', $path[0], 2);
            $obj = $objXml->getElementsByTagNameNS($this->prefixUri[$prefix], $name);
        }
        if ($obj->length == 0) {
            return null;
        }
        $res = [];
        if (empty($path[1])) {
            for($i=0; $i < $obj->length; $i++) {
                $res[] = $obj->item($i);
            }
            return $res;
        }
        for ($i = 0; $i < $obj->length; $i++) {
            $r = $this->xPath($path[1],$obj->item($i));
            if (!empty($r)) {
                $res = array_merge($res, $r);
            }
        }
        return $res;
    }

    protected function xmlToArray(\DOMNodeList $xml)
    {
        $nodeValue = [];
        for ($i=0; $i < $xml->length; $i++) {
            if(!empty($xml->item($i)->tagName)) {
                if (empty($xml->item($i)->childNodes) || ($xml->item($i)->childNodes->length == 1 && empty($xml->item($i)->childNodes->item(0)->tagName))) {
                    $v = trim($xml->item($i)->nodeValue);
                } else {
                    $v = $this->xmlToArray($xml->item($i)->childNodes);
                    $v = empty($v) ? '' : $v;
                }
                $attrs = [];
                $x = $xml->item($i)->attributes;
                if (!empty($x) && $x->length > 0) {
                    for ($j = 0; $j < $x->length; $j++) {
                        $attrs[$x->item($j)->nodeName] = $x->item($j)->nodeValue;
                    }
                }
                $nodeValue[] = [
                    $xml->item($i)->localName, $v, PropNs::getInstance()->getNsIdByUri($xml->item($i)->namespaceURI), $attrs
                ];
            }
        }
        return $nodeValue;
    }
}