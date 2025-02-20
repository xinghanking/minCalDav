<?php

namespace Caldav\Model\Db;

use Caldav\Model\Base\Db;
use Exception;

class PropNs extends Db
{
    const TABLE = 'prop_ns';
    const LIMIT = 256;
    const CAL_ID = 1;
    const CS_ID  = 2;
    public static array $uriMap = [];
    public static array $nsList = [];
    private array $prefixDict = [
        'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y',
        'Z', 'A', 'B', 'C', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r',
        's', 't', 'u', 'v', 'w', 'x', 'y', 'z'
    ];
    public static $prefixUri = [
        'd'    => 'DAV:',
        'c'    => 'urn:ietf:params:xml:ns:caldav',
        'cs'   => 'http://calendarserver.org/ns/',
        'ics'  => 'http://icalendar.org/ns/',
        'card' => 'urn:ietf:params:xml:ns:carddav',
        'vc'   => 'urn:ietf:params:xml:ns:vcard'
    ];
    private static $prefixNsId = [];

    /**
     * 初始化资源属性命名空间类
     */
    protected function init()
    {
        $this->_tbl = '`' . self::TABLE . '`';
        $arrRes = $this->getData(['id', 'uri', 'prefix'], ['LIMIT' => self::LIMIT]);
        if (is_array($arrRes)) {
            foreach ($arrRes as $ns) {
                self::$nsList[$ns['id']] = ['prefix' => $ns['prefix'], 'uri' => $ns['uri']];
                self::$uriMap[$ns['uri']] = $ns['id'];
                self::$prefixNsId[$ns['prefix']] = $ns['id'];
            }
        }
    }

    public static function getNsIdByPrefix(string $prefix) {
        self::getInstance();
        return self::$prefixNsId[$prefix];
    }

    /**
     * 根据uri获取命名空间id
     * @param string $uri 命名空间uri
     * @return int|mixed
     * @throws Exception
     */
    public function getNsIdByUri($uri)
    {
        if(empty($uri)) {
            return NS_DAV_ID;
        }
        if (isset(self::$uriMap[$uri])) {
            return self::$uriMap[$uri];
        }
        $id = $this->getColumn('`id`', ['`uri`=' => $uri]);
        if (is_numeric($id)) {
            self::$uriMap[$uri] = $id;
            return $id;
        }
        $info = ['uri' => $uri, 'user_agent' => $_REQUEST['header']['user-agent']];
        $id = $this->insert($info);
        if (isset($this->prefixDict[$id])) {
            $prefix = $this->prefixDict[$id];
        } else {
            $num = count($this->prefixDict);
            $prefix = $this->prefixDict[$id % $num - 1] . floor($id / $num);
        }
        $this->update(['prefix' => $prefix], ['`uri`=' => $uri]);
        self::$uriMap[$uri] = $id;
        self::$nsList[$id] = ['prefix' => $prefix, 'uri' => $uri];
        return $id;
    }

    /**
     * 根据id查询命名空间信息
     * @param int $id 命名空间id
     * @return array|mixed
     * @throws Exception
     */
    public function getNsInfoById($id)
    {
        if (isset(self::$nsList[$id])) {
            return self::$nsList[$id];
        }
        $info = $this->getRow(['prefix', 'uri'], ['`id`=' => $id]);
        if (empty($info)) {
            throw new Exception('有命名空间id查不到对应的信息，可能是prop_ns表中有数据丢失，id = ' . $id);
        }
        self::$nsList[$id] = $info;
        self::$uriMap[$info['uri']] = $id;
        return $info;
    }

    public function getPrefixById($id)
    {
        $info = $this->getNsInfoById($id);
        return $info['prefix'];
    }
    public function getPrefixByUri($uri)
    {
        $prefix = array_search($uri, self::$uriMap);
        if ($prefix !== false) {
            return $prefix;
        }
        $id = $this->getNsIdByUri($uri);
        return self::$nsList[$id]['prefix'];
    }
}