<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/9/19
 * Time: 12:21
 *
 * postgres 数据库驱动
 * https://www.postgresql.org/docs/10/static/index.html
 * http://php.net/manual/zh/book.pdo.php
 *
 */

namespace v\db;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

class PostgreSQL extends v\Dbase {

    /**
     * 数据库事务记录
     * @var array
     */
    protected static $bulkTrans = [];

    /**
     * PDOStatement对象
     * @var \PDOStatement
     */
    protected $statement = null;

    /**
     * 最后执行的sql记录，便于调试
     * @var string
     */
    protected $lastSQL = '';

    /**
     * 驱动配置
     * @var array
     */
    protected static $configs = [
        'prefix' => 'pgsql',
        'port'   => '5432'
    ];

    /**
     * 取得数据库连接data source name
     * pgsql:host=localhost;port=5432;dbname=testdb;user=bruce;password=mypass
     * 读写分离模式下host=master,slave1,slave2
     * @return string
     */
    protected function dsn($rw = null) {
        $conf = $this->config();
        $prefix = array_value($conf, 'prefix', 'pgsql');  // 默认pgsql驱动
        // 根据主从设置选择host
        $hosts = explode(',', $conf['host']);
        $host = $hosts[0];
        // 开启读写分离后，随机从库中选择链接，SQL只能在只读的情况下开启读写分离
        if ($rw === self::READ_ONLY && count($hosts) > 1) {
            array_shift($hosts);
            $host = array_rand($hosts);
        }
        return "$prefix:host={$host}" . (empty($conf['port']) ? '' : ";port={$conf['port']}") . ";dbname={$conf['dbname']}" . (empty($conf['username']) ? '' : ";user={$conf['username']};password={$conf['password']}");
    }

