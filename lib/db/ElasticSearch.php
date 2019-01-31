<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/9/19
 * Time: 12:21
 *
 * elasticsearch全文索引数据库驱动
 *
 */

namespace v\db;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

class ElasticSearch extends v\Dbase {

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
        $dsn = 'mongodb://' . (empty($conf['username']) ? '' : "{$conf['username']}:{$conf['password']}@") . $conf['host'];
        return $dsn;
    }

    /**
     * 连接数据库
     * @param string $dsn
     * @return mixed
     */
    protected function connect($dsn) {
        $options = $this->conf('options', []);
        $conn = new \MongoDB\Driver\Manager($dsn, $options);
        return $conn;
    }

    /**
     * 执行命令
     * @param array $command  命令
     * @return mixed
     */
    public function execCmd($command = []) {
        $command = new \MongoDB\Driver\Command($command);
        return $this->conn()->executeCommand($this->dbname, $command);
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

    /**
     * 按条件查询
     */
    public function query() {
        $filter = $this->parseQuery($this->query);
        $options = [];
        if (!empty($this->field))
            $options['projection'] = $this->field;

        if (!empty($this->limit) && $this->limit > 0)
            $options['limit'] = $this->limit;

        if (!empty($this->skip) && $this->skip > 0)
            $options['skip'] = $this->skip;

        if (!empty($this->sort))
            $options['sort'] = $this->sort;

        $options['partial'] = true;
        $query = new \MongoDB\Driver\Query($filter, $options);
        $this->cursor = $this->conn()->executeQuery("{$this->dbname}.{$this->table}", $query);
        $this->cursor->setTypeMap(['root' => 'array', 'document' => 'array']);
        $this->iterator = null;
        return $this;
    }

    /**
     * 逐条取得数据
     * @return array
     */
    public function next() {
        if (is_null($this->iterator)) {
            $this->iterator = new \IteratorIterator($this->cursor);
            $this->iterator->rewind();
        }
        $item = $this->iterator->current();
        $this->iterator->next();
        return $item;
    }

    /**
     * 取得查询的数据
     * 如果数据量过大，如超过100条，请使用next
     * @return array
     */
    public function find() {
        $this->query();
        return $this->cursor->toArray();
    }

    /**
     * 取得一条数据
     * @return array
     */
    public function findOne() {
        $this->limit = 1;
        $this->query();
        return current($this->cursor->toArray());
    }

    /**
     * 更新一条数据，然后返回更新后的数据
     * ->where()->field()->sort()->data()->findUpdate()
     */
    public function findUpdate() {
        $cmd = [
            'findAndModify' => $this->table,
            'fields' => $this->field,
            'query' => $this->parseQuery($this->query),
            'update' => $this->parseSets($this->data)
        ];
        if (!empty($this->sort)) {
            $cmd['sort'] = $this->sort;
        }
        $cursor = $this->execCmd($cmd);
        $result = current($cursor->toArray());
        return $result->value;
    }

    /**
     * 删除一条数据，然后返回删除的数据
     * ->where()->field()->sort()->data()->findRemove()
     */
    public function findRemove() {
        $cmd = [
            'findAndModify' => $this->table,
            'fields' => $this->field,
            'query' => $this->parseQuery($this->query),
            'remove' => true
        ];
        if (!empty($this->sort)) {
            $cmd['sort'] = $this->sort;
        }
        $cursor = $this->execCmd($cmd);
        $result = current($cursor->toArray());
        return $result->value;
    }

    /**
     * 取得查询条件的数量
     * @param int $limit
     * @return int
     */
    public function count($limit = null) {
        $filter = $this->parseQuery($this->query);
        $cmd = [
            'count' => $this->table,
            'query' => $filter
        ];
        if (!empty($limit)) {
            $cmd['limit'] = intval($limit);
        }
        $cursor = $this->execCmd($cmd);
        $result = current($cursor->toArray());
        return $result->n;
    }

    /**
     * 开始多条数据操作
     * 开始该操作，insert与update不会立即写入到数据库中，持续到commitBulk完成操作
     */
    public function beginBulk() {
        $this->isBulk = true;
        $this->bulker = new \MongoDB\Driver\BulkWrite();
        return $this;
    }

    /**
     * 提交结束多条数据操作
     * @return int
     */
    public function commitBulk() {
        $this->isBulk = false;
        $rs = 0;
        try {
            $rs = $this->conn()->executeBulkWrite("{$this->dbname}.{$this->table}", $this->bulker);
        } catch (\MongoDB\Driver\Exception\BulkWriteException $e) {
            $result = $e->getWriteResult();
            // 没有所有服务器都写入错误
            if ($writeConcernError = $result->getWriteConcernError()) {
                v\Err::add($writeConcernError->getMessage());
            }
            // 一些数据没有写入
            foreach ($result->getWriteErrors() as $writeError) {
                v\Err::add($writeError->getMessage());
            }
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            v\Err::add($e->getMessage());
        }
        $this->bulker = null;
        return $rs;
    }

    /**
     * 插入数据
     * @return int
     */
    public function insert() {
        if (!$this->isBulk) // 非bulk操作，new BulkWrite
            $this->bulker = new \MongoDB\Driver\BulkWrite();

        $data = $this->data;
        $multi = isset($data[0]) && is_array($data[0]);
        if ($multi) {
            foreach ($data as $item) {
                $this->bulker->insert($item);
            }
            $this->lastID = end($data)['_id'];
        } else {
            $this->bulker->insert($data);
            $this->lastID = $data['_id'];
        }
        if (!$this->isBulk) {
            $rs = $this->commitBulk();
            return $rs ? $rs->getInsertedCount() : $rs;
        }
        return $this;
    }

    /**
     * 更新数据
     * @param array $options  设置 multi | upsert
     * @return int  更新的数量
     */
    public function update($options = []) {
        if (!isset($options['multi']))
            $options['multi'] = true;

        $data = $this->parseSets($this->data);
        $criteria = $this->parseQuery($this->query);
        if (!$this->isBulk)  // 非bulk操作，new BulkWrite
            $this->bulker = new \MongoDB\Driver\BulkWrite();

        $this->bulker->update($criteria, $data, $options);

        if (!$this->isBulk) {  // 非bulk操作
            $rs = $this->commitBulk();
            return $rs ? isset($options['upsert']) ? $rs->getUpsertedCount() : $rs->getModifiedCount() : $rs;
        }
        return $this;
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
     * @param array
     * @return int
     */
    public function remove($options = []) {
        if (!isset($options['limit']))
            $options['limit'] = false;

        $criteria = $this->parseQuery($this->query);

        if (!$this->isBulk)  // 非bulk操作，new BulkWrite
            $this->bulker = new \MongoDB\Driver\BulkWrite();

        $this->bulker->delete($criteria);

        if (!$this->isBulk) {  // 非bulk操作
            $rs = $this->commitBulk();
            return $rs ? $rs->getDeletedCount() : $rs;
        }
        return $this;
    }

    /**
     * 删除一条数据
     * @return int
     */
    public function removeOne() {
        return $this->remove(['limit' => true]);
    }

    /**
     * 删除表
     */
    public function drop() {
        $cmd = ['drop' => $this->table];
        $this->execCmd($cmd);
        return $this;
    }

    /**
     * 创建索引
     * @param array $indexes
     */
    public function indexes($indexes) {
        foreach ($indexes as $items) {
            if (!isset($items['key'])) {
                $keys = reset($items);
                if (is_array($keys)) {
                    $items = empty($items[1]) ? [] : $items[1];
                } else {
                    $keys = $items;
                    $items = [];
                }
                $name = '';
                foreach ($keys as $key => $v) {
                    $name .= "_{$key}_{$v}";
                }
                $items = array_merge(['key' => $keys, 'ns' => "{$this->dbname}.{$this->table}", 'name' => substr($name, 1)], $items);
            }
            $is[] = $items;
        }
        $command = [
            'createIndexes' => $this->table,
            'indexes' => $is,
        ];
        $this->execCmd($command);
        return $this;
    }

}
