<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/8/31
 * Time: 12:24
 */

namespace v;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

class App extends v\ServiceFactory {

    /**
     * 服务提供对象名
     * 必须子类定义
     * @var string
     */
    protected static $objname = 'v\Application';

    /**
     * 服务提供对象
     * 必须子类定义
     * @var object
     */
    protected static $object;

    /**
     * 当前模型名称
     * @var string
     */
    protected static $module;

    /**
     * 载入模块服务 | 取得当前模块名
     * 还原到当前默认入口模块，请保存参数为空
     * @param string $module
     * @return self
     */
    public static function module($module = null, $func = null) {
        if (is_null($module))
            return self::$module;

        // 载入模块
        self::$module = $module;
        self::$appchs = [];

        // 如果带有方法体，执行完后自动恢复上下文关系
        if (!empty($func)) {
            $func();
            self::reset();
        }
    }

    /**
     * 恢复到当前模块
     * @return App
     */
    public static function reset() {
        return self::module(APP_MODULE);
    }

    /**
     * 取得当前应用的配置
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function conf($key, $default = null) {
        // 载入php配置文件
        if (strpos($key, '.php')) {
            $file = self::absolutePath("config/$key", true);
            return require $file;
        }
        // 根模块
        if (empty(self::$module)) {
            return array_value($_ENV['app'], $key, $default);
        }
        // 其他模块
        $module = self::$module;
        // 载入模块配置
        if (!isset($_ENV[$module])) {
            $config = [];
            $path = $module;
            while (!empty($path)) {
                // 是模块，载入配置文件
                $file = APP_ROOT . '/' . $path . APP_SC_DIR . '/config/global.php';
                if (is_file($file)) {
                    array_extend($config, require $file, false);
                }
                $path = trim(strval(dirname($path)), './\\');
            }
            $_ENV[$module] = $config;
        }
        $value = array_value($_ENV[$module], $key);
        if (is_null($value)) {
            $value = array_value($_ENV['app'], $key, $default);
        }
        return $value;
    }

    /**
     * 从当前模块向上搜索文件所在的模块
     * 如果在所有模块里没找到该文件，则为false
     * @param string $file
     * @return string
     */
    public static function findModule($file) {
        $file = strtr(ltrim($file, '/'), ['\\' => '/']);
        $module = self::$module . '/v';
        do {
            $module = trim(strval(dirname($module)), './\\');
            $path = strtr(APP_ROOT . "/$module" . APP_SC_DIR . "/$file", ['//' => '/']);
            if (is_file($path)) {
                if (strpos($file, '.php') && !strpos($path, '/view/'))  // php格式文件自动载入，视图文件不载入
                    require_once $path;
                return $module;
            }
        } while (!empty($module));
        return false;
    }

    /**
     * 取得classname
     * 注意命名空间必须vApp开始
     * @param string $name 类名 如controller\classname，需要带上一级命名空间
     * @return string
     */
    public static function className($name, $bubble = true) {
        // 根据文件位置补全命名空间
        if (substr($name, 0, 5) !== 'vApp\\' && substr($name, 0, 2) !== 'v\\') {
            $name = strtr($name, ['\\' => '/']);
            $module = $bubble ? strval(self::findModule("$name.php")) : self::$module;
            // 命名空间必须vApp开始
            $name = strtr("vApp\\$module\\$name", ['\\\\' => '\\', '/' => '\\']);
        }
        return $name;
    }

    /**
     * 生成文件绝对路径与模块
     * @param string $file
     * @param string|boolean 是否向上查找|模块名
     * @return array 文件名与模块，模块未false代表未找到，使用框架文件
     */
    public static function absolutePaths($file, $bubble = false) {
        $file = ltrim($file, '/');
        $module = is_string($bubble) ? $bubble : ($bubble ? v\App::findModule($file) : self::$module);
        if ($module === false) {
            $filename = V_ROOT . "/$file";
            if (!is_file($filename)) {
                $filename = APP_ROOT . "/$file";
            }
        } else {
            $filename = strtr(APP_ROOT . "/$module" . APP_SC_DIR . "/$file", ['//' => '/', '///' => '/']);
        }
        return [$filename, $module];
    }

    /**
     * 生成文件绝对路径
     * @param string $file
     * @param string|boolean 是否向上查找|模块名
     * @return string
     */
    public static function absolutePath($file, $bubble = false) {
        list($filename, $module) = self::absolutePaths($file, $bubble);
        return $filename;
    }

}

class Application extends v\Service {

    /**
     * 应用配置
     * @var array
     */
    protected static $configs = [
        'debug' => false, // 是否debug
        'logDir' => null // 日志文件夹，如果为空当前模块logs目录
    ];

    /**
     * 工厂实例
     * @var array
     */
    protected static $facs = [];

    /**
     * 模块名
     * v\App::module()
     * @var string
     */
    protected $module = '';

    /**
     * 入口路径
     * @var string
     */
    protected $entry = null;

    /**
     * 调试状态
     * @var bool
     */
    protected $debug = false;

    /**
     * 语言翻译
     * @var array
     */
    protected $langs = ['__langs__' => []];

