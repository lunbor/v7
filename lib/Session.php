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
 * Class Session
 * session处理类
 * 如果没有session的cookie且不保持session数据，则不会写入session
 * @package v
 */
class Session extends v\ServiceFactory {

    /**
     * 服务提供对象名
     * 必须子类定义
     * @var string
     */
    protected static $objname = 'v\SessionService';

    /**
     * 服务提供对象
     * 必须子类定义
     * @var object
     */
    protected static $object;

    /**
     * 必须单例模式
     * @var boolean
     */
    protected static $single = true;

}

class SessionService extends v\Service {

    /**
     * 配置
     * @var string
     */
    protected static $configs = [
        'expire' => 60, // 过期时间 分钟
        'name'   => null, // session cookie名
        'domain' => null, // 域名
        'path'   => null, // 保存路径，默认文件处理session使用
        'strict' => false, // 是否严格执行安全检查
    ];

    /**
     * 初始化，不会start session，只设置session
     */
    public function __construct() {
        // 初始设置session
        if (session_status() != PHP_SESSION_ACTIVE) {
            $conf = $this->config();
            if (!empty($conf['expire']))
                session_cache_expire($conf['expire']);
            if (!empty($conf['name']))
                session_name($conf['name']);
            if (!empty($conf['path']))
                session_save_path($conf['path']);
        }
    }

    /**
     * start session
     */
    public function start() {
        // 初始设置session
        if (session_status() != PHP_SESSION_ACTIVE) {
            $conf = $this->config();
            if (!empty($conf['domain'])) {
                $domain = $conf['domain'];
                if (is_int($domain)) {  // 整数时取当前域名
                    $domain = v\Req::domain($domain);
                    if (!empty($domain)) {
                        $domain = ".$domain";
                    }
                }
                if (ini_get('session.use_cookies')) {
                    $params = session_get_cookie_params();
                    session_set_cookie_params($params['lifetime'], $params['path'], $domain);
                }
            }
            session_start();

            // 判断session过期时间
            if (empty($_SESSION['generate_time'])) {
                // session无时间，直接初始化时间
                $this->init();
            } elseif (!empty($conf['strict'])) {
                // 判断session是否过期
                $expireTime = session_cache_expire() * 60;
                $time = time();
                if (($_SESSION['last_time'] + $expireTime < $time) || ($_SESSION['generate_time'] + 86400 < $time) || ($_SESSION['ip'] !== v\Req::ip())) {
                    // session建立时间 > 24 小时，或者最后操作时间 > 过期时间，则重新下发session_id并初始化
                    $this->anew();
                } else {
                    $_SESSION['last_time'] = $time;  // 记录最后访问时间
                }
            }
        }
    }

    /**
     * 获得关键字session
     * @return mixed
     */
    public function get($key = null, $default = null) {
        if (!$this->has())
            return $default;

        $this->start();
        if (is_null($key))
            return $_SESSION;
        return array_value($_SESSION, $key, $default);
    }

    /**
     * 设置某关健字session
     * @param string|array $key
     * @param mixed $value
     */
    public function set($key, $value = null) {
        $this->start();
        if (is_array($key)) {
            array_extend($_SESSION, $key);
        } else {
            array_setval($_SESSION, $key, $value);
        }
        return $this;
    }

    /**
     * 删除某关健字session
     * @param string $key
     */
    public function del($key) {
        if ($this->has()) {
            $this->start();
            array_setval($_SESSION, $key, null);
            unset($_SESSION[$key]);
        }
        return $this;
    }

    /**
     * session关键字是否为空
     * @param string $key
     * @return boolean
     */
    public function has($key = null) {
        $has = !!v\Req::cookie(session_name());
        if (!$has || empty($key))
            return $has;

        $this->start();
        return !empty($_SESSION[$key]);
    }

    /**
     * 重新生成session_id并初始化
     */
    public function anew() {
        $this->start();
        session_regenerate_id(true);
        $this->init();
    }

    /**
     * 初始化session
     */
    public function init() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['generate_time'] = time();  // 生成时间
            $_SESSION['last_time'] = $_SESSION['generate_time']; // 最后操作
            $_SESSION['ip'] = v\Req::ip();
            $_SESSION['ua'] = md5(v\Req::ua());
            unset($_SESSION['csrf_token']);  // csrf token 需要删除才能重新生成
            $this->tokenCSRF();
        }
    }

    /**
     * 生成csrf token
     * 同一个session下csrf token相同
     * 防重复提交不应该使用该方法，请使用frequent检查提交频度，或者由客户端处理
     * @return string
     */
    public function tokenCSRF() {
        $this->start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = md516($_SESSION['ip'] . uniqid()); // csrf校验值
        }
        // 写入header头
        v\Res::header("Csrf-Token:{$_SESSION['csrf_token']}");  // csrf输出到header中
        // 写入到cookie中
        v\Res::cookie('csrf-token', $_SESSION['csrf_token']);   // csrf下发到cookie中
        return $_SESSION['csrf_token'];
    }

    /**
     * 校验csrf token
     * @param string $value 如果没有该参数则自动读取field的值
     * @return boolean
     */
    public function checkCSRF($value = null) {
        $token = $this->get('csrf_token');
        if (!empty($token)) {
            // 参数不带csrftoken值，则从header头部取
            if (empty($value)) {
                $value = array_value($_SERVER, 'HTTP_CSRF_TOKEN');
            }
            if ($value === $token) {
                return true;
            }
        }
        return false;
    }

    /**
     * 销毁session
     */
    public function destroy() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];  // 重置会话中的所有变量
            session_destroy();  // 最后，销毁会话
            // 如果要清理的更彻底，那么同时删除会话 cookie
            // 注意：这样不但销毁了会话中的数据，还同时销毁了会话本身
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
        }
        return $this;
    }

    /**
     * 根据项目+ip+时间戳生成session_id
     * 自定义session处理才生效，在该服务中不会生效
     * php原生成ID的方法在代理与cdn的情况下可能产生碰撞
     * @return string
     */
    public function create_sid() {
        return md5(APP_ROOT . v\Req::ip() . uniqid('v7sessid', true));
    }

}
