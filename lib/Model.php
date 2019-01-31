<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/8/31
 * Time: 12:21
 */

namespace v;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

abstract class Model extends v\Service {

    use v\ext\QueryData;
    use v\ext\AuditData;

    /**
     * 模型配置
     * @var array
     */
    protected static $configs = [
        'pagingPerMaxRow'   => 100, // 分页每页最大条数
        'pagingCountMaxRow' => 101, // 分页最大统计条数，mongodb默认batch 101
        // 驱动实例class
        'db'                => [
            'service' => null  // 不配置则由v\DB配置决定
        ]
    ];

    /**
     * 索引定义，由子类定义
     * 每个数组代表一个索引
     * 由具体模型定义
     * @var array
     */
    /*
      protected $indexes = [
      ['filedname' => -1, 'fieldname2' => 1],
      [['fieldname' => 1, 'fieldname2' => '2dsphere'], ['background' => false, 'unique' => true]]
      ]; */

    /**
     * 集合名词，必须由子类定义
     * @var string
     */
    protected $table = null;

    /**
     * 数据库驱动实例
     * @var v\Dbase
     */
    protected $db = null;

    /**
     * 最后一条查询的数据
     * @var array
     */
    protected $lastGet = null;

    /**
     * 最后一条添加的数据
     * @var array
     */
    protected $lastAdd = null;

    /**
     * 最后一条更新的数据
     * @var array
     */
    protected $lastUp = null;

    /**
     * 取得数据驱动实例
     * @param array | array $conf 配置
     * @return Dbase
     */
    public function db($conf = []) {
        if (is_null($this->db) || !empty($conf)) {
            if (is_string($conf))
                $conf = $this->conf($conf);
            if (empty($this->table))
                throw new v\PropertyException('Model ' . get_called_class() . ' property table undefined');
            array_extend($conf, $this->config('db'), false);
            $db = v\DB::object($conf);
            $this->db = $db->table($this->table);
        }
        // propvals 为用户当前传入配置，同步到类属性中，然后清除options，为下一次调用做准备，options与field不会同步
        $options = $this->propvals;
        $this->reset();
        $this->options = isset($options['options']) ? array_delete($options, 'options') : [];
        $this->options($options);
        $this->propvals = [];
        return $this->db;
    }

    /**
     * 建立索引
     * @return $this
     */
    public function indexes() {
        if (!empty($this->indexes)) {
            $this->db()->indexes($this->indexes);
        }
        return $this;
    }

    /**
     * 设置|取得表名
     * @param string $table
     * @return $this
     */
    public function table($table = null) {
        if (is_null($table)) {
            return $this->table;
        }
        $this->table = $table;
        if (!empty($this->db)) {
            $this->db = $this->db->table($this->table);
        }
        return $this;
    }

    /**
     * 拷贝一个新的实例，并选择数据库
     * @param string | array $conf 配置KEY
     * @return self
     */
    public function copy($conf = []) {
        $model = clone $this;
        $model->db($conf);
        $model->reset();  // 重新同步options
        return $model;
    }

    /**
     * 给数据生成唯一ID
     * @param array $data ;
     * @return $this;
     */
    protected function guuid(&$data, $setOnInsert = false) {
        if (array_is_column($data)) {
            // 普通数组 $set $setOnInsert 三个数据中判断
            if (empty($data[0]['_id']) && empty($data[0]['$set']['_id']) && empty($data[0]['$setOnInsert']['_id'])) {  // 多条数据_id格式需要一致，这里以第一条数据为准
                foreach ($data as $k => &$item) {
                    if ($setOnInsert) {
                        array_setval($item, '$setOnInsert._id', $this->uniqid($item));
                    } else {
                        $item['_id'] = $this->uniqid($item);
                    }
                }
            }
        } elseif (!isset($data['_id']) && empty($data['$set']['_id']) && empty($data['$setOnInsert']['_id'])) {
            if ($setOnInsert) {
                array_setval($data, '$setOnInsert._id', $this->uniqid($data));
            } else {
                $data['_id'] = $this->uniqid($data);
            }
        }
        return $this;
    }