    /**
     * 语言
     * @var string
     */
    protected $lang = 'zh-cn';

    /**
     * 请求参数，REQUEST
     * @var array
     */
    protected $params = null;

    /**
     * AppWaiter constructor.
     * 应用初始化
     */
    public function __construct() {
        $this->module = v\App::module();
        $this->debug = array_value($_REQUEST, 'debug', v\App::conf('debug', false));
    }

    /**
     * 获得模块入口路径
     * 向上获得入口目录
     * @return string
     */
    public function entry() {
        if (is_null($this->entry)) {
            $module = trim($this->module, '/');
            while (!empty($module)) {
                if (is_file(APP_ROOT . "/$module/index.php"))
                    break;
                $module = trim(strval(dirname($module)), './\\');
            }
            $this->entry = rtrim(APP_ROOT . "/$module", '/');
        }
        return $this->entry;
    }

    /**
     * 载入类文件
     * 第三方类请放在lib/third文件夹
     * 按框架标准定义的类文件不需要显示的载入
     * @param $fileName
     */
    public function load($fileName) {
        $fileName = "lib/$fileName";
        v\App::findModule($fileName);
        return $this;
    }

    /**
     * 获取当前语言|载入应用语言
     * @param array|string $file 语言文件名
     * @return string
     */
    public function lang($file = null) {
        if (is_null($file))
            return $this->lang;

        $file = v\App::absolutePath("/lang/{$this->lang}/" . trim(str_replace('.php', '', $file), '/') . '.php', true);
        if (!in_array($file, $this->langs['__langs__'])) {
            $this->langs['__langs__'][] = $file;
            if (is_file($file)) {
                $lngs = require $file;
                foreach ($lngs as $k => $v) {
                    $this->langs[strtolower($k)] = $v;
                }
            }
        }
        return $this;
    }

    /**
     * 翻译，需要先载入语言文件
     * @param string|array $msg
     * @return string
     */
    public function t($msg) {
        // 翻译数组
        if (is_array($msg)) {
            foreach ($msg as &$value) {
                $value = $this->t($value);
            }
            return $msg;
        } elseif (is_string($msg)) {
            // 获取原消息中的变量
            $tmsg = strtolower($msg);
            preg_match_all('/\[.*?\]/', $tmsg, $vars);
            $vars = $vars[0];
            // 替换原消息变量
            if ($len = count($vars)) {
                $vals = array();
                for ($i = 0; $i < $len; $i++) {
                    $vals[$vars[$i]] = "[$i]";
                }
                $tmsg = strtr($tmsg, $vals);
            }
            // 进行翻译
            if (isset($this->langs[$tmsg])) {
                $msg = empty($vals) ? $this->langs[$tmsg] : strtr($this->langs[$tmsg], array_flip($vals));
            }
        }
        return $msg;
    }

    /**
     * 取得单例实例
     * @param string $classname 类名
     * @return object
     * @throws ClassException
     */
    public function singleton($className) {
        //echo $className;
        if (!isset(self::$facs[$className])) {
            if (class_exists($className)) {
                self::$facs[$className] = new $className();
            } else {
                throw new ClassException("$className not exists");
            }
        }
        return self::$facs[$className];
    }

    /**
     * 设置单例实例
     * @param string $classname 类名
     * @return object
     * @throws ClassException
     */
    public function setSingleton($className, $object) {
        self::$facs[$className] = $object;
        return self::$facs[$className];
    }

    /**
     * 调用外部model目录的方法
     * @desc 为了多网站部署安全,让各网站互不干扰,该配置可将model独立与网站目录之外,调用方法v\App::extModel($modelName); 同v\App::Model($modelName);
     * @desc 优先查询本网站目录model是否存在,否则调用外部路径model PS:此方法只会调用本级model目录和外部定义的model目录(所以需要注意你的filterQuery方法重写)
     * @desc 网站config配置 例 'modelPath' => '..'.DIRECTORY_SEPARATOR.'model'.DIRECTORY_SEPARATOR, 该句调用网站目录上一层中的model目录
     * @param $modelName model名称不需要带.php 使用前必须再config内定义modelPath路径 
     * @return  v\Model
     */
    public function extModel($modelName){
        $modelPath = App::conf('modelPath');
        if(!empty($modelName)){
            if(file_exists(v\App::absolutePath("/model/" . $modelName . '.php'))){
               $model = $this->model($modelName);
            }else{
               if(empty($modelPath)){
                 throw new ClassException("modelPath is empty in config");
               }
               if(file_exists(APP_ROOT. DIRECTORY_SEPARATOR .$modelPath . $modelName . '.php')){
                    require_once APP_ROOT. DIRECTORY_SEPARATOR .$modelPath . $modelName . '.php';
                    $calss = "vApp\\model\\" . $modelName;
                    $model = $this->setSingleton($modelName, new $calss);
                }else{
                    throw new ClassException("$modelName not exists");
                }
            }
        }else{
            throw new ClassException("$modelName Can not be empty");
        }
        return $model;
    }

