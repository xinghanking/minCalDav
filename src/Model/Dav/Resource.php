<?php

namespace Caldav\Model\Dav;

use Caldav\Model\Db\ResourceContent;
use Caldav\Model\Db\ResourceProp;

class Resource
{
    const RESOURCE_TYPE_FILE = 0;
    const RESOURCE_TYPE_COLLECTION = 1;
    const RESOURCE_TYPE_CALENDAR = 2;
    const RESOURCE_TYPE_SCHEDULE_INBOX = 3;
    const RESOURCE_TYPE_SCHEDULE_OUTBOUND = 4;
    const RESOURCE_TYPE_ADDRESSBOOK = 5;
    private $id;
    private $path;
    private $resource_type;
    private $content_type;
    private $size;
    private $etag;

    private $db;

    private static $resources = [];
    private $children = [];

    private function __construct($path, $info = null){
        $this->path = $path;
        $this->id = $info['id'];
        $this->resource_type = $info['resourcetype'];
        $this->content_type = $info['getcontenttype'];
        $this->etag = $info['getetag'];
        $this->db = \Caldav\Model\Db\Resource::getInstance();
    }

    public static function getInstance($path){
        if (empty(self::$resources[$path])) {
            $dbResource = \Caldav\Model\Db\Resource::getInstance();
            $info = $dbResource->getResourceConf($path);
            if (empty($info)) {
                return $info;
            }
            self::$resources[$path] = new self($path, $info);
        }
        return self::$resources[$path];
    }
    public function getId(){
        return $this->id;
    }
    public function getPath(){
        return $this->path;
    }
    public function getResourceType(){
        return $this->resource_type;
    }

    public function getContentType(){
        return $this->content_type;
    }
    public function getEtag(){
        return $this->etag;
    }
    public function getPropFind(array $fields = ['ns_id', 'prop_name', 'prop_value'], array $prop = [])
    {
        $conditions = ['`resource_id`=' . $this->id];
        if (!empty($prop)) {
            foreach ($prop as $nsId => $propNames) {
                $prop[$nsId] = '`ns_id`=' . $nsId . " AND `prop_name` IN ('" . implode("','", $propNames) . "')";
            }
            if (count($prop) == 1) {
                $conditions[] = current($prop);
            } else {
                $conditions[] = '((' . implode(') OR (', $prop) . '))';
            }
        };
        $dbProp = ResourceProp::getInstance();
        return $dbProp->getProperties($fields, $conditions);
    }

    public function getChildren()
    {
        if (empty($this->children)) {
            $children = $this->db->getChildren($this->id);
            if (!empty($children)) {
                foreach ($children as $child) {
                    $this->children[$child['path']] = new self($child['path'], $child);
                    self::$resources[$child['path']] = &$this->children[$child['path']];
                }
            }
        }
        return $this->children;
    }

    /**
     * 对一个资源的属性进行变更操作
     * @param array $propList
     * @return bool
     */
    public function propPatch(array $propList)
    {
        $dbResourceProp = ResourceProp::getInstance();
        $dbResourceProp->beginTransaction();
        if (isset($propList['set'])) {
            foreach ($propList['set'] as $prop) {
                $dbResourceProp->setResourceProp($this->id, $prop['prop_name'], $prop['prop_value'], $prop['ns_id'], $_SESSION['uid']);
            }
        }
        if (isset($propList['remove'])) {
            foreach ($propList['remove'] as $prop) {
                $dbResourceProp->removeResourceProp($this->id, $prop['prop_name'], $prop['ns_id']);
            }
        }
        $dbResourceProp->commit();
        return true;
    }
    public function remove() {
        $dbResource = \Caldav\Model\Db\Resource::getInstance();
        $dbResourceProp = ResourceProp::getInstance();
        $dbResourceContent = ResourceContent::getInstance();
        try{
            $dbResource->beginTransaction();
            if ($this->resource_type == self::RESOURCE_TYPE_FILE) {
                $dbResource->delete(['`id`=' => $this->id]);
                $dbResourceProp->delete(['`resource_id`=' => $this->id]);
                $dbResourceContent->delete(['`resource_id`=' => $this->id]);
            } else {
                $where = [
                    [
                        '`resource_id`=' . $this->id,
                        "`resource_id` IN (SELECT `id` FROM `resource` WHERE `uri` LIKE '" . $this->path . "/%')"
                    ],
                    'OR'
                ];
                $dbResourceProp->delete($where);
                $dbResourceContent->delete($where);
                $where = [['`id`=' => $this->id, '`uri` LIKE ' => $this->path . '/%'], 'OR'];
                $dbResource->delete($where);
            }
            $dbResource->commit();
            unset(self::$resources[$this->path]);
            return true;
        }
        catch (\Exception $e){
            $dbResourceProp->rollback();
            throw new \Exception($e->getMessage(), $e->getCode(), $e);
        }
    }
    public static function createEtag()
    {
        return time() . '-' . $_SESSION['uid'];
    }
}