    /**
     * 生成uniqid
     * 高并发，多服务器清空下请覆盖该方法，使用Redis的uniqid9方法，该方法生成的ID会有重复的问题
     * @param array $data 当前数据
     * @return string
     */
    protected function uniqid($data) {
        return uniqid12();
    }

    /**
     * find取得的字段
     * @param array|string $fields
     *      string format +field1+field2 or -field1-field2
     */
    public function field($fields) {
        if (!empty($fields)) {
            if ($fields === '*') {
                $this->field = '*';
            } elseif (is_array($fields) && isset($fields[0])) {
                $this->field = array_fill_keys($fields, 1);
            } else {
                if (!is_string($fields)) {
                    $state = reset($fields);
                } else {
                    $state = substr($fields, 0, 1) == '-' ? 0 : 1;
                    $fields = explode(',', trim(strtr($fields, [', ' => ',', '-' => ',', '+' => ',', ' ' => ',']), ','));
                    $fields = array_fill_keys($fields, 1);
                }

                if ($state == 0) {
                    // 排除某字段， 在现有字段上排除
                    if (empty($this->field)) {
                        $this->field = array_keys($this->fields);
                        $this->field = array_fill_keys($this->field, 1);
                    }
                    foreach ($fields as $field => $value) {
                        unset($this->field[$field]);
                    }
                } else {
                    $this->field = $fields;
                }
            }
            $this->propvals['field'] = $this->field;  // 同步到options中
        }
        return $this;
    }

    /**
     * 排序
     * @param array|string $sorts ，如果为字符串需要检测是否允许排序
     *      string like -filed1+field2
     */
    public function sort($sorts) {
        if (!empty($sorts)) {
            if (is_string($sorts)) {
                $fields = explode(',', trim(strtr($sorts, ['-' => ',-', '+' => ',+', ' ' => ',+']), ','));
                $sorts = [];
                foreach ($fields as $field) {
                    $state = substr($field, 0, 1) == '-' ? -1 : 1;
                    $field = trim($field, '+ -');
                    $sorts[$field] = $state;
                }
            }
            $this->sort = $sorts;
            $this->propvals['sort'] = $sorts;
        }
        return $this;
    }

    /**
     * 字符或结果数组中的字符串转换成JSON
     * @param array | string $data
     * @return array | string
     */
    public function toJson(&$data) {
        if (is_string($data)) {
            // 字符串转JSON，失败返回原字符串
            $value = json_decode($data, true);
            if (empty($value))
                $value = $data;
            $data = $value;
        } elseif (!empty($data)) {
            // 结果数组，通过要查找的字段判断是否json格式数据转成json
            $fields = empty($this->field) || $this->field === '*' ? $this->fields : $this->field;
            foreach ($fields as $field => $sets) {
                if (isset($this->fields[$field])) {
                    // 列名查询模式
                    if (strpos($sets[0], 'json') === 0) {
                        if (!empty($data[$field]) && is_string($data[$field])) {
                            $data[$field] = json_decode($data[$field], true);
                        } elseif (array_is_column($data)) {
                            // 多行数据
                            foreach ($data as &$item) {
                                if (!empty($item[$field]) && is_string($item[$field])) {
                                    $item[$field] = json_decode($item[$field], true);
                                }
                            }
                        }
                    }
                } elseif (strpos($field, '.')) {
                    // 子文档查询模式
                    $newField = str_replace('.', '_', $field);
                    if (!empty($data[$newField]) && is_string($data[$newField])) {
                        array_setval($data, $field, $this->toJson($data[$newField]));
                        unset($data[$newField]);
                    } elseif (array_is_column($data)) {
                        // 多行数据
                        foreach ($data as &$item) {
                            if (!empty($item[$newField]) && is_string($item[$newField])) {
                                array_setval($item, $field, $this->toJson($item[$newField]));
                                unset($item[$newField]);
                            }
                        }
                    }
                }
            }
        }
        return $data;
    }