    /**
     * 取得模型实例
     * @param string $name 模型类名
     * @return v\Model
     */
    public function model($name) {
        // 加入模型命名空间
        if (strpos($name, 'model') === false) {
            $name = "model\\$name";
        }
        $classname = v\App::className($name);
        $model = $this->singleton($classname);
        //$model->reset();  // 使用该方法会把模型置于初始状态
        return $model;
    }

    /**
     * 取得crontab实例
     * @param string $name
     * @return v\Crontab
     */
    public function crontab($name) {
        // 加入crontab命名空间
        if (strpos($name, 'crontab') === false) {
            $name = "crontab\\$name";
        }
        $classname = v\App::className(strtolower($name));
        return $this->singleton($classname);
    }

    /**
     * 取得控制器对象
     * 注意控制器不会向上级模块查找
     * @param string $name
     * @return v\Controller
     */
    public function controller($name) {
        if (strpos($name, 'controller') === false) {
            $name = "controller\\$name";
        }
        $classname = v\App::className($name, false);
        return $this->singleton($classname);
    }

    /**
     * 取得请求数据
     * 控制器里最好使用该方法取请求数据
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function param($key = null, $default = null) {
        if (is_array($key)) {
            $this->params = $default ? (is_null($this->params) ? array_merge($_GET, $_POST, $key) : array_merge($this->params, $key)) : $key;
        } else {
            if (is_null($this->params))
                $this->params = array_merge($_GET, $_POST);
            if (empty($key))
                return $this->params;
            return array_value($this->params, $key, $default);
        }
        return $this;
    }

    /**
     * 生成控制器访问url地址
     * @param array $params 查询参数
     * @param string $path 相对路径
     * @param string $module 模块，默认当前模块，顶级模块请用 /
     * @return string
     */
    public function url($path = null, $params = [], $module = null) {
        if (is_array($path))
            swap($params, $path);
        if (is_string($params))
            swap($params, $module);
        if (empty($params))
            $params = [];

        // 默认当前控制器与请求类型
        $path = empty($path) ? v\Req::controller() : strpos($path, '.') === 0 ? v\Req::controller() . $path : trim($path, '.');
        if (substr($path, -1) != '/' && strpos($path, '.') === false)
            $path .= '.' . v\Req::type();

        $path = v\Router::build($path, $params, $module);
        $path = ltrim_string($path, ENTRY_MODULE . '/');  // 去除入口模块
        $type = v\Req::conf('defaultType');
        if (!empty($type)) {
            $path = rtrim_string($path, ".$type");
        }
        $url = v\Req::conf('rewrite', false) ? "/$path" : '/' . basename($_SERVER['SCRIPT_NAME']) . "?q=" . str_replace('?', '&', $path);
        $url = v\Req::url($url);
        return $url;
    }

    /**
     * 生成文件url链接
     * 文件必须位于static目录
     * @param string $file
     * @param boolean|string 是否向上查找，模块，默认当前模块，顶级模块请用 /
     * @param boolean 是否加入版本信息
     * @return string
     */
    public function link($file, $bubble = false, $ver = false) {
        list($filename, $module) = v\App::absolutePaths("static/$file", $bubble);
        if ($module === false) {
            // 框架文件
            $path = ltrim_string($filename, V_ROOT);
        } else {
            $entryDir = APP_ROOT;  // 顶级模块
            if (!empty($module)) {
                if ($module === $this->module) {
                    // 当前模块
                    $entryDir = $this->entry();
                } else {
                    // 其他模块
                    v\App::module($bubble, function() use (&$entryDir) {
                        $entryDir = v\App::entry();
                    });
                }
            }
            $path = str_replace(APP_SC_DIR, '', ltrim_string($filename, $entryDir));
        }
        // 版本信息处理
        $path = strtr($path, ['//' => '/']);
        if (!empty($ver) && is_file($filename) && ($time = filemtime($filename))) {
            $pos = strrpos($path, '.');
            $path = substr($path, 0, $pos) . '_' . date('YmdHi', $time) . substr($path, $pos);
        }
        $url = v\Req::url($path);
        return $url;
    }

    /**
     * 响应数据
     * @param $status
     * @param $data
     */
    public function resp($status, $data = null) {
        return v\Res::resp($status, $data);
    }

    /**
     * 获取|设置debug模式
     * @param boolean $debug
     * @return boolean
     */
    public function debug($debug = null) {
        if (is_null($debug))
            return $this->debug;
        $this->debug = $debug;
        return $this;
    }

    /**
     * 记录日志
     * @param string $data
     * @param string $file
     */
    public function log($data, $file = 'default.log') {
        // 日志不进行/与中文的编码，日志之间空一行处理
        $data = date('Y-m-d H:i:s') . "\t" . (is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $data) . "\r\n\r\n";
        $logDir = App::conf('logDir');
        $filename = empty($logDir) ? v\App::absolutePath("/logs/$file") : "$logDir/$file";
        @file_put_autodir($filename, $data, FILE_APPEND | LOCK_EX);
        return $this;
    }

    /**
     * 应用开始处理请求
     */
    public function run() {
        v\Req::start();
    }

}
