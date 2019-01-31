<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/9/19
 * Time: 12:21
 *
 * redis session处理
 *
 */

namespace v\session;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

class Redis extends v\SessionService implements \SessionHandlerInterface {

    /**
     * 缓存配置
     * @var string
     */
    protected static $configs = [
        // redis配置
        'redis' => [
            'dbid' => 15  // 默认放入最后一个redis数据库中
        ]
    ];

    /**
     * 
     * @var RedisService
     */
    protected $redis = null;

    /**
     * 保存session的键名
     * @var string
     */
    protected $rkey = 'session';

    /**
     * 开启session
     */
    public function start() {
        if (session_status() != PHP_SESSION_ACTIVE) {
            session_set_save_handler($this, true);
            parent::start();
            // 每次重新设置session过期时间
            if ($_SESSION['generate_time'] !== $_SESSION['last_time']) {
                $rkey = "{$this->rkey}_" . session_id();
                $this->redis->expire($rkey, session_cache_expire() * 60);
            }
        }
    }

    /**
     * 初始化，设置过期时间
     */
    public function init() {
        parent::init();
        // 每次重新设置session过期时间
        $rkey = "{$this->rkey}_" . session_id();
        $this->redis->expire($rkey, session_cache_expire() * 60);
    }

    /**
     * 链接Redis数据库
     * @param string $save_path
     * @param string $name
     * @return boolean
     */
    public function open($save_path, $name) {
        $this->rkey = $name;
        $this->redis = v\Redis::object($this->conf('redis', []));
        return true;
    }

    /**
     * 关闭数据库连接
     * redis自动处理
     * @return boolean
     */
    public function close() {
        return true;
    }

    /**
     * 读 session
     * @param string $session_id
     * @return string
     */
    public function read($session_id) {
        // php7只接收字符串返回，key不存在返回false时则返回为空串
        $value = $this->redis->get("{$this->rkey}_$session_id");
        return is_string($value) ? $value : '';
    }

    /**
     * 写 session
     * @param string $session_id
     * @param string $session_data
     */
    public function write($session_id, $session_data) {
        $this->redis->set("{$this->rkey}_$session_id", $session_data, session_cache_expire() * 60);
        return true;
    }

    /**
     * 销毁 session
     * @param string $session_id
     * @return string
     */
    public function destroy($session_id = null) {
        if (!empty($session_id)) {
            $redis = v\Redis::object($this->conf('redis', []));
            $redis->delete("{$this->rkey}_$session_id");
            return true;
        }
        parent::destroy();
    }

    /**
     * 回收过期 session
     * @param int $maxlifetime
     * @return boolean
     */
    public function gc($maxlifetime) {
        return true;
    }

    /**
     * 根据项目+ip+时间戳生成session_id
     * php原生成ID的方法在代理与cdn的情况下可能产生碰撞
     * @return string
     */
    public function create_sid() {
        return md5(APP_ROOT . v\Req::ip() . v\Redis::uniqid9('v7sessid'));
    }

}