    /**
     * 计算数量
     * @param int|null $limit
     * @return int
     */
    public function count($limit = null) {
        if (is_null($limit))
            $limit = $this->limit;
        return $this->db()->where($this->query)->limit($limit)->count();
    }

    /**
     * 取得数据
     * @param array $options 选项设置
     * @return array
     */
    public function getAll($options = []) {
        if (!empty($options))
            $this->options($options);
        $rs = $this->db()->where($this->query)->field($this->field)
                        ->limit($this->limit)->skip(intval($this->skip))->group($this->group)
                        ->sort($this->sort)->hint($this->hint)->find();
        if (!empty($rs) && is_array($rs) && !array_is_column($rs)) {
            $rs = [$rs];
        }
        $this->lastGet = reset($rs);
        return $rs;
    }

    /**
     * 取得一条数据
     * @param array $options 选项设置
     * @return array
     */
    public function getOne($options = []) {
        if (!empty($options))
            $this->options($options);
        $rs = $this->db()->where($this->query)->field($this->field)
                        ->limit(1)->sort($this->sort)->hint($this->hint)->find();
        $this->lastGet = $rs;
        return $rs;
    }

    /**
     * 取得分页数据
     * @param array $options 选项，包含 limit skip field sort row page all
     *         row 每页条数, page 页码,  all 最大统计条数，模糊查询的时候使用
     * @param bool $fuzzyCount 是否模糊统计数据
     * @return array
     */
    public function getPaging($options = [], $fuzzyCount = true) {
        // 分页
        $row = min([empty($options['row']) ? ($this->limit ? $this->limit : 12) : intval($options['row']), $this->conf('pagingPerMaxRow', 100)]);  // 每页数据最大条数，默认100
        $page = intval(array_value($options, 'page', 1));
        $start = $row * ($page - 1);
        $items = $this->skip($start)->limit($row)->getAll($options);
        // 如果参数里有总行数，则使用模糊行数,默认模糊一页数据
        $count = 0;
        if (!empty($items)) {
            if (!$fuzzyCount) {
                $count = $this->count(0);
            } else {
                $all = intval(array_value($options, 'all', $this->conf('pagingCountMaxRow', 101)));  // 最大统计数据条数，默认101条
                $count = count($items);
                $count = $start + $count + ($count == $row ? 1 : 0);  // 如果数据等于页数则向后一页
                // 如果模糊页数小于预估页数，从新算总页数
                if ($count > 0 && $count < $all) {
                    $count = $this->count($all);
                }
            }
        }
        return ['count' => $count, // 总条数
            'page'  => ceil($count / $row), // 总页数
            'data'  => $items];
    }

    /**
     * 按ID取得数据
     * @param $id
     * @param array $options
     * @return array
     */
    public function getByID($id, $options = []) {
        return $this->where(['_id' => $id])
                        ->getOne($options);
    }

    /**
     * 按多个ID取得二维数据
     * @param array $ids
     * @param array $options
     * @return array
     */
    public function getByIDs($ids, $options = []) {
        $ids = arrayval($ids);
        return $this->where(count($ids) === 1 ? ['_id' => reset($ids)] : ['_id' => ['$in' => $ids]])
                        ->getAll($options);
    }

    /**
     * 设置添加数据，由子类继承改变 addOne与addAll的数据
     * @param boolean $isMulti 是否多条数据
     * @return $this;
     */
    protected function setAdd($isMulti) {
        return $this;
    }

    /**
     * 设置更新数据，由子类继承改变 upOne与upAll的数据
     * @param boolean $isMulti 是否多条数据
     * @return $this;
     */
    protected function setUp($isMulti) {
        return $this;
    }

    /**
     * 添加多条数据
     * @return int
     */
    public function addAll() {
        $rs = 0;
        if (empty($this->data)) {
            throw new v\Exception('Data cannot be empty');
        } else if (!array_is_column($this->data)) {
            return $this->addOne();
        } else {
            $this->guuid($this->data)->setAdd(true);
            $rs = $this->db()->data($this->data)->insert();
            $this->lastAdd = null;
        }
        return $rs;
    }

