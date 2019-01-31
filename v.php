<?php

/*
 * v framework
 * 
 * v框架目录结构为
 * v.php 框架入口文件
 * lib目录下文件为框架类文件
 * lib/core目录为框架核心文件，该目录下文件命名空间不带core，直接在v下
 * 
 * @copyright knyon.com
 * @author knyon <knyon@qq.com>
 * @version SVN: $Id: v.php 7273 2015-08-31 12:04:37Z wangyong $
 */

define('V_ROOT', strtr(dirname(__FILE__), array('\\' => '/')));

// auto loader
spl_autoload_register(function($className) {
    if (substr($className, 0, 2) == 'v\\') {
        $fileName = V_ROOT . '/lib/' . strtr(substr($className, 2), ['\\' => '/', 'Worker' => '']) . '.php';
        if (is_file($fileName)) {
            require $fileName;
        }
    }
});

require V_ROOT . '/lib/Comn.php';
require V_ROOT . '/lib/Func.php';
require V_ROOT . '/lib/Boot.php';

class v {

    /**
     * 载入类文件
     *
     * 框架类文件一般不需要手动载入，通常可以自动载入
     * 第三方类文件需要通过此函数载入
     *
     * @param string $file
     */
    public static function load($file) {
        include_once V_ROOT . '/lib/' . trim($file, '/');
    }

    /**
     * 开始运行程序
     */
    public static function start() {
        v\App::run();
    }

}

?>