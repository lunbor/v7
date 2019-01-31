<?php

/*
 * v framework
 * 
 * v框架mongodb数据库驱动
 * 
 * @copyright daojon.com
 * @author daojon <daojon@live.com>
 * @version SVN: $Id: Mongo.php 17891 2016-12-01 08:36:23Z liuyang $
 */

namespace v\db;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

/**
 * mongodb数据库驱动类
 */
class Mongo extends v\Dbase {

    /**
     * mongodb cursor对象
     * @var \MongoDB\Driver\Cursor
     */
    protected $cursor = null;

    /**
     * cursor数据队列
     * @var \IteratorIterator
     */
    protected $iterator = null;

    /**
     * 数据操作对象
     * @var \MongoDB\Driver\BulkWrite
     */
    protected $bulker = null;

    /**
     * 是否大批数量操作
     * @var bool
     */
    protected $isBulk = false;

    /**
     * 取得数据库连接data source name
     * @return string
     */
    protected function dsn() {
        $conf = $this->config();
        $dsn = 'mongodb://' . (empty($conf['username']) ? '' : "{$conf['username']}:{$conf['password']}@") . $conf['host'] . (empty($conf['port']) ? '' : ":{$conf['port']}");
        return $dsn;
    }

    /**
     * 连接数据库
     * @param string $dsn
     * @return mixed
     */
    protected function connect($dsn) {
        $options = $this->conf('options', []);
        $conn = new \MongoClient($dsn, $options);
        $conf = $this->config();
        return $conn->selectDB($conf['dbname']);
    }

    /**
     * 执行命令
     * @param array $command  命令
     * @return mixed
     */
    public function execCmd($command = []) {
        
    }

    /**
     * 解析查询条件
     * $like, $or 解析
     * @param array $query
     * @return array
     */
    public function parseQuery($query) {
        //  $or 解析
        if (isset($query['$or'])) {
            foreach ($query['$or'] as $k => $item) {
                $query['$or'][$k] = $this->parseQuery($item);
            }
        }
        // $like 解析
        foreach ($query as $key => $data) {
            if (is_array($data) && !empty($data['$like'])) {
                $value = preg_quote($data['$like']);
                $query[$key] = ['$regex' => '^' . strtr($value, ['%' => '.*?']) . '$', '$options' => 'i'];
            }
        }
        return $query;
    }

    /**
     * 解析set
     * @param array $data
     * @return array
     */
    public function parseSets($data) {
        foreach ($data as $k => $v) {
            if (substr($k, 0, 1) != '$') {
                if (!is_null($v)) {
                    $data['$set'][$k] = $v;
                } else {
                    $data['$unset'][$k] = 1;
                }
                unset($data[$k]);
            }
        }
        return $data;
    }

    public function getField() {
        return is_array($this->field) ? $this->field : [];
    }

    /**
     * 按条件查询
     */
    public function query() {
        $filter = $this->parseQuery($this->query);
        $cursor = $this->conn()->selectCollection($this->table)->find($filter, $this->getField());
        if ($this->skip > 0)
            $cursor->skip($this->skip);
        if ($this->limit > 0)
            $cursor->limit($this->limit);
        if (!empty($this->sort))
            $cursor->sort($this->sort);
        $this->cursor = $cursor;
        $this->iterator = null;
        return $this;
    }

    /**
     * 逐条取得数据
     * @return array
     */
    public function next() {
        return $this->cursor->getNext();
    }

    /**
     * 取得查询的数据
     * 如果数据量过大，如超过100条，请使用next
     * @return array
     */
    public function find() {
        $this->query();
        return iterator_to_array($this->cursor, false);
    }

    /**
     * 查询单条
     * @param array $fields 查询的字段 
     * @return array
     */
    public function findOne() {
        $filter = $this->parseQuery($this->query);
        return $this->conn()->selectCollection($this->table)->findOne($filter, $this->getField());
    }

