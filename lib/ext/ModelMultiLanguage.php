<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2018/3/20
 * Time: 10:31
 *
 * 多语言字段模型
 * 
 * 在fields中定义多语言字段的格式
 * jsonlang格式：json的格式保存
 * strlang格式：字符串的格式保存，需要定义对应的语言字段field_lang，校验不能必填
 * 多语言的字段，多一种语言请多定义一个字段
 * 加以使用jsonlang格式
 * 
 * 如果要同时处理多个语言字段，请不要调用lang方法。
 * 
 */

namespace v\ext;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

trait ModelMultiLanguage {
    /**
     * 字段定义示例
     */
    /*
      protected $fields = [
      'field1' => ['jsonlang'],
      'field2' => ['strlang']
      ]; */

    /**
     * 多语言字段映射关系
     * @var array
     */
    protected $fieldsMaps = [];

    /**
     * 语言定义
     * @var string 
     */
    protected $lang = null;

    /**
     * 语言函数调用次数，防止二次调用
     * @var array
     */
    protected $langCallNum = [];

    /**
     * json lang类型，标明语言字段
     * @param string $value
     * @return array | string
     */
    public function jsonlang($value) {
        return is_array($value) ? $value : (is_string($value) && $value[0] === '{' ? json_decode($value, true) : $value);
    }

    /**
     * string lang类型，标识语言字段
     * @param string $value
     */
    public function strlang($value) {
        return str_striptrim($value);
    }

    /**
     * 语言定义
     * @param string | false | null $lang
     * @return $this
     */
    public function lang($lang = true) {
        // 从App取默认语言
        if ($lang === true) {
            $lang = v\App::lang();
        }
        $lang = str_replace('-', '_', $lang);
        if (!empty($lang) && $lang !== $this->lang) {
            $this->lang = $lang;
            $this->fieldsMaps = [];
            // 语言字段映射
            foreach ($this->fields as $field => $sets) {
                if (isset($sets[0])) {
                    switch ($sets[0]) {
                        case 'jsonlang':  // json格式
                            $fieldML = "{$field}.{$lang}";
                            break;
                        case 'strlang':  // 字符串格式另外定义字段
                            $fieldML = "{$field}_{$lang}";
                            $sets[0] = 'string';
                            if (!isset($this->fields[$fieldML]))
                                $this->fields[$fieldML] = $sets;
                    }
                    if (!empty($fieldML)) {
                        $this->fieldsMaps[$field] = $fieldML;
                        $fieldML = null;
                    }
                }
            }

            // hook获取的字段
            if (!empty($this->field) && $this->field !== '*') {
                array_rekey($this->field, $this->fieldsMaps);
                $this->propvals['field'] = $this->field;
            }

            // hook插入更新的字段
            if (!empty($this->data)) {
                array_column_rekey($this->data, $this->fieldsMaps);
            }

            // hook查找的字段
            if (!empty($this->query) && !empty($this->query)) {
                array_column_rekey($this->data, $this->fieldsMaps);
            }
        }
        return $this;
    }

    /**
     * 查询条件的处理
     * @param array $query 查询条件
     * @return $this
     */
    public function where($query = null) {
        // hook where的字段
        if (!empty($this->lang) && !empty($query)) {
            array_rekey($query, $this->fieldsMaps);
        }
        return parent::where($query);
    }

    /**
     * 查询字段处理
     * 对语言字段进行hook
     * @param array $fields
     * @return $this
     */
    public function field($fields) {
        $rs = parent::field($fields);
        // hook find的字段
        if (!empty($this->lang) && $this->field !== '*') {
            array_rekey($this->field, $this->fieldsMaps);
            $this->propvals['field'] = $this->field;
        }
        return $rs;
    }

    /**
     * 取得一条数据
     * 多语言下还原字段的值
     * @param array $options
     * @return array
     */
    public function getOne($options = array()) {
        $this->langCallNum['getone'] = array_value($this->langCallNum, 'getone', 0) + 1;
        $item = parent::getOne($options);
        if (!empty($this->lang) && !empty($item) && $this->langCallNum['getone'] === 1) {
            $this->toJson($item);  // sql下需要转换成JSON
            foreach ($this->fieldsMaps as $key => $keyML) {
                if (isset($item[$key]))
                    $item[$key] = array_delete($item, $keyML);
            }
        }
        $this->langCallNum['getone'] --;
        return $item;
    }

