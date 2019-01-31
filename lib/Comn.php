<?php

/*
 * v framework
 *
 * v框架组件对象
 * 所以可以实例化的对象都需从此类继承
 * @copyright daojon.com
 * @author daojon <daojon@live.com>
 * @version SVN: $Id: Com.php 14035 2016-06-08 05:36:27Z wangyong $
 */

namespace v;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

/*
 * v framework
 *
 * 应用服务
 * 应用中完成某一工作的功能的服务，如路由服务、请求处理服务、数据响应服务、视图处理服务、数据处理服务、缓存服务等
 * 框架与应用中很多功能都可以拆分成服务，服务是框架的核心
 * 服务在大多数情况是单例的，但也可以实例化多个服务对象
 * 服务不具有实际生产的能力，只是完成该功能的抽象接口，要运作服务需要和服务实现程序一起完成
 * 服务相当于公司，服务实现程序相当于公司员工，服务规定怎样做事，而员工可能有多个，是实际做事的人
 *
 * 自动产生Service服务实现程序类的实例工厂
 * 需要子类定义静态方法object与objname
 * @copyright daojon.com
 * @author daojon <daojon@live.com>
 * @version SVN: $Id: Com.php 14035 2016-06-08 05:36:27Z wangyong $
 */

abstract class ServiceFactory {
    /**
     * 服务提供对象名
     * 必须子类定义
     * @var string
     */
    //protected static $objname;

    /**
     * 服务提供对象
     * 必须子类定义
     * @var object
     */
    //protected static $object;

    /**
     * 服务对象集
     * @var array
     */
    protected static $objects = [];

    /**
     * app上下文变化记录
     * @var array
     */
    protected static $appchs = [];

    /**
     * 是否必须单例模式
     * @var boolean
     */
    protected static $single = false;

    /**
     * 获取单例对象
     * 如果APP上下文发生变化会重建APP
     * @param array $conf 配置，传入了配置则代表需要新建一个实例对象
     * @param boolean $asDefault 是否作为默认服务
     * @return object
     * @throws ClassException
     */
    public static function object($conf = [], $asDefault = false) {
        $className = get_called_class();
        // 默认服务对象，单例模式对象
        if (!empty(static::$object) && (empty($conf) && !empty(self::$appchs[$className]) || !empty(static::$single))) {
            return static::$object;
        }
        // 服务是否需要变化，为空时需要重新设置默认对象
        if (empty(self::$appchs[$className])) {
            self::$appchs[$className] = 1;
            static::$object = null;
        }
        // 是否作为默认服务
        $asDefault = empty(static::$object) && (empty($conf) || static::$single) || $asDefault;

        // 根据配置文件初始化不同的服务对象
        array_extend($conf, v\App::conf($className, []), false); // 以参数配置为优先
        array_extend($conf, ['module' => v\App::module()]);  // 配置文件中加入当前模块，重新初始化
        $key = md5($className . json_encode($conf));
        if (empty(self::$objects[$key])) {
            // 优先级 参数 > 配置 > 类定义 > 默认
            $className = array_value($conf, 'service', static::$objname);
            if (empty($className)) {
                throw new ClassException('Service class is null');
            } else {
                $className = v\App::className($className);
                if (!class_exists($className)) {
                    throw new ClassException("Service class $className not exists");
                }
                $className::setConf($conf);  // 初始化前先设置配置
                self::$objects[$key] = new $className();
            }
        }

        // 设置为默认工作对象
        if ($asDefault)
            static::$object = self::$objects[$key];

        return self::$objects[$key];
    }

    /**
     * 魔术调用静态方法
     * 该魔术方法可使服务类静态调用实现类方法
     *
     * @param string $name 方法名
     * @param array $args 方法参数
     * @return mixed
     * @throws MethodException
     */
    public static function __callStatic($name, $args) {
        $obj = static::object();
        if (is_callable([$obj, $name])) {
            $rs = call_user_func_array([$obj, $name], $args);
            return $rs;
        } else {
            throw new MethodException("Method $name not exists in object");
        }
    }

}

/**
 * Class Service
 * 服务的实现程序，是实际干活的类
 * 所服务类应该由此继承
 * 该类的配置configs会向上继承，系统初始化后配置不可改
 * @package v
 */
abstract class Service {

    /**
     * 配置
     * 注意该配置会自动继承父类的配置
     * 请所有程序的配置使用该静态属性进行配置
     * @var array
     */
    protected static $configs = [];

    /**
     * 初始化时使用的配置
     * @var array
     */
    protected static $myconfs = [];

    /**
     * 事件
     * @var array
     */
    private $events = [];

    /**
     * 初始化类之前配置参数
     * @param $conf
     */
    public static function setConf($conf) {
        $className = get_called_class();
        self::$myconfs[$className] = $conf;
    }