    /**
     * 添加一条数据
     * 会走多条执行方式，子类修改数据，修改多条的数据即可
     * @return int
     */
    public function addOne() {
        $rs = 0;
        if (empty($this->data)) {
            throw new v\Exception('Data cannot be empty');
        } else if (array_is_column($this->data)) {
            throw new v\Exception('Data cannot be multi');
        } else {
            $this->guuid($this->data)->setAdd(false);
            $rs = $this->db()->data($this->data)->limit(1)->insert();
            $this->lastAdd = null;
        }
        return $rs;
    }

    /**
     * 更新多条数据
     * @return int
     */
    public function upAll() {
        $rs = 0;
        if (!empty($this->data)) {
            $this->setUp(true);
            $rs = $this->db()->where($this->query)->data($this->data)->options($this->options)->update();
            $this->lastUp = null;
        }
        return $rs;
    }

    /**
     * 更新一条数据
     * 如果设置了field则会返回修改后的数据，如果返回修改前的数据在mongodb中设置options的new为false
     * @return int | array
     */
    public function upOne() {
        $rs = 0;
        if (!empty($this->data)) {
            $this->setUp(false);
            $db = $this->db()->where($this->query)->data($this->data)->limit(1)->options($this->options);
            $rs = empty($this->field) ? $db->update() : $db->field($this->field)->findOneAndUpdate();
            $this->lastUp = null;
        }
        return $rs;
    }

    /**
     * 按ID更新数据
     * @param string $id
     * @param $options array field等参数设置
     * @return int
     */
    public function upByID($id, $options = []) {
        if (!empty($options))
            $this->options($options);
        return $this->where(['_id' => $id])
                        ->upOne();
    }

    /**
     * upsert插入更新，允许单条与多条数据
     * query与data组合成最终插入数据
     * 必须有query条件，条件的值以data中对应的key为准
     * 不更新的字段请使用'$setOnInsert'
     * @return int | array
     */
    public function upsert() {
        $rs = 0;
        if (empty($this->data)) {
            throw new v\Exception('Data cannot be empty');
        } else {
            $isMulti = array_is_column($this->data);
            $this->setAdd($isMulti);  // 做添加处理
            if (empty($this->query['_id'])) {  // 添加自定义ID
                $this->guuid($this->data, true);
            }
            $db = $this->db()->where($this->query)->data($this->data)->options($this->options);
            if (empty($this->field)) {
                $rs = $db->upsert();
            } elseif (!$isMulti) {
                $rs = $db->field($this->field)->findOneAndUpsert();
            } else {
                v\Err::add('Data cannot be multi');
            }
            $this->lastAdd = $this->lastUp = null;
        }
        return $rs;
    }

    /**
     * 保存数据，只支持单条数据
     * 有该数据时候update，没有时插入数据
     * 该数据会走upOne与addOne，资料对数据的处理不用单独进行
     * @return int
     */
    public function save() {
        $rs = 0;
        if (empty($this->data)) {
            throw new v\Exception('Data cannot be empty');
        } else if (array_is_column($this->data)) {
            throw new v\Exception('Data cannot be multi');
        } else {
            if (empty($this->data['_id'])) {  // 无ID添加
                $rs = $this->addOne();
            } else {  // 有ID更新
                $this->where(array_delete($this->data, ['_id']))->upOne();
            }
        }
        return $rs;
    }

    /**
     * 删除数据
     * @return int
     */
    public function delAll() {
        $rs = 0;
        if (!empty($this->query)) {
            $rs = $this->db()->where($this->query)->remove();
        }
        return $rs;
    }

    /**
     * 删除单条数据
     * @return int
     */
    public function delOne() {
        $rs = 0;
        if (!empty($this->query)) {
            $db = $this->db()->where($this->query);
            $rs = empty($this->field) ? $db->removeOne() : $db->field($this->field)->findOneAndRemove();
        }
        return $rs;
    }

