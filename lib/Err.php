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
 * Class Err
 * 错误消息服务
 * 应用的错误消息
 * @package v
 */
class Err extends v\ServiceFactory {

    /**
     * 服务提供对象名
     * 必须子类定义
     * @var string
     */
    protected static $objname = 'v\Error';

    /**
     * 服务提供对象
     * 必须子类定义
     * @var object
     */
    protected static $object;

}

class Error extends v\Service {

    /**
     * 错误消息
     * @var array
     */
    protected $message = [];

    /**
     * 错误编号
     * @var array
     */
    protected $errno = [];

    /**
     * 获得关键字消息
     * @return mixed
     */
    public function get($key = null) {
        if (is_null($key))
            return ['errno' => array_keys($this->errno), 'message' => $this->message];
        return array_value($this->message, $key);
    }

    /**
     * 取得错误编号
     * @return array
     */
    public function getNo() {
        return array_keys($this->errno);
    }

    /**
     * 取得错误消息
     * @return array
     */
    public function getMessage() {
        return $this->message;
    }

    /**
     * 添加错误消息
     * 定义错误编号请同http状态，400到600的编号
     * @param string $key 错误字段
     * @param mixed $message 错误消息
     * @param integer $errno 错误编号，默认未422效验未通过
     * @return $this
     */
    public function add($message, $key = '*', $errno = 422) {
        if (is_int($key)) {
            $errno = $key;
            $key = '*';
        }
        if (empty($errno)) {  // 为0的错误归入到422
            $errno = 422;
        }
        is_array($message) ? $this->message = array_merge($this->message, v\App::t($message)) : $this->message[$key] = v\App::t($message);
        $this->errno[$errno] = 1;
        return $this;
    }

    /**
     * 抛出错误结束程序
     * @param string|array $message
     * @param string $key
     * @param integer $errno
     */
    public function end($message = null, $key = '*', $errno = 422) {
        if (!empty($message))
            $this->add($message, $key, $errno);
        v\Res::end(422, $this->get());
    }

    /**
     * 是否有某错误
     * @param string | int $key 错误字段或编号
     * @return boolean
     */
    public function has($key = null) {
        return is_null($key) ? !empty($this->message) : (is_string($key) ? isset($this->message[$key]) : isset($this->errno[$key]));
    }

    /**
     * 清除错误
     */
    public function clean() {
        $this->message = [];
        $this->errno = [];
    }

    /**
     * 记录错误日志
     */
    public function log() {
        $msg = [];
        foreach ($this->message as $key => $message) {
            $msg[] = "$key, $message";
        }
        v\App::log($msg, 'error.log');
    }

    /**
     * 返回错误响应
     * @return mixed
     */
    public function resp($status = null) {
        if (is_null($status)) {
            $status = 422;
        }
        return v\App::resp($status, $this->get());
    }

}
