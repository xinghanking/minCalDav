<?php

namespace Caldav\Model\Base;

use Exception;
use PDO;
use PDOException;
use PDOStatement;

class Db
{

    const DELETED_NO = 0;
    const DELETED_YES = 1;
    private static $instance = null;
    protected $_tbl = '';
    protected $_sql
        = [
            'SELECT'   => '*',
            'FROM'     => '',
            'WHERE'    => '',
            'ORDER BY' => '',
            'LIMIT'    => '',
        ];
    private static $pdo;
    private $statement;
    private static $arrInstances = [];

    private function __construct($tbl = null)
    {
        try {
            if (empty(self::$pdo)) {
                $config = require __DIR__."/../../../config/calendar.php";
                $dsn    = sprintf('%s:host=%s;port=%d;dbname=%s;charset=%s',
                    $config['database']['driver'], $config['database']['host'],
                    $config['database']['port'], $config['database']['dbname'],
                    $config['database']['charset']);
                self::$pdo = new PDO($dsn, $config['database']['user'], $config['database']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            }
        } catch (PDOException $e) {
            exit('Db connection failed: '.$e->getMessage());
        }
        if(!empty($tbl)) {
            $this->_tbl = $tbl;
        }
        $this->_sql['FROM'] = &$this->_tbl;
        $this->init();
    }

    public static function getInstance()
    {
        if (!static::$instance || !(static::$instance instanceof static)) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    protected function init(){}

    function select($fields, $conditions = [])
    {
        $this->_sql['SELECT'] = is_string($fields) ? $fields : implode(',', $fields);
        if (!empty($conditions)) {
            $this->getConditions($conditions);
        }
        $sql = $this->getSql();
        return $this->query($sql);
    }

    /**
     * @param  array  $conditions
     */
    public function getConditions(array $conditions)
    {
        $conds = array_intersect_key($conditions, $this->_sql);
        if (empty($conds)) {
            $this->_sql['WHERE'] = $conditions;
        } else {
            $this->_sql = array_merge($this->_sql, $conditions);
        }
        if (!empty($this->_sql['WHERE'])) {
            $this->_sql['WHERE'] = self::getWhere($this->_sql['WHERE']);
        }
    }

    /**
     * @param  string|array  $conditions
     * @param  string  $andOr
     *
     * @return string
     */
    public function getWhere($conditions, $andOr = 'AND')
    {
        if (is_string($conditions)) {
            return $conditions;
        }
        if (count($conditions) == 1 && isset($conditions[0])) {
            return $this->getWhere($conditions[0], $andOr);
        }
        if (isset($conditions[0]) && is_array($conditions[0])
            && isset($conditions[1])
            && is_string($conditions[1])
        ) {
            $op = strtoupper(trim($conditions[1]));
            if (in_array($op, ['AND', 'OR'])) {
                return $this->getWhere($conditions[0], $op);
            }
        }
        foreach ($conditions as $k => $v) {
            if (!is_numeric($v) && !is_array($v)) {
                $v = trim($v);
            }
            if (is_numeric($k)) {
                $conditions[$k] = is_string($v) ? $v : $this->getWhere($v);
            } else {
                if (is_numeric($v)) {
                    $conditions[$k] = $k.$v;
                } else {
                    if (is_array($v)) {
                        $this->escapeData($v);
                        $conditions[$k] = $k.' ('.implode(',', $v).')';
                    } else {
                        $conditions[$k] = $k.self::$pdo->quote($v);
                    }
                }
            }
        }
        return '('.implode(') '.$andOr.' (', $conditions).')';
    }

    /**
     * @param  array  $row
     */
    public function escapeData(array &$row)
    {
        foreach ($row as $k => $v) {
            if (is_array($v)) {
                $this->escapeData($row[$k]);
            } elseif (!is_numeric($v)) {
                $row[$k] = self::$pdo->quote($v);
            }
        }
    }

    public function quote($string) {
        return self::$pdo->quote($string);
    }
    private function getSql()
    {
        $sql = [];
        if (!empty($this->_sql['WHERE']) && is_array($this->_sql['WHERE'])) {
            $this->_sql['WHERE'] = self::getWhere($this > $this->_sql['WHERE']);
        }
        foreach ($this->_sql as $k => $v) {
            if (!empty($v)) {
                $sql[] = $k.' '.(is_array($v) ? implode(',', $v) : $v);
            }
        }
        return implode(' ', $sql);
    }


    public function getData($fields, $conditions = [])
    {
        $statement = $this->select($fields, $conditions);
        return $statement->fetchAll();
    }

    public function getRow($fields, $conditions = [])
    {
        $statement = $this->select($fields, $conditions);
        if($statement === false){
            return false;
        }
        if($statement->rowCount() === 0) {
            return [];
        }
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if($row === false){
            return false;
        }
        return $row;
    }

    /**
     * @param $field
     * @param $conditions
     *
     * @return mixed
     */
    public function getColumn($field, $conditions = []) {
        $statement = $this->select($field, $conditions);
        return $statement->fetchColumn();
    }

    /**
     * @param  string  $sql
     *
     * @return false|PDOStatement
     * @throws Exception
     */
    public function query(string $sql, array $params = null)
    {
        $statement = self::$pdo->prepare($sql);
        if ($statement === false) {
            return false;
        }
        if(!empty($params)) {
            foreach ($params as $k => $v) {
                $statement->bindValue($k, $v);
            }
        }
        $statement->execute($params);
        return $statement;
    }

    public function prepare(string $sql)
    {
        return self::$pdo->prepare($sql);
    }

    public function execute($sql, array $params = null)
    {
        $statement = self::$pdo->prepare($sql);
        if ($statement === false) {
            return false;
        }
        if(!empty($params)) {
            foreach ($params as $k => $v) {
                $statement->bindValue($k, $v);
            }
        }
        $statement->execute();
        return $statement->rowCount();
    }

    public function exec(string $sql)
    {
        return self::$pdo->exec($sql);
    }

    public static function beginTransaction()
    {
        return self::$pdo->beginTransaction();
    }

    public static function commit()
    {
        return self::$pdo->commit();
    }

    public static function rollBack()
    {
        return self::$pdo->rollBack();
    }

    public function insert($data, $table = null)
    {
        if (empty($table)) {
            $table = $this->_tbl;
        }
        $fields       = array_keys($data);
        $placeholders = array_map(function ($field) {
            return ":$field";
        }, $fields);

        $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $statement = self::$pdo->prepare($sql);
        $statement->execute(array_combine($placeholders, array_values($data)));
        return self::$pdo->lastInsertId();
    }

    /**
     * @param $data
     * @param $table
     *
     * @return false|int
     */
    public function batchInsert($data, $table = null) {
        if (empty($table)) {
            $table = $this->_tbl;
        }
        $keys = array_keys(current($data));
        foreach($data as $k => $v) {
            $this->escapeData($v);
            $data[$k] = '(' . implode(',', $v) . ')';
        }
        $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $keys) . ') VALUES ' . implode(', ', $data);
        return self::$pdo->exec($sql);
    }

    public function getlastInsertId()
    {
        return self::$pdo->lastInsertId();
    }

    public function delete($where, $table = null)
    {
        if (empty($table)) {
            $table = $this->_tbl;
        }
        $sql = 'DELETE FROM ' . $table . ' WHERE ' . (is_string($where) ? $where : $this->getWhere($where));
        return self::$pdo->exec($sql);
    }

    public function update($data, $where, $table = null)
    {
        if (empty($table)) {
            $table = $this->_tbl;
        }
       foreach ($data as $k => $v) {
           $data[$k] = '`' . $k . '` =' . self::$pdo->quote($v);
       }
       $sql = "UPDATE $table SET " . implode(', ', $data) . " WHERE " . $this->getWhere($where);
       return self::$pdo->exec($sql);
    }

    public function replace($data)
    {
        if (empty($table)) {
            $table = $this->_tbl;
        }
        $this->escapeData($data);
        $sql = 'REPLACE INTO ' . $table . ' (`' . implode('`, `', array_keys($data)) . '`) VALUES (' . implode(', ', $data) . ')';
        self::$pdo->exec($sql);
        return self::$pdo->lastInsertId();
    }

    private function __clone() { }
}