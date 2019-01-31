<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/9/6
 * Time: 12:24
 *
 * url数据签名服务服务
 *
 */

namespace v\kit;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

class UrlSign extends v\ServiceFactory {

    /**
     * 服务提供对象名
     * 必须子类定义
     * @var string
     */
    protected static $objname = 'v\kit\UrlSignService';

    /**
     * 服务提供对象
     * 必须子类定义
     * @var object
     */
    protected static $object;

}

class UrlSignService extends v\Service {

    /**
     * 配置
     * @var array
     */
    protected static $configs = [
        'name' => 's', // 签名变量的名称
        'secretKey' => 'knyon-v7', // 签名密钥
        'timeoutSec' => 180  // 检测时间范围，秒
    ];

    /**
     * 密钥
     * @var string
     */
    protected $secretKey = null;

    /**
     * 超时时间，秒
     * @var int
     */
    protected $timeoutSec = 180;

    /**
     * 初始化
     */
    public function __construct() {
        $this->secretKey = $this->conf('secretKey');
        $this->timeoutSec = $this->conf('timeoutSec');
    }

    /**
     * 设置KEY
     * @param string $key
     * @return self
     */
    public function setKey($key) {
        $this->secretKey = $key;
        return $this;
    }

    /**
     * 设置TIMEOUT
     * @param intval $sec
     * @return \v\ext\UrlSignSvc
     */
    public function setTimeout($sec) {
        $this->timeoutSec = $sec;
        return $this;
    }

    /**
     * 生成签名密钥
     * @param string $url
     * @return string
     */
    public function token($url, $data = [], $time = null) {
        if (empty($time))
            $time = time();
        $data = empty($data) ? $url : $url . (strpos($url, '?') ? '&' : '?') . (is_array($data) ? http_build_query($data) : $data);
        // 签名后面带时间
        $token = substr(md5("{$this->secretKey}$data{$time}"), 8, 16) . $time;
        return $token;
    }

    /**
     * 生成url签名
     * @param string $url
     * @return string
     */
    public function sign($url = null, $data = []) {
        if (is_array($url))
            swap($url, $data);
        if (is_null($url))
            $url = v\Req::furl();
        $sign = $this->token($url, $data);
        $url .= (strpos($url, '?') ? '&' : '?') . "s=$sign";
        return $url;
    }

    /**
     * 检查签名
     * @param string $data
     * @param string $sign 签名
     * @return boolean
     */
    public function check($url = null, $data = []) {
        if (is_array($url))
            swap($url, $data);
        if (is_null($url)) {
            $url = v\Req::furl();
        }

        // 取签名
        $qstr = $this->conf('name');
        $sign = v\App::param($qstr);  // 从参数中取得签名
        if (empty($sign)) {
            if (is_array($data) && !empty($data[$qstr])) {
                $sign = $data[$qstr];  // 从数据中取签名
            } else {  // 从url中取签名
                preg_match("/[^\w]$qstr=(\w+)/", $url, $matches);
                if (!empty($matches)) {
                    $sign = $matches[1];
                }
            }
        }

        if (empty($sign)) {
            v\Err::add('sign token is null');
        } else {
            $time = intval(substr($sign, 16));
            if ($time < NOW_TIME - $this->timeoutSec) {
                v\Err::add('sign token timeout');
            } else {
                if (is_array($data) && !empty($data)) {
                    unset($data[$qstr]);
                }
                $url = trim(strtr($url, ["$qstr=$sign" => '']), '?&');
                $url = strtr($url, ['?&' => '?', '&&' => '&']);
                if ($this->token($url, $data, $time) === $sign) {
                    return true;
                }
                v\Err::add('sign token error');
            }
        }
        return false;
    }

}
