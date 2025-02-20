<?php

namespace Caldav\Utils;

use Caldav\Model\Db\PropNs;
use DOMDocument;

class CalDav
{
    /**
     * 根据命名空间id获取命名空间信息
     * @param int $id
     * @return mixed
     */
    public static function getNsInfoById($id)
    {
        return PropNs::getInstance()->getNsInfoById($id);
    }

    public static function createSyncToken() {
        return time() . '-' . $_SESSION['uid'];
    }

    /**
     * 将路径转化为链接
     * @param string $path
     * @return string
     */
    public static function href_encode($path)
    {
        $path = BASE_URI . $path;
        $arrPath = explode(DIRECTORY_SEPARATOR, $path);
        foreach ($arrPath as $k => $v) {
            $arrPath[$k] = rawurlencode($v);
        }
        $href = implode('/', $arrPath);
        return $href;
    }

    /**
     * 将链接转为资源路径
     * @param string $href
     * @return string|null
     */
    public static function href_decode($href)
    {
        if (0 === strpos($href, 'http')) {
            if (0 === strpos($href, 'http://' . $_REQUEST['HEADERS']['Host'])) {
                $href = substr($href, strlen('http://' . $_REQUEST['HEADERS']['Host']));
            } elseif (0 === strpos($href, 'https://' . $_REQUEST['HEADERS']['Host'])) {
                $href = substr($href, strlen('https://' . $_REQUEST['HEADERS']['Host']));
            } else {
                return null;
            }
        }
        if (0 !== strpos($href, '/')) {
            return null;
        }
        $path = urldecode($href);
        $path = $_REQUEST['DOCUMENT_ROOT'] . $path;
        return $path;
    }

    /**
     * 将代码运行的数组返回结果转化成xml格式
     *
     * @param DOMDocument $xmlDoc
     * @param array $data
     *
     * @return DOMElement|string
     */
    public static function xml_encode(array $data)
    {
        $nsId = isset($data[2]) && is_numeric($data[2]) ? intval($data[2]) : NS_DAV_ID;
        $nsInfo = self::getNsInfoById($nsId);
        $nsUri = $nsInfo['uri'];
        $nsPrefix = $nsInfo['prefix'];
        $qualifiedName = $nsPrefix . ':' . $data[0];
        if (!empty($data[1]) && is_array($data[1])) {
            $nsMap = [$nsPrefix => 'xmlns:' . $nsPrefix . '="' . $nsUri . '"'];
            $element = '';
            foreach ($data[1] as $node) {
                $element .= self::item_encode($node, $nsMap);
            }
            $element = '<' . $qualifiedName . ' ' . implode(' ', $nsMap) . '>' . $element . "</" . $qualifiedName . '>';
        } else {
            $element = '<' . $qualifiedName . ' xmlns:' . $nsPrefix . '="' . $nsUri . '"' . (!isset($data[1]) || $data[1] === '' || is_array($data[1])) ? '/>' : ('>' . strval($data[1]) . '</' . $qualifiedName . '>');
        }
        $element = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $element;
        return $element;
    }

    /**
     * @param array $data
     * @param array $nsMap
     * @return string
     */
    public static function item_encode(array $data, array &$nsMap)
    {
        $nsId = isset($data[2]) && is_numeric($data[2]) ? intval($data[2]) : NS_DAV_ID;
        $nsInfo = self::getNsInfoById($nsId);
        $nsUri = $nsInfo['uri'];
        $nsPrefix = $nsInfo['prefix'];
        if (empty($nsMap[$nsPrefix])) {
            $nsMap[$nsPrefix] = 'xmlns:' . $nsPrefix . '="' . $nsUri . '"';
        }
        $qualifiedName = $nsPrefix . ':' . $data[0];
        $element = '<' . $qualifiedName;
        if (!empty($data[3]) && is_array($data[3])) {
            foreach ($data[3] as $k => $v) {
                $element .= ' '.$k.'="'.$v.'"';
            }
        }
        if (!empty($data[1]) && is_array($data[1])) {
            $element .= '>';
            foreach ($data[1] as $node) {
                $element .= self::item_encode($node, $nsMap);
            }
            $element .= '</' . $qualifiedName . '>';
        } else {
            $element .= ((isset($data[1]) && $data[1] !== '' && !is_array($data[1])) ? ('>' . strval($data[1]) . '</' . $qualifiedName . '>') : '/>');
        }
        return $element;
    }
}