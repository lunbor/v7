<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/10/8
 * Time: 10:31
 * 
 * 回收前端缓存计划任务
 * 
 */

namespace v\ext;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

trait CrontabRecyclePage {
    
    /**
     * 执行间隔时间
     * 由使用的类定义，可以每分钟执行一次
     * @var string
     */
    //protected $interval = 60 * 1000;

    /**
     * 要回收缓存的模块
     * 由要使用的类定义
     * @var array
     */
    //protected $recycleModule = [];

    /**
     * 开始回收缓存
     */
    public function start() {
        if (empty($this->recycleModule)) {
            v\PCache::recycle();
        } else {
            foreach ($this->recycleModule as $module) {
                v\PCache::module($module)->recycle();
            }
        }
    }

}
