<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2017/5/4
 * Time: 12:24
 *
 * 数据库事务守护
 * 实现mongodb的数据库事务操作
 * 注意该操作不能回滚数据，只能保证数据操作每一步的完成，所以程序的逻辑实现不要依靠此事务去保证
 * 原理：
 * 对原始数据存入redis，并生成一个ID
 * 对每一步数据库操作的同时写入该ID
 * 操作完毕以后删除对应的数据表中的ID，并且移除数据
 * 计划检查未完成的事务数据，并重新执行处理逻辑
 *
 * 注意涉及到事务的表，请添加 guards 字段并进行索引
 * 事务处理请关闭读写分离
 *
 */

namespace v\kit;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

class Guard extends v\ServiceFactory {

    /**
     * 服务提供对象名
     * 必须子类定义
     * @var string
     */
    protected static $objname = 'v\kit\GuardService';

    /**
     * 服务提供对象
     * 必须子类定义
     * @var object
     */
    protected static $object;

}

class GuardService extends v\Service {

    /**
     * 数据守护hash表
     * @var string
     */
    protected $hashKey = '__v7dataguardhash';

    /**
     * 数据守护有序表
     * @var string
     */
    protected $setKey = '__v7dataguardset';

    /**
     * 事务对应到的模型
     * guardID => [model1, model2]
     * @var array
     */
    protected $models = [];

    /**
     * 建立一个新的守护
     * @param array $data 要处理的数据
     * @param array $func 执行的函数[modelName, functionName]
     * @param string $id 守护ID，如果要确定唯一性，防止多次调用则由外部传入守护ID
     * @return string  守护ID
     */
    public function start($data, $func, $id = null) {
        v\DB::splitRW(false);  // 关闭读写分离
        if (empty($id))
            $id = uniqid12();
        $vals = ['func' => $func, 'data' => $data];
        $rs = v\Redis::hSetnx($this->hashKey, $id, json_encode($vals));  // 记录数据
        if ($rs) {
            v\Redis::zAdd($this->setKey, time(), $id);  // 按时间排序队列
        }
        return $id;
    }

    /**
     * 开始执行一个事务
     * 同一个事务只允许单线程执行，180秒内必须完成
     * @param string $id 守护ID
     */
    public function doing($id) {
        $rs = 0;
        if (v\Redis::lock("guard_doing_{$id}", 180)) {
            $guard = v\Redis::hGet($this->hashKey, $id);
            if (!empty($guard)) {
                $guard = json_decode($guard, true);
                $func = $guard['func'];
                $func[0] = v\App::model($func[0]);
                $rs = call_user_func($func, $guard['data'], $id);
            } else {
                v\Redis::zDelete($this->setKey, $id);
            }
            v\Redis::unlock("guard_doing_{$id}");
        }
        return $rs;
    }

    /**
     * 完成一个事务
     * @param string $id 守护ID
     * @param array $models 守护事务所影响的模型，如果使用的是事务中更新与添加方法可以不传入该参数
     */
    public function finish($id, $models = []) {
        $rs = v\Redis::hDel($this->hashKey, $id);
        if ($rs) {
            v\Redis::zDelete($this->setKey, $id);
            // 合并模型并去重
            if (!empty($this->models[$id])) {
                foreach ($this->models[$id] as $model) {
                    if (!in_array($model, $models, true))
                        $models[] = $model;
                }
                unset($this->models[$id]);
            }
            foreach ($models as $model) {
                $model->data(['$pull' => ['guards' => $id]])->where(['guards' => $id])->upOne();
            }
        }
    }

    /**
     * 添加事务关联到的模型
     * @param string $guardID 事务ID
     * @param v\Model $model 事务模型
     */
    public function addModel($guardID, $model) {
        if (empty($this->models[$guardID])) {
            $this->models[$guardID] = [$model];
        } else {
            $this->models[$guardID][] = $model;
        }
    }

    /**
     * 是否能进行该步事务
     * @param string $id 守护ID
     * @param v\Model $model 模型
     * @param array 附加条件
     * @return boolean
     */
    public function stepCan($id, $model, $where = []) {
        $where = array_merge(['guards' => $id], $where);
        $count = $model->where($where)->count();
        return empty($count);
    }

    /**
     * 添加一步数据
     * @param string $id 守护ID
     * @param v\Model $model 模型
     * @param array $data 添加数据
     * @param array $filter 附加条件
     * @return int
     */
    public function stepAdd($id, $model, $data, $filter = []) {
        $this->addModel($id, $model);
        if ($this->stepCan($id, $model, $filter)) {
            $data['guards'] = [$id];
            return $model->data($data)->addOne();
        }
        return 1;
    }

    /**
     * 更新一步数据
     * @param string $id 守护ID
     * @param v\Model $model 模型
     * @param array $data 更新数据
     * @param array $where 更新条件
     * @param array $filter 附加条件
     * @return int
     */
    public function stepUp($id, $model, $data, $where, $filter = []) {
        $this->addModel($id, $model);
        if ($this->stepCan($id, $model, $filter)) {
            $data['$push'] = ['guards' => $id];
            return $model->data($data)->where($where)->upOne();
        }
        return 1;
    }

    /**
     * 检查并修复一个未完成的守护事务
     * 修复3分钟后的事务
     */
    public function fixOne() {
        $now = time();
        $items = v\Redis::zRangeByScore($this->setKey, 0, $now - 180);
        if (!empty($items)) {
            foreach ($items as $id) {
                v\Redis::zAdd($this->setKey, $now, $id);  // 按时间排序队列
                $this->doing($id);
                return true;
            }
        }
        return false;
    }

    /**
     * 取得所有事务数据
     * @return array
     */
    public function getAll() {
        return v\Redis::hGetAll($this->hashKey);
    }

}
