<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/9/19
 * Time: 12:21
 *
 * redis cache处理
 *
 */

namespace v\cache;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));
use v;

class Redis extends v\CacheService {
    
    /**
     * 缓存配置
     * @var string
     */
    protected static $configs = [
        // redis配置
        'redis' => []
    ];

    /**
     * 
     * @var RedisSvc
     */
    protected $redis = null;

    /**
     * 实例化redis对象
     */
    public function __construct() {
        //parent::__construct();
        $conf = $this->conf('redis', []);
        $this->redis = v\Redis::object($conf);
    }

    /**
     * @see v\Cache::get
     */
    public function get($key, $fun = null, $expire = null) {
        $key1 = "cache_$key";
        $value = $this->redis->get($key1);
        if ($this->redis->get("__array_$key1")) {
            $value = json_decode($value, true);
        }
        if (empty($value) && ($value !== 0) && !is_null($fun)) {
            if (is_int($fun))
                swap($fun, $expire);
            $value = call_function($fun);
            $this->set($key, $value, $expire);
        }
        return $value;
    }

    /**
     * @see v\Cache::set
     */
    public function set($key, $data, $expire = null) {
        $key = "cache_$key";
        if (is_array($data)) {  // 数组序列化
            $data = json_encode($data);
            $this->redis->set("__array_$key", 1);
        } else {
            $this->redis->del("__array_$key");
        }
        $this->redis->set($key, $data);
        if (!empty($expire)) {
            $this->redis->expire($key, $expire);
        }
        return $this;
    }

    /**
     * @see v\Cache::del
     */
    public function del($key) {
        $key = "cache_$key";
        $this->redis->del($key);
        $this->redis->del("__array_$key");
        return $this;
    }

    /**
     * @see v\Cache::mem
     */
    public function mem($sets) {
        $sets = "cache_$sets";
        return $this->redis->smembers($sets);
    }

    /**
     * @see v\Cache::mem
     */
    public function add($sets, $key) {
        $sets = "cache_$sets";
        $this->redis->sadd($sets, $key);
        return $this;
    }

    /**
     * @see v\Cache::rem
     */
    public function rem($sets, $key) {
        $sets = "cache_$sets";
        $this->redis->srem($sets, $key);
        return $this;
    }

}