    /**
     * 更新一条数据，然后返回更新后的数据
     * ->where()->field()->sort()->data()->findUpdate()
     */
    public function findUpdate() {
        
    }

    /**
     * 删除一条数据，然后返回删除的数据
     * ->where()->field()->sort()->data()->findRemove()
     */
    public function findRemove() {
        
    }

    /**
     * 取得查询条件的数量
     * @param int $limit
     * @return int
     */
    public function count($limit = null) {
        $filter = $this->parseQuery($this->query);
        $cursor = $this->conn()->selectCollection($this->table)->find($filter, ['_id']);
        if ($limit) {
            $cursor->limit($limit);
            return $cursor->count(true);
        }
        return $cursor->count();
    }

    /**
     * 开始多条数据操作
     * 开始该操作，insert与update不会立即写入到数据库中，持续到commitBulk完成操作
     */
    public function beginBulk() {
        
    }

    /**
     * 提交结束多条数据操作
     * @return int
     */
    public function commitBulk() {
        
    }

    /**
     * 插入数据
     * @return int
     */
    public function insert() {
        $data = $this->data;
        $multi = isset($data[0]) && is_array($data[0]);
        $rs = 0;
        $this->lastID = 0;
        $conn = $this->conn()->selectCollection($this->table);
        try {
            if ($multi) {
                $options = $this->options;
                $options['continueOnError'] = true;
                $options['w'] = 1;
                $rs = $conn->batchInsert($data, $options);
                if ($rs)
                    $this->lastID = end($data)['_id'];
            } else {
                $rs = $conn->insert($data, $this->options);
                if ($rs)
                    $this->lastID = $data['_id'];
            }
        } catch (\MongoException $e) {
            $rs = 0;
        }
        return $rs;
    }

    /**
     * 更新数据，多条或单条
     * @param array $data
     * @return boolean
     */
    public function update($options = []) {
        $options['multiple'] = !isset($options['multi']) ? true : $options['multi'];
        $data = $this->parseSets($this->data);
        $criteria = $this->parseQuery($this->query);
        try {
            $rs = $this->conn()->selectCollection($this->table)->update($criteria, $data, $options);
        } catch (\MongoException $e) {
            $rs = false;
        }
        if (is_array($rs)) {
            $rs = $rs['n'];
            if ($rs === 0) {
                v\Err::add('Anything not updated');
            }
        }
        return $rs;
    }

    /**
     * 更新一条数据
     * @return int 更新的数量
     */
    public function updateOne() {
        return $this->update(['multi' => false]);
    }

    /**
     * 插入更新，更新数据是如果没有该数据则组合条件与数据插入
     * @return int
     */
    public function upsert() {
        return $this->update(['multi' => false, 'upsert' => true]);
    }

    /**
     * 删除数据
     * @return boolean
     */
    public function remove($options = []) {
        $options['justOne'] = !isset($options['limit']) ? false : $options['limit'];
        $criteria = $this->parseQuery($this->query);
        try {
            $rs = $this->conn()->selectCollection($this->table)->remove($criteria, $options);
        } catch (\MongoException $e) {
            $rs = false;
        }
        return $rs;
    }

    /**
     * 删除一条数据
     * @return int
     */
    public function removeOne() {
        return $this->remove(['limit' => true]);
    }

    /**
     * 删除数据表
     * @return boolean
     */
    public function drop() {
        try {
            $rs = $this->conn()->selectCollection($this->table)->drop();
        } catch (\MongoException $e) {
            $rs = false;
        }
        return $rs;
    }

    /**
     * 建立索引
     * @param array $indexes
     * @return
     */
    public function indexes($indexes) {
        $conn = $this->conn()->selectCollection($this->table);
        foreach ($indexes as $items) {
            $keys = reset($items);
            if (is_array($keys)) {
                $options = isset($items[1]) ? $items[1] : [];
            } else {
                $keys = $items;
                $options = [];
            }
            if (!isset($options['background']))
                $options['background'] = true;
            $conn->ensureIndex($keys, $options);
        }
        return $this;
    }

}

?>