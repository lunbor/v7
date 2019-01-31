<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/8/31
 * Time: 12:21
 */

namespace v;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

abstract class Crontab extends v\Service {

    /**
     * 间隔时间，秒
     * 取值0.000001 --- 600
     * >=600秒，则每次调用只执行一次
     * @var int
     */
    protected $interval = 600;

    /**
     * 按时间间隔执行任务
     */
    public function run() {
        $starttime = microtime(true);
        $endtime = $starttime + 600;
        while (true) {
            $starttime += $this->interval;
            $this->start();
            if ($starttime >= $endtime)
                break;
            // sleep时间 = 下次执行时间 - 当前时间，程序执行时间不做计算，比如间隔10秒钟执行，程序执行8秒钟，则sleep 2 秒钟
            $microtime = microtime(true);
            if ($starttime > $microtime) {
                $sleeptime = $starttime - microtime(true);
                usleep($sleeptime * 1000000);
            } else {
                $starttime = $microtime;
            }
        }
    }

    /**
     * 开始执行任务
     * 子类任务继承
     * 要求执行时间不超过10分钟
     * 执行一次任务
     */
    abstract public function start();

    /**
     * 是否有该进程执行中
     * 有必须单进程允许的任务调用该方法判断
     * @return boolean
     */
    public function haveRun() {
        $name = v\Req::controller();
        $count = exec("ps -ax | grep '[index.php] $name' | wc -l");
        return $count > 1;
    }

}
