<?php

/**
 * 应用启动程序
 * 计算应用入口与应用初始化工作
 * 该程序应该作为第一个载入
 *
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/8/3
 * Time: 14:47
 */
class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));
/**
 * 初始化APP
 * 只初始化一次，调用后unset该初始化函数
 */
$initApp = function() {
    // 时间
    define('NOW_TIME', time());
    define('NOW_MICRO_TIME', microtime(true));
    define('APP_SC_DIR', '/_'); // 应用代码目录名称
    // crontab处理
    // crontab是由命令行启动的程序
    $isCrontab = !empty($_SERVER['argv']);
    if ($isCrontab && !empty($_SERVER['argv'][1])) {
        // crontab的参数
        $_GET['q'] = trim($_SERVER['argv'][1], '?& ');
    }
    define('IS_CRONTAB', $isCrontab);

    // 应用入口根目录，不一定是根目录
    $dir = strtr(dirname($_SERVER['SCRIPT_FILENAME']), ['\\' => '/', '//' => '/']);
    define('ENTRY_ROOT', $dir);  //  程序入口根目录
    // 应用根目录，应用跟目录指项目目录
    $config = [];  // 模块的配置
    do {
        $path = dirname($dir);
        if (!is_dir($path . APP_SC_DIR)) {
            break;
        }
        // 同时载入配置文件，从ENTRY_ROOT开始由下向上
        $file = $dir . APP_SC_DIR . '/config/global.php';
        if (is_file($file)) {
            array_extend($config, require $file, false);
        }
        $dir = $path;
    } while ($dir);
    define('APP_ROOT', $dir);  // 应用根目录
    // 应用配置
    $file = APP_ROOT . APP_SC_DIR . '/config/global.php';
    $_ENV['app'] = is_file($file) ? require $file : [];
    $module = trim(ltrim_string(ENTRY_ROOT, APP_ROOT), '/');  // 入口模块，模块的定义从app跟目录开始
    define('ENTRY_NS', rtrim('vApp\\' . str_replace('/', '\\', $module), '\\'));
    define('ENTRY_MODULE', $module);

    // 解析模块，由入口从左向右，因为模块偏少，但是路由会偏多
    $path = ltrim(empty($_GET['q']) ? '' : str_striptrim($_GET['q']), '/');
    $paths = explode('/', $path);
    $ctrl = array_pop($paths);  // 控制器路径
    while (!empty($paths)) {
        $dir = array_shift($paths);
        $path = APP_ROOT . "/$module/$dir" . APP_SC_DIR;
        if (!is_dir($path)) {
            $ctrl = strtr("$dir/" . implode('/', $paths) . '/' . $ctrl, ['//' => '/']);
            break;
        }
        $module .= "/$dir";
        // 是模块，载入配置文件
        $file = $path . '/config/global.php';
        if (is_file($file)) {
            array_extend($config, require $file, true);
        }
    }
    $module = ltrim($module, '/');
    define('APP_MODULE', $module);  // 模块名
    define('MODULE_ROOT', APP_ROOT . '/' . $module);  // 模块跟目录
    if (!empty($module))
        $_ENV[$module] = $config;  // 模块的配置不包含最顶级APP的配置
    $_GET['q'] = $ctrl;

    // auto loader
    spl_autoload_register(function($class) {
        if (substr($class, 0, 2) !== 'v\\') {
            $class = ltrim_string($class, 'vApp');
            if ($pos = strpos($class, '\controller\\')) {
                // 控制器大写转换成下划线隔开
                $pos += 12;
                $str = substr($class, $pos);
                if (ucfirst($str) !== $str) {  // 首字母非大写
                    $str = preg_replace('/[A-Z]/', '_$0', $str);
                    $class = substr($class, 0, $pos) . strtolower($str);
                } else {  // 直接转成小写
                    $class = strtolower($class);
                }
            }
            $fileName = strtr($class, ['\_'            => '/',
                '\controller\\' => APP_SC_DIR . '/controller/',
                '\model\\'      => APP_SC_DIR . '/model/',
                '\crontab\\'    => APP_SC_DIR . '/crontab/',
                '\lib\\'        => APP_SC_DIR . '/lib/', '\\'            => '/']);
            $fileName = APP_ROOT . "$fileName.php";
            if (is_file($fileName))
                require_once $fileName;
        }
    });

    // 错误捕获
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        // 抛出error异常
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }, error_reporting());

    // 异常捕获
    set_exception_handler(function($e) {
        if (!IS_CRONTAB) {
            ob_clean();
            try {
                header('HTTP/1.1 500 Internal Server Error');
            } catch (\Exception $e) {
                
            }
        }
        // 记录错误日志
        $p = $e->getPrevious();
        while ($p) {
            $e = $p;
            $p = $e->getPrevious();
        }
        $err = $e->getMessage() . ' (' . $e->getCode() . '), ' . $e->getFile() . ' (' . $e->getLine() . ')' . "\n" . $e->getTraceAsString();
        if (!IS_CRONTAB) {
            $err .= ', ' . v\Req::furl() . ', ' . v\Req::ua();
        }
        v\App::log($err, 'error.log');
        // 向用户输出
        if (v\App::debug()) {
            echo strtr("$err\n", ["\n" => "</br>\n"]);
        } else {
            echo 'Has a error';
        }
        exit;
    });
};
$initApp();
unset($initApp);  // 只允许启动应用一次，启动后释构启动函数
v\App::module(APP_MODULE);  // app应用设为当前模块