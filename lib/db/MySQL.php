<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/9/19
 * Time: 12:21
 *
 * mysql 数据库驱动
 * https://dev.mysql.com/doc/refman/8.0/en/
 * http://php.net/manual/zh/book.pdo.php
 *
 */

namespace v\db;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

class MySQL extends PostgreSQL {

    /**
     * 驱动配置
     * @var array
     */
    protected static $configs = [
        'prefix' => 'mysql',
        'port'   => '3306'
    ];

    /**
     * 取得插入字段的值
     * @param string $field
     * @return string
     */
    protected function parseUpsertValue($field) {
        return "EXCLUDED.$field";
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
        if (!empty($options['multi']) && (empty($this->query) || empty($this->query['_id']))) {
            $sql .= " LIMIT 1";
        }
        // upsert解析
        if (!empty($options['upsert'])) {
            $sql .= ' ON DUPLICATE KEY DO UPDATE SET ' . $this->parseUpserts($this->data);
        }
        // 数据返回
        if (!empty($options['return'])) {
            throw new SQLException('Mysql not supported update ...returning');
        }
        return $sql;
    }

    /**
     * 
     * @param string $value
     * @return string
     */
    public function escapeString($value) {
        return mysqli::real_escape_string($value);
    }

}
