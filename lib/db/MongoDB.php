<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/9/19
 * Time: 12:21
 *
 * mongodb数据库驱动
 * https://docs.mongodb.com/manual/reference/command/nav-crud/
 * http://php.net/manual/zh/set.mongodb.php
 *
 */

namespace v\db;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

class MongoDB extends v\Dbase {

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
     * 取得数据库连接data source name
     * replicaSet： host => '192.168.1.1:27017,192.168.1.2:27018/?replicaSet=myReplicaSet'
     * @return string
     */
    protected function dsn($rw = null) {
        $conf = $this->config();
        return 'mongodb://' . (empty($conf['username']) ? '' : "{$conf['username']}:{$conf['password']}@") . $conf['host'] . (empty($conf['port']) ? '' : ":{$conf['port']}");
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
     * @param boolean $isSplitRW 是否读写分离
     * @return mixed
     */
    public function execCmd($command, $isSplitRW = null) {
        $command = new \MongoDB\Driver\Command($command);
        $readPref = is_null($isSplitRW) ? null : new \MongoDB\Driver\ReadPreference($isSplitRW ? \MongoDB\Driver\ReadPreference::RP_SECONDARY_PREFERRED : \MongoDB\Driver\ReadPreference::RP_PRIMARY);
        $this->reset(); // 清空options
        return $this->conn()->executeCommand($this->dbname, $command, $readPref);
    }

    /**
     * 执行命令，返回影响的条数
     * @param array $command
     * @return int
     */
    public function execCmdNum($command, $isSplitRW = null) {
        $cursor = $this->execCmd($command, $isSplitRW);
        $result = current($cursor->toArray());
        return $result->n;
    }

    /**
     * 执行命令，返回影响后的结果
     * @param array $command
     * @return array
     */
    public function execCmdValue($command, $isSplitRW = null) {
        $cursor = $this->execCmd($command, $isSplitRW);
        $result = current($cursor->toArray());
        return empty($result->value) ? 0 : json_decode(json_encode($result->value), true);
    }

    /**
     * field * 处理
     * @param string $fields
     * @return mixed
     */
    public function field($fields) {
        if ($fields === '*') {
            $this->field = null;
            return $this;
        }
        return parent::field($fields);
    }

    /**
     * 解析查询条件
     * $like, $or 解析
     * @param array $query
     * @return array
     */
    public function parseQuery($query) {
        if (empty($query))
            return [];
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

        if (!empty($this->sort)) {
            $options['sort'] = $this->sort;
            if (!empty($this->hint))
                $options['hint'] = $this->hint;
        }
        $options['partial'] = true;

        $query = new \MongoDB\Driver\Query($filter, $options);
        $readPref = new \MongoDB\Driver\ReadPreference(self::$isSplitRW ? \MongoDB\Driver\ReadPreference::RP_SECONDARY_PREFERRED : \MongoDB\Driver\ReadPreference::RP_PRIMARY); // 读写分离
        $this->cursor = $this->conn()->executeQuery("{$this->dbname}.{$this->table}", $query, $readPref);
        $this->cursor->setTypeMap(['root' => 'array', 'document' => 'array']);
        $this->iterator = null;
        return $this->reset();
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
        if ($this->iterator->valid()) {
            $item = $this->iterator->current();
            $this->iterator->next();
            return $item;
        }
        return false;
    }

    /**
     * 取得查询的数据
     * 如果数据量过大，如超过100条，请使用next
     * @return array
     */
    public function find() {
        $isLimit = $this->limit === 1 && is_null($this->skip);  // 是否取单条数据
        $this->query();
        $items = $this->cursor->toArray();
        return $isLimit ? current($items) : $items;
    }

    /**
     * 取得一条数据
     * @return array
     */
    public function findOne() {
        if (empty($this->skip))
            $this->skip = null;
        return $this->limit(1)->find();
    }

    /**
     * 取得多条数据
     * @return array
     */
    public function findMany() {
        if (is_null($this->skip))
            $this->skip = 0;
        return $this->find();
    }

    /**
     * 取得查询条件的数量
     * @return int
     */
    public function count() {
        $filter = $this->parseQuery($this->query);
        $cmd = [
            'count' => $this->table,
            'query' => $filter
        ];
        if (!empty($this->limit) && $this->limit > 0) {
            $cmd['limit'] = $this->limit;
        }
        return $this->execCmdNum($cmd, self::$isSplitRW);
    }

    /**
     * 开始多条数据操作
     * 开始该操作，insert与update不会立即写入到数据库中，持续到commitBulk完成操作
     */
    public function beginBulk() {
        $this->bulker = new \MongoDB\Driver\BulkWrite();
        return $this;
    }

    /**
     * 提交结束多条数据操作
     * @return MongoDB\WriteResult
     */
    public function commitBulk() {
        $rs = 0;
        try {
            $rs = $this->conn()->executeBulkWrite("{$this->dbname}.{$this->table}", $this->bulker);
        } catch (\MongoDB\Driver\Exception\InvalidArgumentException $e) {
            $rs = 0;
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
        }
        $this->bulker = null;
        return $rs;
    }

    /**
     * 开启事务
     * @return $this
     */
    public function startTransaction() {
        return $this;
    }

    /**
     * 提交事务
     * @return $this
     */
    public function commitTransaction() {
        return $this;
    }

    /**
     * 终止事务
     * @return $this
     */
    public function abortTransaction() {
        return $this;
    }

    /**
     * 插入数据
     * options 设置 multi
     * @return int
     */
    public function insert() {
        $data = $this->limit === 1 || !array_is_column($this->data) ? [$this->data] : $this->data;
        // 更新数据的方式处理插入，field.sub字段处理
        foreach ($data as &$item) {
            foreach ($item as $key => $value) {
                if (strpos($key, '.')) {
                    array_setval($item, $key, $value);
                    unset($item[$key]);
                }
            }
        }
        $this->lastID = $item['_id'];

        // 批量操作
        if (!empty($this->bulker)) {
            foreach ($data as $item) {
                $this->bulker->insert($item);
            }
            return $this->reset();
        }

        // 及时插入
        return $this->execCmdNum([
                    'insert'    => $this->table,
                    'documents' => $data,
                    'ordered'   => false
        ]);
    }

    /**
     * 插入一条数据
     * @return int
     */
    public function insertOne() {
        return $this->limit(1)->insert();
    }

    /**
     * 插入多条数据
     * @return int
     */
    public function insertMany() {
        return $this->limit(0)->insert();
    }

    /**
     * 更新数据
     * 默认会更新多条
     * options 设置 multi | upsert
     * @return int  更新的数量
     */
    public function update() {
        array_extend($this->options, ['multi' => !boolval($this->limit), 'upsert' => false], false);
        $data = $this->parseSets($this->data);
        $criteria = $this->parseQuery($this->query);

        // 批量操作
        if (!is_null($this->bulker)) {
            $this->bulker->update($criteria, $data, $this->options);
            return $this->reset();
        }

        // 及时修改
        return $this->execCmdNum([
                    'update'  => $this->table,
                    'updates' => [array_extend($this->options, ['q' => $criteria, 'u' => $data])],
                    'ordered' => false
        ]);
    }

    /**
     * 更新一条数据
     * @return int 更新的数量
     */
    public function updateOne() {
        return $this->limit(1)->update();
    }

    /**
     * 更新多条
     * @return int 更新数据的数量
     */
    public function updateMany() {
        return $this->limit(0)->update();
    }

    /**
     * 更新一条数据，然后返回更新后的数据
     * options中的new参数决定了返回修改前或修改后的数据
     * ->where()->field()->sort()->options(['new'=>true])->findUpdate()
     * @return array
     */
    public function findOneAndUpdate() {
        $cmd = [
            'findAndModify' => $this->table,
            'fields'        => $this->field,
            'new'           => array_value($this->options, 'new', true),
            'query'         => $this->parseQuery($this->query),
            'update'        => $this->parseSets($this->data)
        ];
        if (!empty($this->sort)) {
            $cmd['sort'] = $this->sort;
        }
        return $this->execCmdValue($cmd);
    }

    /**
     * upsert的query与data数据解析
     * @param array $data
     * @return array
     */
    protected function parseUpsert($data, $query) {
        // 多条数据upsert时查询条件的值需要从data中更新
        $query = $this->parseQuery($query);
        array_extend($query, array_delete($data, array_merge(array_keys($query), ['_id'])), true);
        $data = $this->parseSets($data);
        return [$data, $query];
    }

    /**
     * 插入更新，更新数据是如果没有该数据则组合条件与数据插入
     * 只针对满足条件的单条数据
     * @return int
     */
    public function upsert() {
        $data = $this->limit === 1 || !array_is_column($this->data) ? [$this->data] : $this->data;
        array_extend($this->options, ['upsert' => true, 'multi' => false], true);

        // 批量操作
        if (!is_null($this->bulker)) {
            foreach ($data as $item) {
                list($item, $criteria) = $this->parseUpsert($item, $this->query);
                $this->bulker->update($criteria, $item, $this->options);
            }
            return $this->reset();
        }

        // 及时修改插入
        $updates = [];
        foreach ($data as $item) {
            list($item, $criteria) = $this->parseUpsert($item, $this->query);
            $updates[] = array_merge($this->options, ['q' => $criteria, 'u' => $item]);
        }
        return $this->execCmdNum([
                    'update'  => $this->table,
                    'updates' => $updates,
                    'ordered' => false
        ]);
    }

    /**
     * 插入更新单条数据
     * @return int
     */
    public function upsertOne() {
        return $this->limit(1)->upsert();
    }

    /**
     * 插入更新多条数据
     * @return int
     */
    public function upsertMany() {
        return $this->limit(0)->upsert();
    }

    /**
     * upsert一条数据，然后返回更新后的数据
     * options中的new参数决定了返回修改前或修改后的数据
     * ->where()->field()->sort()->options(['new'=>true])->findUpsert()
     * @return array
     */
    public function findOneAndUpsert() {
        list($data, $criteria) = $this->parseUpsert($this->data, $this->query);
        $cmd = [
            'findAndModify' => $this->table,
            'fields'        => $this->field,
            'new'           => array_value($this->options, 'new', true),
            'query'         => $criteria,
            'update'        => $data,
            'upsert'        => true
        ];
        return $this->execCmdValue($cmd);
    }

    /**
     * 删除数据
     * @param array
     * @return int
     */
    public function remove() {
        array_extend($this->options, ['limit' => intval($this->limit)]);
        $criteria = $this->parseQuery($this->query);

        // 批量操作
        if (!is_null($this->bulker)) {
            $this->bulker->delete($criteria);
            return $this->reset();
        }

        // 立即删除
        return $this->execCmdNum([
                    'delete'  => $this->table,
                    'deletes' => [array_extend($this->options, ['q' => $criteria])],
                    'ordered' => false
        ]);
    }

    /**
     * 删除一条数据
     * @return int
     */
    public function removeOne() {
        return $this->limit(1)->remove();
    }

    /**
     * 删除多条数据
     * @return int
     */
    public function removeMany() {
        return $this->limit(0)->remove();
    }

    /**
     * 删除一条数据，然后返回删除的数据
     * ->where()->field()->sort()->findRemove()
     * @return array
     */
    public function findOneAndRemove() {
        $cmd = [
            'findAndModify' => $this->table,
            'fields'        => $this->field,
            'query'         => $this->parseQuery($this->query),
            'remove'        => true
        ];
        if (!empty($this->sort)) {
            $cmd['sort'] = $this->sort;
        }
        return $this->execCmdValue($cmd);
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
        $this->conn();
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
            'indexes'       => $is,
        ];
        $this->execCmd($command);
        return $this;
    }

}
