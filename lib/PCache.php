<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/9/6
 * Time: 12:24
 * 
 * 前端静态文件缓存
 * 一年以上的视为永久缓存，不会被回收
 * 
 */

namespace v;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

/**
 * Class PCache
 * 页面缓存处理类
 *
 * @package v
 */
class PCache extends v\ServiceFactory {

    /**
     * 服务提供对象名
     * 必须子类定义
     * @var string
     */
    protected static $objname = 'v\PCacheService';

    /**
     * 服务提供对象
     * 必须子类定义
     * @var object
     */
    protected static $object;

}

class PCacheService extends v\Service {

    /**
     * 配置
     * @var array
     */
    protected static $configs = [
        'expireDir' => '_expire', // 过期时间文件夹
        'minSecond' => 60 // 缓存时间超过该秒才生成静态文件
    ];

    /**
     * 缓存DIR
     * @var string
     */
    protected $cacheDir = null;

    /**
     * 初始化
     */
    public function __construct() {
        // 默认缓存为当前模块
        $this->module(APP_MODULE);
    }

    /**
     * 设置缓存的模块
     * @param string $module
     */
    public function module($module) {
        $this->cacheDir = strtr(APP_ROOT . "/$module", ['//' => '/']);
        return $this;
    }

    /**
     * 生成缓存文件
     * @param string $path 缓存文件url，从请求跟目录开始
     * @param string $value 缓存数据
     * @param int $sec 缓存时间 秒，>= 86400 * 360 永不过期
     */
    public function set($path, $value, $sec) {
        if ($sec >= $this->conf('minSecond', 60)) {
            // 写缓存文件
            $file = $this->cacheDir . "/$path";
            file_put_autodir($file, $value, LOCK_EX);
            // 写过期时间，永久缓存不写过期时间
            if ($sec < 86400 * 360) {
                $this->setExpire($path, NOW_TIME + $sec);
            }
        }
    }

    /**
     * 写过期时间
     * 子类继承时需要重写
     * @param string $path
     * @param int $exptime
     */
    protected function setExpire($path, $exptime) {
        $file = $this->cacheDir . '/' . $this->conf('expireDir') . '/' . md5($path);
        file_put_autodir($file, "{$exptime}\n$path", LOCK_EX);
    }

    /**
     * 删除缓存
     */
    public function del($path) {
        $file = $this->cacheDir . "/$path";
        @unlink($file);
    }

    /**
     * 删除缓存过去数据
     * 子类继承时需要重写
     */
    protected function delExpire($path) {
        $file = $this->cacheDir . '/' . $this->conf('expireDir') . '/' . md5($path);
        @unlink($file);
    }

    /**
     * 回收到期页面
     * 子类继承时需要重写
     */
    public function recycle() {
        $now = time();
        $expireDir = $this->cacheDir . '/' . $this->conf('expireDir');
        $dh = opendir($expireDir);
        while ($file = readdir($dh)) {
            if ($file != '.' && $file != '..') {
                $path = "{$expireDir}/$file";
                $exp = explode("\n", file_get_contents($path));
                if ($exp[0] < $now) {
                    @unlink($path);
                    $this->del($exp[1]);
                }
            }
        }
        closedir($dh);
    }

    /**
     * 清空目录
     * 子类继承时需要重写
     */
    public function clean() {
        $expireDir = $this->cacheDir . '/' . $this->conf('expireDir');
        $dh = opendir($expireDir);
        while ($file = readdir($dh)) {
            if ($file != '.' && $file != '..') {
                $path = "{$expireDir}/$file";
                $exp = explode("\n", file_get_contents($path));
                @unlink($path);
                $this->del($exp[1]);
            }
        }
        closedir($dh);
    }

}