    /**
     * 按ID删除数据
     * @param string $id
     * @return int
     */
    public function delByID($id, $options = []) {
        if (!empty($options))
            $this->options($options);
        return $this->where(['_id' => $id])
                        ->delOne();
    }

    /**
     * 按ID删除数据
     * 支持多个和单个ID，字符串逗号隔开
     * @param array | string $ids
     * @return int
     */
    public function delByIDs($ids) {
        $ids = arrayval($ids);
        return count($ids) === 1 ? $this->where(['_id' => reset($ids)])->delOne() :
                $this->where(['_id' => ['$in' => $ids]])->delAll();
    }

    /**
     * 取得最后插入的ID
     * @return string
     */
    public function lastID() {
        return $this->db()->lastID();
    }

    /**
     * 设置|取得最后添加的一条数据
     * 注意不会改变lastGet的数据
     * @param array $data
     * @return $this
     */
    public function lastAdd($data = null) {
        if (is_null($data)) {
            if (is_null($this->lastAdd)) {
                $this->lastAdd = [];
                if ($id = $this->db()->lastID()) {
                    $this->lastAdd = $this->db()->where(['_id' => $id])->findOne();
                }
            }
            return $this->lastAdd;
        }
        $this->lastAdd = $data;
        return $this;
    }

    /**
     * 设置|取得最后更新后的一条数据
     * 该方法会重新查询一次数据库并缓存
     * 注意不会改变lastGet的数据
     * @param array $data
     * @return $this
     */
    public function lastUp($data = null) {
        if (is_null($data)) {
            if (is_null($this->lastUp)) {
                $this->lastUp = $this->db()->where($this->query)->findOne();
                if (is_null($this->lastUp))
                    $this->lastUp = [];
            }
            return $this->lastUp;
        }
        $this->lastUp = $data;
        return $this;
    }

    /**
     * 设置|取得最后查询的一条数据
     * 注意如果没有做过查询操作会使用最后更新的条件进行查询
     * @param array $data
     * @return $this
     */
    public function lastGet($data = null) {
        if (is_null($data)) {
            if (is_null($this->lastGet)) {
                $this->lastGet = $this->db()->where($this->query)->findOne();
            }
            return $this->lastGet;
        }
        $this->lastGet = $data;
        return $this;
    }

    /**
     * 数据Join
     * 通过该模型ID，连接该模型的数据
     * @param array $data 要关联的数据
     * @param string $foreignKey 关联数据的外键
     * @param array|string $field 要关联的字段
     * @return array
     */
    public function joinTo(&$data, $foreignKey, $field = null, $prefix = null) {
        $prefix = is_null($prefix) ? strtolower(ltrim(strrchr(get_class($this), '\\'), '\\')) : $prefix;
        if (!empty($prefix)) {
            $prefix = $prefix . '_';
        }
        if (array_is_column($data)) {
            // 多条
            $ids = array_column($data, $foreignKey);
            if (!empty($ids)) {
                $ids = array_values(array_unique($ids));
                $items = $this->getByIDs($ids, ['field' => $field]);
                $items = array_column_askey($items, '_id');
                // 信息合并 modelname_fieldname
                foreach ($data as &$item) {
                    if (!empty($item[$foreignKey]) && !empty($items[$item[$foreignKey]])) {
                        foreach ($items[$item[$foreignKey]] as $f => $v) {
                            if ($f != '_id')
                                $item["{$prefix}{$f}"] = $v;
                        }
                    }
                }
            }
        } elseif (!empty($data[$foreignKey])) {
            // 单条
            $item = $this->getByID($data[$foreignKey], ['field' => $field]);
            if (!empty($item)) {
                foreach ($item as $f => $v) {
                    if ($f != '_id')
                        $data["{$prefix}{$f}"] = $v;
                }
            }
        }
        return $data;
    }

}