    /**
     * 连接数据库
     * @param string $dsn
     * @return PDO
     */
    protected function connect($dsn) {
        $options = $this->conf('options', []);
        array_extend($options, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
                ], false);
        $conn = new \PDO($dsn, null, null, $options);
        return $conn;
    }

    /**
     * 执行sql语句
     * @param string $sql  
     * @param array $param 预处理参数值
     * @param boolean $isRead 是否只读
     * @return PDOStatement
     */
    public function execSQL($sql, $param = null, $isRead = false) {
        // 允许不传递param参数
        if (is_bool($param))
            swap($param, $isRead);

        if (v\App::debug())
            $this->lastSQL = $sql;

        $this->reset();  // 重置options数据
        $conn = $isRead ? $this->connr() : $this->conn();

        if (is_null($param)) {
            // 执行普通sql
            $this->statement = $conn->query($sql);
        } else {
            // 执行预处理
            $this->statement = $conn->prepare($sql);
            $this->statement->execute($param);
        }
        return $this->statement;
    }

    /**
     * 获取最后执行的sql语句
     * @return string
     */
    public function lastSQL() {
        return $this->lastSQL;
    }

    /**
     * 获取sql错误消息
     * @return array
     */
    public function lastError() {
        $hander = empty($this->statement) ? $this->conn : $this->statement;
        return $hander->errorInfo();
    }

    /**
     * 定义field字段
     * @param array | string $fields
     * @return $this
     */
    public function field($fields) {
        $rs = parent::field($fields);
        if (empty($this->field)) {
            $this->field = '*';
        } elseif (is_array($this->field)) {
            // 解析数组定义字段
            $fields = array_keys($this->field);
            foreach ($fields as &$field) {
                if (strpos($field, '.')) {
                    $arr = $this->parseSubFieldName($field);
                    $field = "{$arr[0]} AS {$arr[1]}";
                }
            }
            $this->field = implode(', ', $fields);
        }
        return $rs;
    }

    /**
     * group查询转换成sql字符串
     * @param array $fields
     * @return string
     */
    public function group($fields) {
        if (!empty($fields)) {
            if (is_string($fields)) {
                $this->group = $fields;
            } else {
                parent::group($fields);
                $this->group = implode(', ', array_keys($this->group));
            }
        }
        return $this;
    }

    /**
     * 解析查询条件
     * @param array $query
     * @param array $param 预查询绑定参数数组
     * @return string
     */
    public function parseQuery($query, &$param = []) {
        $sql = '';
        $ops = array('$lt' => '<', '$gt' => '>', '$lte' => '<=', '$gte' => '>=', '$like' => 'LIKE', '$ne' => '!=', '$in' => 'IN', '$nin' => 'NOT IN');
        foreach ($query as $k => $v) {
            if ($k == '$or') { // or关系
                $sql .= ' (';
                foreach ($v as $v1) {
                    $sql .= $this->parseQuery($v1, $param) . ' OR ';
                }
                $sql = substr($sql, 0, -4) . ') ';
            } else { // and关系
                // 子文档查询处理
                if (strpos($k, '.')) {
                    list($k, $kp) = $this->parseSubFieldName($k);
                } else {
                    $kp = $k;
                }
                if (is_array($v)) { // 数组计算逻辑符    
                    foreach ($v as $k1 => $v1) {
                        $kp1 = $kp . str_replace('$', '_', $k1);
                        switch ($k1) {
                            case '$in':
                            case '$nin':
                                $v1 = arrayval($v1);
                                $i = 0;
                                $kp3 = '';
                                foreach ($v1 as $v2) {
                                    $kp2 = "{$kp1}_" . $i++;
                                    $kp3 .= ":$kp2,";
                                    $param[$kp2] = $v2;
                                }
                                $sql .= " $k {$ops[$k1]} (" . trim($kp3, ',') . ')';
                                break;
                            default:
                                $sql .= " $k " . array_value($ops, $k1, $k1) . " :$kp1";
                                $param[$kp1] = $v1;
                        }
                        $sql .= ' AND';
                    }
                    $sql = substr($sql, 0, -3);
                } elseif (is_null($v)) {
                    $sql .= " $k IS NULL";
                } else {
                    $param[$kp] = $v;
                    $sql .= " $k = :$kp";
                }
            }
            $sql .= " AND";
        }
        return substr($sql, 0, -3);
    }

    /**
     * 解析upate set
     * @param array $data
     * @return string
     */
    public function parseSets($data, &$param = []) {
        $sql = '';
        $param = [];
        foreach ($data as $k => $v) {
            switch ($k) {
                case '$set':
                    $sql .= ', ' . $this->parseSets($v, $param);
                    break;
                case '$inc':
                    foreach ($v as $k1 => $v1) {
                        if (strpos($v1, '`') === 0) {  // 字段相加处理
                            $sql .= ", $k1 = {$this->table}.$k1 + " . str_replace('`', '', $v1);
                        } else {
                            $kp = "u_$k1";
                            $sql .= ", $k1 = {$this->table}.$k1 + :$kp";
                            $param[$kp] = $v1;
                        }
                    }
                    break;
                case '$unset':
                    foreach ($v as $k1 => $v1) {
                        $sql .= ", $k1 = null";
                    }
                    break;
                default:
                    if (is_string($v) && strpos($v, '`') === 0) {  // 字段等于字段处理
                        $sql .= ", $k = " . str_replace('`', '', $v);
                    } else {
                        // 子文档查询处理
                        if ($pos = strpos($k, '.')) {
                            $this->parseSubFieldValue($k, $v);
                            $kp = "u_$k";
                            $sql .= ", $k = $k || :$kp";
                        } else {
                            $kp = "u_$k";
                            $sql .= ", $k = :$kp";
                        }
                        $param[$kp] = is_array($v) ? json_encode($v) : $v;
                    }
            }
        }
        return substr($sql, 2);
    }

    /**
     * 解析插入的子文档字段与值
     * @param string $key
     * @param mixed $val
     * @return array
     */
    protected function parseSubFieldValue(&$key, &$val) {
        if ($pos = strpos($key, '.')) {
            $tmp = [];
            array_setval($tmp, $key, $val);
            $key = substr($key, 0, $pos);
            $val = $tmp[$key];
        }
        return [$key, $val];
    }

    /**
     * 解析插入预查询数据
     * @param array $data 要插入的数据
     * @param array $param 预查询绑定值
     * @return string
     */
    public function parseValues($data, &$param = []) {
        $field = $value = '';
        foreach ($data as $key => $val) {
            switch ($key) {
                case '$inc':
                case '$set':
                case '$setOnInsert':
                    break;
                default:
                    // 普通情况下需要排除query中的逻辑判断
                    if (is_array($val) && !empty(array_intersect_key($val, ['$gte' => 1, '$gt' => 1, '$lte' => 1, '$lt' => 1, '$ne' => 1])))
                        continue;
                    $val = [$key => $val];
            }
            foreach ($val as $k1 => $v1) {
                $this->parseSubFieldValue($k1, $v1);
                $kp = "u_$k1";
                $field .= ", $k1";
                $value .= ", :$kp";
                $param[$kp] = is_array($v1) ? json_encode($v1) : $v1;
            }
        }
        $field = trim($field, ', ');
        $value = trim($value, ', ');
        return "($field) VALUES ($value)";
    }

    /**
     * 解析多条数据插入
     * @param array $data
     * @return string
     */
    public function parseMany($data) {
        // 字段
        $sql = $this->parseValues(reset($data));
        $sql = substr($sql, 0, strpos($sql, 'VALUES') + 7);
        // 值
        foreach ($data as $item) {
            $str = '';
            foreach ($item as $key => $val) {
                switch ($key) {
                    case '$inc':
                    case '$set':
                    case '$setOnInsert':
                        break;
                    default:
                        // 普通情况下需要排除query中的逻辑判断
                        if (is_array($val) && !empty(array_intersect_key($val, ['$gte' => 1, '$gt' => 1, '$lte' => 1, '$lt' => 1, '$ne' => 1])))
                            continue;
                        $val = [$key => $val];
                }
                foreach ($val as $k1 => $v1) {
                    $this->parseSubFieldValue($key, $v1);
                    if (is_array($v1))
                        $v1 = json_encode($v1);
                    $str .= ', ' . (is_string($v1) ? '\'' . $this->escapeString($v1) . '\'' : $v1);
                }
            }
            $sql .= '(' . trim($str, ', ') . '), ';
        }
        $sql = trim($sql, ', ');
        return $sql;
    }

    /**
     * 解析upsert的UPDATE数据
     * @param array $data
     * @return string
     */
    public function parseUpserts($data) {
        $sql = '';
        unset($data['_id']);  // 主键不允许更新
        foreach ($data as $k => $v) {
            switch ($k) {
                case '$set':
                    $sql .= ', ' . $this->parseUpserts($v);
                    break;
                case '$inc':
                    foreach ($v as $k1 => $v1) {
                        $sql .= ", $k1 = {$this->table}.$k1 + " . $this->parseUpsertValue($k1);
                    }
                    break;
                case '$setOnInsert':  // 插入时更新的数据丢弃
                    break;
                default:
                    $sql .= ", $k = " . $this->parseUpsertValue($k);
            }
        }
        return substr($sql, 2);
    }

    /**
     * 取得插入字段的值
     * 根据驱动不同，子类需要覆盖
     * @param string $field
     * @return string
     */
    protected function parseUpsertValue($field) {
        return "EXCLUDED.$field";
    }

    /**
     * 取得子文档字段与别名
     * 根据驱动不同，子类需要覆盖
     * @param string $field
     * @return array
     */
    protected function parseSubFieldName($field) {
        $pos = strpos($field, '.');
        return [substr_replace(str_replace('.', '\'->\'', $field) . '\'', '', $pos, 1), strtr($field, ['->>' => '_', '->' => '_', '\'' => '', '.' => '_'])];
    }

    /**
     * 按条件查询
     */
    public function query() {
        $param = [];
        $sql = 'SELECT ' . (empty($this->field) ? '*' : $this->field) . " FROM $this->table";

        // 条件where
        if (!empty($this->query))
            $sql .= ' WHERE ' . $this->parseQuery($this->query, $param);

        // group by
        if (!empty($this->group)) {
            $sql .= " GROUP BY $this->group";
        }

        // 排序order by
        if (!empty($this->sort)) {
            $sql .= ' ORDER BY ';
            foreach ($this->sort as $field => $order) {
                $sql .= "$field " . ($order === -1 ? 'DESC' : 'ASC') . ',';
            }
            $sql = trim($sql, ',');
        }

        // 条数limit
        if (!empty($this->limit) && $this->limit > 0)
            $sql .= " LIMIT $this->limit";

        // 跳过offset
        if (!empty($this->skip) && $this->skip > 0)
            $sql .= " OFFSET $this->skip";

//        \v\App::log($this->query);
        $this->execSQL($sql, $param, true);
        return $this;
    }

    /**
     * 逐条取得数据
     * @return array
     */
    public function next() {
        $item = $this->statement->fetch(\PDO::FETCH_ASSOC);
        return empty($item) ? false : $item;
    }

    /**
     * 取得查询的数据
     * 如果数据量过大，如超过100条，请使用next
     * @return array
     */
    public function find() {
        $isLimit = $this->limit === 1 && is_null($this->skip);  // 是否取单条数据
        $this->query();
        return $isLimit ? $this->statement->fetch(\PDO::FETCH_ASSOC) : $this->statement->fetchAll(\PDO::FETCH_ASSOC);
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
     * @param int $limit
     * @return int
     */
    public function count($limit = null) {
        $param = [];
        $sql = "SELECT COUNT(*) FROM $this->table";

        // 条件where
        if (!empty($this->query))
            $sql .= ' WHERE ' . $this->parseQuery($this->query, $param);

        // 条数limit
        if (!empty($this->limit) && $this->limit > 0)
            $sql .= " LIMIT $this->limit";

        return $this->execSQL($sql, $param, true)->fetch(\PDO::FETCH_ASSOC)['count'];
    }

    /**
     * 按链接计算开启事务的次数
     * @return mixed
     */
    protected function incrTransaction($incrNum = 1) {
        $key = md5($this->dsn());
        if (empty(self::$bulkTrans[$key])) {
            self::$bulkTrans[$key] = 1;
        } else {
            self::$bulkTrans[$key] += $incrNum;
        }
        return self::$bulkTrans[$key];
    }

    /**
     * 开始多条数据操作，针对该数据库开启事务
     * 支持事务嵌套
     * 开始该操作，insert与update不会立即写入到数据库中，持续到commitBulk完成操作
     * @return $this
     */
    public function startTransaction() {
        // 如果该数据库链接已经开启事务，则标记+1
        if ($this->incrTransaction(1) == 1) {
            $this->conn()->beginTransaction();
            // 出错后抛出异常
            $this->conn()->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }
        return $this;
    }

    /**
     * 提交结束多条数据操作，提交事务
     * 支持事务嵌套
     * @return $this
     */
    public function commitTransaction() {
        if ($this->incrTransaction(-1) == 0) {
            // 出错后不抛出异常
            $this->conn()->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);
            $this->conn()->commit();
        }
        return $this;
    }

    /**
     * 终止一个事务
     * 支持事务嵌套
     * @return $this
     */
    public function abortTransaction() {
        if ($this->incrTransaction(-1) == 0) {
            // 出错后不抛出异常
            $this->conn()->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);
            $this->conn()->rollBack();
        }
        return $this;
    }

    /**
     * 开始批量操作
     * @return $this
     */
    public function beginBulk() {
        return $this->startTransaction();
    }

    /**
     * 结束批量操作
     * @return $this
     */
    public function commitBulk() {
        return $this->commitTransaction();
    }

    /**
     * options参数解析成sql
     * @param array $options
     * @return string
     * @throws SQLException
     */
    protected function sqlOptions($options = []) {
        // 单条更新,pgsql不支持
        $sql = '';
        /*
          if (!empty($options['limit']) && (empty($this->query) || empty($this->query['_id']))) {
          throw new SQLException('Postgresql not supported update or delete ...limit');
          } */
        // upsert解析，upsert只能支持最后一条数据
        if (!empty($options['upsert'])) {
            $data = array_is_column($this->data) ? end($this->data) : $this->data;
            $conflict = empty($this->query) ? '_id' : implode(',', array_keys($this->query));
            $sql .= " ON CONFLICT($conflict) DO UPDATE SET " . $this->parseUpserts($data);
        }
        // 数据返回
        if (!empty($options['return'])) {
            $sql .= ' RETURNING ' . $this->field;
        }
        return $sql;
    }

    /**
     * 安全编码特殊字符
     * 子类需要继承修改
     * @param string $value
     * @return string
     */
    public function escapeString($value) {
        return pg_escape_string($value);
    }

    /**
     * 插入数据
     * 多条或者单条
     * @return int
     */
    public function insert() {
        $isLimit = $this->limit === 1 || !array_is_column($this->data);
        return $isLimit ? $this->insertOne() : $this->insertMany();
    }

    /**
     * 多条数据插入
     * @return int
     */
    public function insertMany() {
        // 数据过多使用pdo影响效率，使用传统方式
        $sql = "INSERT INTO $this->table " . $this->parseMany($this->data);
        $this->execSQL($sql);
        $item = end($this->data);
        $this->lastID = empty($item['_id']) ? $this->conn()->lastInsertId() : $item['_id'];
        return $this->statement->rowCount();
    }

    /**
     * 插入单条数据
     * @return int 插入数据的数量
     */
    public function insertOne() {
        $data = $this->data;
        $param = [];
        $sql = "INSERT INTO $this->table " . $this->parseValues($data, $param);

        $this->execSQL($sql, $param);
        $this->lastID = empty($data['_id']) ? $this->conn()->lastInsertId() : $data['_id'];
        return $this->statement->rowCount();
    }

    /**
     * 更新数据
     * pgsql不能使用multi=>false参数
     * @return int  更新的数量
     */
    public function update() {
        array_extend($this->options, ['limit' => $this->limit, 'return' => false], false);

        $param = [];
        $sql = "UPDATE {$this->table} SET " . $this->parseSets($this->data, $param);
        if (!empty($this->query)) {
            $sql .= ' WHERE ' . $this->parseQuery($this->query, $param);
        }
        // 参数解析
        $sql .= $this->sqlOptions($this->options);

        $isReturn = !empty($this->options['return']);
        $isLimit = $this->limit === 1;
        $this->execSQL($sql, $param);
        // 不要求数据返回则返回更新的行数，否则返回更新后的数据
        return !$isReturn ? $this->statement->rowCount() :
                ($isLimit ? $this->statement->fetch(\PDO::FETCH_ASSOC) : $this->statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * 更新多条
     * @return int 更新数据的数量
     */
    public function updateMany() {
        return $this->limit(0)->update();
    }

    /**
     * 更新一条数据
     * @return int 更新的数量
     */
    public function updateOne() {
        return $this->limit(1)->update();
    }

    /**
     * 更新一条数据，然后返回更新后的数据
     * ->where()->field()->sort()->findOneAndUpdate()
     * @return array
     */
    public function findOneAndUpdate() {
        return $this->options(['limit' => 1, 'return' => true])->update();
    }

    /**
     * 更新一条数据，然后返回更新后的数据
     * ->where()->field()->sort()->findManyAndUpdate()
     * @return array
     */
    public function findManyAndUpdate() {
        return $this->options(['limit' => 0, 'return' => true])->update();
    }

    /**
     * 插入更新数据单条或多条
     * @return int
     */
    public function upsert() {
        $isLimit = $this->limit === 1 || !array_is_column($this->data);
        return $isLimit ? $this->upsertOne() : $this->upsertMany();
    }

    /**
     * 插入更新，更新数据是如果没有该数据则组合条件与数据插入
     * @return int | array
     */
    public function upsertOne() {
        $param = [];
        $options = array_extend($this->options, ['upsert' => true]);
        if (!empty($this->query)) {
            array_extend($this->data, $this->query, false);
        }
        $sql = "INSERT INTO $this->table " . $this->parseValues($this->data, $param) . $this->sqlOptions($options);

        $isReturn = !empty($this->options['return']);
        $this->execSQL($sql, $param);
        // 不要求数据返回则返回更新的行数，否则返回更新后的数据
        return !$isReturn ? $this->statement->rowCount() : $this->statement->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * 插入更新多条
     * 可以利用该语句做不同条件不同数据的更新
     * @return int | array
     */
    public function upsertMany() {
        $options = array_extend($this->options, ['upsert' => true]);
        if (!empty($this->query)) {
            foreach ($this->data as &$item)
                array_extend($item, $this->query, false);
        }
        $sql = "INSERT INTO $this->table " . $this->parseMany($this->data) . $this->sqlOptions($options);
        $isReturn = !empty($this->options['return']);
        $this->execSQL($sql);
        return !$isReturn ? $this->statement->rowCount() : $this->statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * 插入更新并返回数据，更新数据是如果没有该数据则组合条件与数据插入
     * @return int
     */
    public function findOneAndUpsert() {
        return $this->options(['return' => true, 'limit' => 1])->upsert();
    }

    /**
     * 删除数据
     * @param array
     * @return int
     */
    public function remove() {
        array_extend($this->options, ['limit' => $this->limit], false);
        $param = [];
        $sql = "DELETE FROM {$this->table}";
        if (!empty($this->query)) {
            $sql .= ' WHERE ' . $this->parseQuery($this->query, $param);
        }
        $sql .= $this->sqlOptions($this->options);

        $isReturn = !empty($this->options['return']);
        $isLimit = $this->limit === 1;
        $this->execSQL($sql, $param);
        // 不要求数据返回则返回更新的行数，否则返回更新后的数据
        return !$isReturn ? $this->statement->rowCount() :
                ($isLimit ? $this->statement->fetch(\PDO::FETCH_ASSOC) : $this->statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * 删除多条数据
     * @return int
     */
    public function removeMany() {
        return $this->limit(0)->remove();
    }

    /**
     * 删除一条数据
     * @return int
     */
    public function removeOne() {
        return $this->limit(1)->remove();
    }

    /**
     * 删除一条数据，然后返回删除的数据
     * ->where()->field()->sort()->findRemove()
     * @return array
     */
    public function findOneAndRemove() {
        return $this->options(['limit' => 1, 'return' => true])->remove();
    }

    /**
     * 删除数据，然后返回删除的数据
     * ->where()->field()->sort()->findRemove()
     * @return array
     */
    public function findManyAndRemove() {
        return $this->options(['limit' => 0, 'return' => true])->remove();
    }

    /**
     * 删除表
     */
    public function drop() {
        $sql = "TRUNCATE TABLE {$this->table}";
        $this->execSQL($sql);
        return $this;
    }

    /**
     * 创建索引
     * @param array $indexes
     */
    public function indexes($indexes) {
        throw new SQLException('Driver not supported indexes in v7 php');
    }

}

/**
 * 类异常
 * 类不存在或类继承错误时抛出
 */
class SQLException extends v\Exception {
    
}
