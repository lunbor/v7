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
 * Class Router
 * 路由服务
 * 解析路由与重建路由
 * @package v
 */
class Router extends v\ServiceFactory {

    /**
     * 服务提供对象名
     * 必须子类定义
     * @var string
     */
    protected static $objname = 'v\RouterService';

    /**
     * 服务提供对象
     * 必须子类定义
     * @var object
     */
    protected static $object;

}

class RouterService extends v\Service {

    /**
     * 配置路由
     * @var array
     */
    protected $conf_routes = [];

    /**
     * 正则路由
     * @var array
     */
    protected $preg_routes = [];

    /**
     * 载入模块下的路由
     * @param string $module
     * @param boolean $preg
     * @return array
     */
    protected function loadRoute($module, $preg = false) {
        if (empty($module))
            $module = '/';
        if (!isset($this->conf_routes[$module])) {
            $file = v\App::absolutePath('config/route.php', $module);
            $this->conf_routes[$module] = $this->preg_routes[$module] = [];
            if (is_file($file)) {
                $this->conf_routes[$module] = require $file;
                $keys = preg_replace(['/\//', '/^/', '/$/'], ['\/', '/^', '$/', '([^\/]+)'], array_keys($this->conf_routes[$module]));
                $rous = preg_replace('/^([^?]+)$/', '$0?', $this->conf_routes[$module]);
                $this->preg_routes[$module] = array_combine($keys, $rous);
            }
        }
        return $preg ? $this->preg_routes[$module] : $this->conf_routes[$module];
    }

    /**
     * 获得路由映射
     * @param string $path
     * @param array $params
     * @param array $routes
     * @return string
     */
    protected function routeMaps($path, &$params, &$routes) {
        $ctrlps = [];  // 控制器参数
        $ctrlvs = [];  // 控制器固定值
        if (!empty($routes)) {
            $num_by_key = -1;
            $num_by_assoc = -1;
            // 找出匹配最多查询参数且所有匹配所有映射变量的路由
            foreach ($routes as $route_path => $route_ctrl) {
                if (!empty($route_ctrl)) {
                    parse_str($route_ctrl, $vals);
                    $dcount = count($vals) - substr_count($route_ctrl, '$');
                    if ($dcount > 0) {
                        // 有固定值匹配则需完全匹配固定值
                        $vars = array_intersect_assoc($vals, $params);
                        $num1 = count($vars);
                        if ($dcount == $num1 && $num1 >= $num_by_assoc) {
                            $vals = array_diff_key($vals, $vars);
                            $num = count(array_intersect_key($vals, $params));
                            // 判断剩余参数是否最多匹配
                            if ($num > $num_by_key) {
                                $num_by_key = $num;
                                $num_by_assoc = $num1;
                                $path = $route_path;
                                $ctrlps = $vals;
                                $ctrlvs = $vars;
                            }
                        }
                    } elseif ($num_by_assoc <= 0) {
                        // 非固定值匹配
                        $num = count(array_intersect_key($vals, $params));
                        if ($num > $num_by_key) {
                            $num_by_key = $num;
                            $path = $route_path;
                            $ctrlps = $vals;
                        }
                    }
                } elseif ($num_by_key < 0) { // path为空取路由
                    $path = $route_path;
                    $num_by_key = 0;
                }
            }

            // 去除参数固定值
            if (!empty($ctrlvs)) {
                $params = array_diff_assoc($params, $ctrlvs);
            }
        }
        $routes = $ctrlps;
        return $path;
    }

    /**
     * 解析请求
     * @param string $path
     */
    public function parse($path) {
        $module = v\App::module();
        $controller = 'index';
        $path = trim($path, '/');
        if (!empty($path)) {
            $controller = $path;
            $rous = $this->loadRoute($module, true);
            if (!empty($rous)) {
                $path = trim(preg_replace(array_keys($rous), $rous, $path, 1), '?');
                if ($pos = strpos($path, '?')) {
                    parse_str(substr($path, $pos + 1), $param);
                    $_GET = array_merge($_GET, $param);
                    $controller = substr($path, 0, $pos);
                } else {
                    $controller = $path;
                }
            }
        }
        return $controller;
    }

    /**
     * 建立路由
     * @param string $path
     * @param string $params
     * @param string $module
     * @return string
     */
    public function build($path, $params, $module = null) {
        // 默认当前模块
        $module = is_null($module) ? v\App::module() : $module;
        // 去除空数据
        foreach ($params as $k => $v) {
            if (is_null($v) || $v == '')
                unset($params[$k]);
        }
        ksort($params);

        // 扩展名
        $type = '';
        if ($pos = strrpos($path, '.')) {
            $type = substr($path, $pos);
            $path = substr($path, 0, $pos);
        }

        $path = trim($path, '/');
        $routes = preg_filter('/^' . strtr($path, ['/' => '\/']) . '\??/', '', $this->loadRoute($module));
        $path = $this->routeMaps($path, $params, $routes);
        // 替换路由param
        if (!empty($routes)) {
            $i = 1;
            $vars = array_flip($routes);
            $path = preg_replace_callback('/\(.*?\)/', function ($matches) use (&$params, &$vars, &$i) {
                $key = $vars["$$i"];
                $value = array_value($params, $key, '_');
                unset($params[$key]);
                $i++;
                return $value;
            }, $path);
            $path = rtrim($path, '/_');
        }
        // 无扩展名则为目录
        $path = $module . '/' . ltrim($path, '/') . ($type ? $type : '/');
        if (!empty($params)) {
            $path .= '?' . strtr(http_build_query($params), ['%7B' => '{', '%7D' => '}']);
        }
        return ltrim($path, '/');
    }

}