    /**
     * 调用动态添加的匿名方法与补丁方法
     *
     * @param string $name
     * @param array $args
     * @return mixed
     * @throws MethodException
     */
    public function __call($name, $args) {
        if (isset($this->$name) && is_callable($this->$name)) {
            return call_user_func_array($this->$name, $args);
        }
        throw new MethodException("Method $name not exists in object");
    }

    /**
     * 获取配置
     * 每个类定义静态属性的配置，会自动从父类继承已有属性
     * 配置优先级 程序调用 > 子类配置 > 父类配置 > 子类程序定义 > 父类程序定义
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function config($key = null, $default = null) {
        // 无配置则从类中获取
        if (!isset($this->_configs)) {
            // 配置优先级 程序传递参数 > 配置优先 > 类定义
            $className = get_class($this);
            $this->_configs = array_value(self::$myconfs, $className, []);
            // 类配置继承，静态属性只做一次处理
            if (!isset(static::$configs['__extended__'])) {
                $conf = [];
                while ($className && $className != 'v\Service') {
                    // 定义的config，子类优先
                    array_extend(static::$configs, $className::$configs, false);
                    // 配置的config，子类优先
                    array_extend($conf, v\App::conf($className, []), false);
                    // 父类
                    $className = get_parent_class($className);
                }
                array_extend(static::$configs, $conf, true);  // 优先级配置 > 程序定义
                static::$configs['__extended__'] = 1;
            }
            array_extend($this->_configs, static::$configs, false); // 调用程序配置最高优先级
        }
        if (is_null($key))
            return $this->_configs;
        if (is_array($key)) {
            // 重新定义配置
            array_extend($this->_configs, $key);
            return $this;
        }
        return array_value($this->_configs, $key, $default);
    }

    /**
     * 获取配置，config别名
     * 每个类定义静态属性的配置，会自动从父类继承已有属性
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function conf($key = null, $default = null) {
        return $this->config($key, $default);
    }

    /**
     * 对象属性与方法扩展
     * @param $class string
     */
    public function extend($class) {
        if (is_string($class)) {
            $rc = new \ReflectionClass($class);
            $class = $rc->newInstanceArgs();
        } else {
            $rc = new ReflectionObject($class);
        }
        // 属性
        foreach ($rc->getProperties() as $prop) {
            $prop->setAccessible(true);
            $name = $prop->name;
            $value = $prop->getValue($class);
            // 属性如果对象与扩展源对象都是数组，则会合并
            if (isset($this->$name) && is_array($this->$name) && is_array($value)) {
                array_extend($this->$name, $value);
            } else {
                $this->$name = $value;
            }
        }
        // 方法
        foreach ($rc->getMethods() as $mth) {
            if (substr($mth->name, 0, 2) != '__' && !$mth->isAbstract() && $mth->name !== 'extend') {
                $this->{$mth->name} = $mth->getClosure($class)->bindTo($this);
            }
        }
    }

    /**
     * 添加钩子
     * 钩子方法没有任何返回值，如果要打断程序执行，请抛出异常
     *
     * @param string $type 事件类型
     * @param function $fun 处理函数
     * @param boolean $once 是否只执行一次
     * @throws MethodException
     */
    public function addHook($type, $fun, $once = false) {
        if (is_string($fun) && is_callable([$this, $fun]) || is_callable($fun)) {
            isset($this->events[$type]) or $this->events[$type] = [];
            if (!in_array([$fun, $once], $this->events[$type])) {
                $this->events[$type][] = [$fun, $once];
            }
        } else {
            throw new MethodException('Hook is not callable');
        }
    }

    /**
     * 触发钩子
     *
     * @param string $type 事件类型
     * @param array $params 参数，必须是数组
     */
    public function triHook($type, $params = []) {
        if (!empty($this->events[$type])) {
            foreach ($this->events[$type] as $k => $fun) {
                if (is_string($fun[0]))  // 函数为字符则为当前类方法
                    $fun[0] = [$this, $fun[0]];
                if ($fun[1])  // 只执行一次
                    unset($this->events[$type][$k]);
                $fun[0]($params);
            }
        }
    }

}

/**
 * 框架异常
 */
class Exception extends \Exception {
    
}

/**
 * 文件异常
 * 文件不存在时抛出
 */
class FileException extends Exception {
    
}

/**
 * 类异常
 * 类不存在或类继承错误时抛出
 */
class ClassException extends Exception {
    
}

/**
 * 方法异常
 * 方法不存在时抛出
 */
class MethodException extends Exception {
    
}

/**
 * 属性异常
 * 属性错误时抛出
 */
class PropertyException extends Exception {
    
}

/**
 * 参数异常
 * 函数参数不对时抛出
 */
class ArgumentsException extends Exception {
    
}

?>