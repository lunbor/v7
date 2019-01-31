<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/8/31
 * Time: 12:21
 * 
 * 控制器对象
 * 应用所有控制器由此继承
 * 
 * 控制器中包含了CSRF攻击检查，涉及到登陆操作的都应该进行CSRF检查
 * CSRF主要防范非法使用系统接口
 * html视图中应该使用视图的htmlCSRF取得csrf的表单，APP应该使用单独的控制器取得csrf的token
 * 
 */

namespace v;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

abstract class Controller extends v\Service {

    /**
     * 控制器配置
     * @var array
     */
    protected static $configs = [
        'csrf_name' => 'csrf_token'
    ];

    /**
     * 控制器频繁度检测
     * @param int $expire 秒
     * @return boolean
     */
    public function frequent($expire = 1) {
        $key = "often_" . get_class($this);
        $time = v\Session::get($key);
        if (empty($time) || NOW_TIME - $expire >= $time) {
            v\Session::set($key, NOW_TIME);
            return true;
        }
        v\Err::add('Frequent submit');
        return false;
    }

    /**
     * 生成csrf token
     * 同一个session下csrf token相同
     * 防重复提交不应该使用该方法，请使用frequent检查提交频度，或者由客户端处理
     * @return string
     */
    public function tokenCSRF() {
        return v\Session::tokenCSRF();
    }

    /**
     * 校验csrf token
     * @return boolean
     */
    public function checkCSRF() {
        $field = $this->conf('csrf_name');
        $value = v\App::param($field, null);
        if (!v\Session::checkCSRF($value)) {
            v\Err::add('CSRF token error');
            return false;
        }
        return true;
    }

    /**
     * 响应数据
     * 该处响应数据格式同错误v\Err::resp
     * 错误数据请使用v\Err::resp，该方法用于响应控制器的正确的数据
     * @param $data
     */
    public function resp($data) {
        if (!isset($data['errno'])) {
            if (!isset($data['data'])) {
                $data = ['errno' => [], 'data' => $data];
            } else {
                $data['errno'] = [];
            }
        }
        return v\Res::resp(200, $data);
    }

    /**
     * 载入视图
     * @param string $file
     * @param array $data
     * @return string
     */
    public function view($file, $data = null) {
        return v\View::controller($this)->resp($file, $data);
    }

}