    /**
     * 取得多条数据
     * 多语言下还原字段的值
     * @param array $options
     * @return array
     */
    public function getAll($options = array()) {
        $this->langCallNum['getall'] = array_value($this->langCallNum, 'getall', 0) + 1;
        $items = parent::getAll($options);
        if (!empty($this->lang) && !empty($items) && $this->langCallNum['getall'] === 1) {
            foreach ($items as &$item) {
                $this->toJson($item);  // sql下需要转换成JSON
                foreach ($this->fieldsMaps as $key => $keyML) {
                    if (isset($item[$key]))
                        $item[$key] = array_delete($item, $keyML);
                }
            }
        }
        $this->langCallNum['getall'] --;
        return $items;
    }

    /**
     * 更新语言字段只更新对应语言字段的值
     * @return $this
     */
    protected function upLangFieldHook() {
        if (empty($this->lang)) {
            // 语言字段映射
            foreach ($this->fields as $field => $sets) {
                if (isset($sets[0]) && $sets[0] === 'jsonlang' && !empty($this->data[$field])) {
                    foreach ($this->data[$field] as $lang => $value) {
                        $this->data["$field.$lang"] = $value;
                    }
                    unset($this->data[$field]);
                }
            }
        }
        return $this;
    }

    /**
     * 更新一条数据
     * 多语言下还原字段的值
     * @return array | int
     */
    public function upOne() {
        $this->upLangFieldHook();
        $item = parent::upOne();
        if (!empty($this->lang) && !empty($item) && is_array($item)) {
            $this->toJson($item);  // sql下需要转换成JSON
            foreach ($this->fieldsMaps as $key => $keyML) {
                $val = array_value($item, $keyML);
                if (!is_null($val))
                    $item[$key] = $val;
                unset($item[$keyML]);
            }
        }
        return $item;
    }

    /**
     * 取得多条数据
     * 多语言下还原字段的值
     * @param array $options
     * @return array
     */
    public function upAll() {
        $this->upLangFieldHook();
        $items = parent::upAll();
        if (!empty($this->lang) && !empty($items) && is_array($items)) {
            foreach ($items as &$item) {
                $this->toJson($item);  // sql下需要转换成JSON
                foreach ($this->fieldsMaps as $key => $keyML) {
                    $val = array_value($item, $keyML);
                    if (!is_null($val))
                        $item[$key] = $val;
                    unset($item[$keyML]);
                }
            }
        }
        return $items;
    }

    /**
     * 取得多条数据
     * 多语言下还原字段的值
     * @param array $options
     * @return array
     */
    public function upsert() {
        $items = parent::upsert();
        if (!empty($this->lang) && !empty($items) && is_array($items)) {
            foreach ($items as &$item) {
                $this->toJson($item);  // sql下需要转换成JSON
                foreach ($this->fieldsMaps as $key => $keyML) {
                    $val = array_value($item, $keyML);
                    if (!is_null($val))
                        $item[$key] = $val;
                    unset($item[$keyML]);
                }
            }
        }
        return $items;
    }

    /**
     * 设置字段数据
     * 多语言hook
     * @param array $data
     * @return $this
     */
    public function setData($data) {
        if (!empty($this->lang)) {
            array_column_rekey($data, $this->fieldsMaps);
        }
        return parent::setData($data);
    }

    /**
     * 添加数据项,不转换,不检查字段
     * 支持key,value
     * hook 多语言字段
     * @param $data
     */
    public function addData($data) {
        if (!empty($this->lang)) {
            array_column_rekey($data, $this->fieldsMaps);
        }
        return parent::addData($data);
    }

    /**
     * 减去数据项
     * 键值数组减去同key同值得键，普通数组减去数组中的键
     * @param array $data
     */
    public function subData($data) {
        if (!empty($this->lang)) {
            // 不带key减去数据中的某些键
            if (!isset($data[0])) {
                array_column_rekey($data, $this->fieldsMaps);
            } else {
                $data = array_flip($data);
                $data = array_keys(array_rekey($data, $this->fieldsMaps));
            }
        }
        return parent::subData($data);
    }

}
