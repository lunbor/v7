<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/9/6
 * Time: 12:24
 */
namespace v;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));
use v;

/**
 * Class Cache
 * 缓存处理类
 * 默认情况不缓存
 *
 * v\Cache::get('cacheKey', function() {
 *     // get data statement
 *     return $value;
 * }, $expire_time);
 *
 * @package v
 */
class Cache extends v\ServiceFactory {

    /**
     * 服务提供对象名
     * 必须子类定义
     * @var string
     */
    protected static $objname = 'v\CacheService';

    /**
     * 服务提供对象
     * 必须子类定义
     * @var object
     */
    protected static $object;


}

class CacheService extends v\Service {

    /**
     * 获得某关键字缓存
     * 如果无缓存内容则，可以调用函数取得数据然后缓存
     * @param string $key
     * @param string|array $fun
     * @param int $expire 过期时间，单位/秒 null不过期
     * @return mixed
     */
    public function get($key, $fun = null, $expire = null) {
        if (is_int($fun))
            swap($fun, $expire);
        if (is_null($fun))
            return '';
        return call_function($fun);
    }

    /**
     * 设置某关健字缓存
     * @param string $key
     * @param mixed $value
     * @param int $expire 过期时间，单位/秒 null不过期
     */
    public function set($key, $value, $expire = null) {
        return $this;
    }

    /**
     * 删除某关健字缓存
     * @param string $key
     */
    public function del($key) {
        return $this;
    }

    /**
     * 添加key到集合
     * @param string $sets
     * @param string $key
     */
    public function add($sets, $key) {
        return $this;
    }

    /**
     * 从集合删除key
     * @param string $sets
     * @param string $key
     */
    public function rem($sets, $key) {
        return $this;
    }

    /**
     * 获得集合成员
     * @param string $sets
     * @return array
     */
    public function mem($sets) {
        return [];
    }

}