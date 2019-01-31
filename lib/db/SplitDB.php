<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/9/8
 * Time: 10:31
 *
 * 数据库分库分表
 * 由模型使用
 * 
 * 请在模型的配置中配置splitdb项
 * 'v\Model' => [
 *       'db' => [], // 默认数据库配置
 *       // 分库分别配置，通过分表关键字进行分库分别
 *       'splitdb' => [
 *           'history' => [
 *               'table' => 'table_history', // 表名，如果未定义，则自动为表名 + 关键字
 *               'host' => '192.168.1.4', // 数据库地址，如果不分库只分表则不用定义
 *           ],
 *           ...
 *       ]
 *   ]
 * 
 */

namespace v\db;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

trait SplitDB {

    /**
     * 分库的model实例
     * @var array
     */
    protected static $splits = [];

    /**
     * 获得分表模型实例
     * @param string $key
     * @return v\Model
     */
    public function split($key = 'history') {
        $skey = md5(get_called_class() . "_$key");
        if (empty(self::$splits[$skey])) {
            $conf = $this->conf("splitdb.$key", []);
            // 未定义table则生成table
            if (!empty($conf['table'])) {
                $table = $conf['table'];
                unset($conf['table']);
            } else {
                $table = "{$this->table}_{$key}";
            }
            $model = new static();
            $model->table($table)->db($conf);
            self::$splits[$skey] = $model;
        }
        return self::$splits[$skey];
    }

    /**
     * 移动记录
     * 默认按3个月前移动记录
     * 模型中应该按不同的分库分别方式覆盖该方法
     */
    public function move() {
        $query = ['itime' => ['$lte' => time() - 86400 * 90]];
        // 1.3个月前完成的数据放入历史表中
        $cursor = $this->db()->where($query)->query();
        $db = $this->split()->db();
        while ($item = $cursor->next()) {
            $db->data($item)->upsert();  // 可能插入后未从表中删除，所以使用upsert
        }
        // 2.接着从现有表删除数据
        $this->db()->where($query)->remove();
    }

}
