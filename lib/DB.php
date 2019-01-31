<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/9/6
 * Time: 12:24
 *
 * 数据库服务
 *
 */

namespace v;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

class DB extends v\ServiceFactory {

    /**
     * 服务提供对象名
     * 必须子类定义
     * @var string
     */
    protected static $objname = 'v\Dbase';

    /**
     * 服务提供对象
     * 必须子类定义
     * @var object
     */
    protected static $object;

}

abstract class Dbase extends v\Service {

    use v\ext\QueryData;

    const READ_ONLY = 1;  // 只读模式
    const READ_WRITE = 0;  // 读写模式

    /**
     * 配置
     * @var array
     */

    protected static $configs = [
        'host'   => 'localhost', // 主机
        //'port' => '27017', // 端口
        'dbname' => 'test', // 数据库名
            //'username' => '', // 用户名
            //'password' => '' // 密码
    ];

    /**
     * 链接对象，静态保证相同主机配置只有一个链接
     * @var array
     */
    protected static $conns = [];

    /**
     * 数据库表对象，静态保证相同主机的相同数据表只有一个链接
     * @var array
     */
    protected static $tables = [];

    /**
     * 是否读写分离，默认情况下无读写分离
     * 读写分离为全局配置
     * @var boolean
     */
    protected static $isSplitRW = null;

    /**
     * 插入或者更新的数据
     * @var array
     */
    protected $data = null;

    /**
     * 数据表名
     * @var string
     */
    protected $table = null;

    /**
     * 数据库名
     * @var string
     */
    protected $dbname = null;

    /**
     * 最后插入的ID
     * @var string
     */
    protected $lastID = null;

    /**
     * 数据库连接
     * @var mixed
     */
    protected $conn = null;

    /**
     * 只读数据库链接
     * @var mixed
     */
    protected $connr = null;

    /**
     * 构造函数中从配置中获取读写分离模式
     */
    public function __construct() {
        // 读写分离配置
        if (is_null(self::$isSplitRW)) {
            self::$isSplitRW = boolval($this->config('splitRW'));
        }
    }

    /**
     * 创建数据库链接
     * @param string $dsn
     * @return mixed
     */
    protected function createConn($dsn) {
        $this->dbname = $this->config('dbname');
        $key = md5($dsn);
        if (empty(self::$conns[$key])) {
            self::$conns[$key] = $this->connect($dsn);
        }
        return self::$conns[$key];
    }

    /**
     * 取得数据库连接
     * @return mixed
     */
    public function conn() {
        if (empty($this->conn)) {
            $this->conn = $this->createConn($this->dsn());
        }
        return $this->conn;
    }

    /**
     * 取得只读模式下数据库连接
     * @return mixed
     */
    public function connr() {
        if (empty($this->connr)) {
            $this->connr = $this->createConn($this->dsn(self::READ_ONLY));
        }
        return $this->connr;
    }

    /**
     * 选择数据表
     * @param string $table 表名
     * @return self
     */
    public function table($table = null) {
        if (is_null($table))
            return $this->table;

        $dbname = $this->config('dbname');
        $key = md5($this->dsn() . ";db=$dbname;table=$table");
        if (empty(self::$tables[$key])) {
            // 第一次的table作为默认，如果table不同则自动克隆一个新对象
            self::$tables[$key] = is_null($this->table) ? $this : new static();
            self::$tables[$key]->table = $table;
        }
        return self::$tables[$key];
    }

    /**
     * 插入或更新的数据
     * @param array $data
     * @return array
     */
    public function data($data) {
        $this->data = $data;
        return $this;
    }

    /**
     * 取得最后插入的ID
     * @return string
     */
    public function lastID() {
        return $this->lastID;
    }

    /**
     * 设置读写分离
     * 默认为非读写分离模式
     * 在开始数据库操作之前就应该确定是否读写分离，在只有读的情况下使用读写分离，读写混合的情况下不使用读写分离。
     * 希望写入后立即读取的情况，不要读写分离模式
     * @param boolean $value  为true读写分离
     */
    public function splitRW($value) {
        self::$isSplitRW = $value;
        return $this;
    }

    /**
     * 取得数据库连接data source name
     * 不同的驱动组成方式不一样
     * @param int $rw 读写模式
     * @return string
     */
    abstract protected function dsn($rw = null);

    /**
     * 通过dsn链接到数据库
     * @param string $dsn
     * @return mixed 数据库连接实例
     */
    abstract protected function connect($dsn);

    /**
     * 查询数据，不返回查询结果，和next配合使用
     */
    abstract public function query();

    /**
     * 取得下一条数据
     * @return array
     */
    abstract public function next();

    /**
     * 取得数据
     * @return array
     */
    abstract public function find();

    /**
     * 插入数据
     * @return int 插入的数据条数
     */
    abstract public function insert();

    /**
     * 更新数据
     * @return int 更新数据的条数
     */
    abstract public function update();

    /**
     * 修改数据并返回
     * @return array
     */
    abstract public function findOneAndUpdate();

    /**
     * 插入更新，更新数据是如果没有该数据则组合条件与数据插入
     * @return int
     */
    abstract public function upsert();

    /**
     * 插入更新并返回数据，更新数据是如果没有该数据则组合条件与数据插入
     * @return array
     */
    abstract public function findOneAndUpsert();

    /**
     * 删除数据
     * @return int 删除数据的条数
     */
    abstract public function remove();

    /**
     * 删除数据并返回
     * @return array
     */
    abstract public function findOneAndRemove();

    /**
     * 删除数据表
     * @return boolean 是否成功
     */
    abstract public function drop();

    /**
     * 取得数据条数
     * @param int $limit 最多限制条数，null不限制
     * @return int
     */
    abstract public function count();

    /**
     * 创建索引
     * @param array $indexes
     */
    abstract public function indexes($indexes);
}
