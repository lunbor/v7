<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/9/8
 * Time: 10:31
 *
 * 数据查询
 * where sort limit skip field options
 */

namespace v\ext;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

trait QueryData {

    /**
     * where查询条件
     * @var array
     */
    protected $query = null;

    /**
     * 排序
     * @var array
     */
    protected $sort = null;

    /**
     * 查询字段
     * @var array
     */
    protected $field = null;

    /**
     * 取数限制条数
     * @var init
     */
    protected $limit = null;

    /**
     * 跳过多少条
     * @var init
     */
    protected $skip = null;

    /**
     * gorup字段
     * @var array
     */
    protected $group = null;

    /**
     * 索引提示
     * @var string
     */
    protected $hint = null;

    /**
     * 其他设置参数传递
     * @var array
     */
    protected $options = [];

    /**
     * 属性数据值
     * @var array 
     */
    protected $propvals = [];

    /**
     * 查询条件
     * @param array $query
     * @return $this
     */
    public function where($query = null) {
        $this->query = $query;
        return $this;
    }

    /**
     * 清除options参数数据
     * @return $this
     */
    public function reset() {
        // 所有查询条件被重置
        $this->sort = null;
        $this->field = null;
        $this->limit = null;
        $this->skip = null;
        $this->hint = null;
        $this->group = null;
        $this->propvals = [];
        $this->options = [];
        return $this;
    }

    /**
     * find取得的字段
     * @param array $fields
     * @return $this
     */
    public function field($fields) {
        if (is_null($fields))
            return $this;
        if (is_string($fields))
            $fields = arrayval($fields);
        if (isset($fields[0])) {
            $fields = array_fill_keys($fields, 1);
        }
        $this->field = $fields;
        $this->propvals['field'] = $fields;
        return $this;
    }

    /**
     * group字段
     * @param array $fields
     * @return $this
     */
    public function group($fields) {
        if (!empty($fields)) {
            if (is_string($fields)) {
                $fields = arrayval($fields);
            }
            if (isset($fields[0])) {
                $fields = array_fill_keys($fields, 1);
            }
            $this->group = $fields;
            $this->propvals['group'] = $fields;
        }
        return $this;
    }

    /**
     * 排序
     * @param array $sorts
     * @return $this
     */
    public function sort($sorts) {
        $this->sort = $sorts;
        $this->propvals['sort'] = $sorts;
        return $this;
    }

    /**
     * 数据限制条数
     * @param int $num
     * @return $this
     */
    public function limit($num) {
        $this->limit = $num;
        $this->propvals['limit'] = $num;
        return $this;
    }

    /**
     * 跳过数据条数
     * @param int $num
     * @return $this
     */
    public function skip($num) {
        $this->skip = $num;
        $this->propvals['skip'] = $num;
        return $this;
    }

    /**
     * 
     * @param string $indexName 索引名称
     * @return $this
     */
    public function hint($indexName) {
        $this->hint = $indexName;
        $this->propvals['hint'] = $indexName;
        return $this;
    }

    /**
     * options参数设置，配置limit field sort skip hint等
     * options不会重新初始化，要重新初始化请使用reset方法
     * @param array|string $options 数组为设置参数，字符串为取得参数
     * @param mixed $default 取参数时的默认数据
     * @return $this | array
     */
    public function options($options = null, $default = null) {
        if (is_null($options)) {
            // 取得options参数
            $options = $this->propvals;
            if (!empty($options['options'])) {
                $props = array_delete($options, 'options');
                $options = array_merge($options, $props);
            }
            return $options;
        } elseif (is_string($options)) {
            // 取得某一个options参数
            return array_value($this->propvals, $options, $default);
        }
        foreach ($options as $key => $value) {
            switch ($key) {
                case 'field':
                    $this->field($value);
                    break;
                case 'sort':
                    $this->sort($value);
                    break;
                case 'limit':
                    $this->limit($value);
                    break;
                case 'skip':
                    $this->skip($value);
                    break;
                case 'hint':
                    $this->hint($value);
                    break;
                case 'group':
                    $this->group($value);
                    break;
                default:
                    $this->options[$key] = $value;
            }
        }
        $this->propvals['options'] = $this->options;
        return $this;
    }

}
