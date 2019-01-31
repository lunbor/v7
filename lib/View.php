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
 * Class View
 * 视图处理类
 *
 * @package v
 */
class View extends v\ServiceFactory {

    /**
     * 服务提供对象名
     * 必须子类定义
     * @var string
     */
    protected static $objname = 'v\ViewService';

    /**
     * 服务提供对象
     * 必须子类定义
     * @var object
     */
    protected static $object;

}

class ViewService extends v\Service {

    /**
     * 视图数据
     * @var array
     */
    protected $data = [];

    /*
     * crumb脆片变量名
     * @var array
     */
    protected $crumbs = [];

    /**
     * 当前模板文件所在文件夹
     * @var string
     */
    protected $dir = '';

    /**
     * 控制器对象
     * @var v\Controller
     */
    protected $controller = null;

    /**
     * 取得视图文件路径
     * @param string $file
     * @return string
     */
    protected function filePath($file) {
        $dir = substr($file, 0, 2) === '//' ? '' : $this->dir;
        $file = v\App::absolutePath("/view/{$dir}/$file", true);
        if (!is_file($file)) {
            throw new FileException("$file not exists");
        }
        return $file;
    }

    /**
     * 取得模板文件所在文件夹
     * @param string $file
     * @return string
     */
    protected function dirPath($file) {
        return trim($this->dir . '/' . (strpos($file, '/') ? dirname($file) : ''), '/');
    }

    /**
     * 关联 | 获取关联控制器
     * @param v\Controller $ctrlObj 控制器对象
     * @return $this
     */
    public function controller($ctrlObj = null) {
        if (is_null($ctrlObj)) {
            if (empty($this->controller)) {
                // 取控制器对象时必须已经关联了控制器
                throw new v\PropertyException('View property controller is null');
            }
            return $this->controller;
        }
        $this->controller = $ctrlObj;
        return $this;
    }

    /**
     * 给视图赋值
     * @param string | array $key
     * @param mixed $data
     * @return \v\VewSvc
     */
    public function assign($key, $data = null) {
        if (is_array($key))
            $this->data = array_merge($this->data, $key);
        else {
            $this->data[$key] = $data;
        }
        return $this;
    }

    /**
     * 解析视图
     * @param string $file
     * @param array $data 模板变量，在非php模板中使用，在php模板中使用会有性能损耗
     */
    public function resp($file, $data = null) {
        $fileName = $this->filePath($file);
        $this->dir = $this->dirPath($file); // 记录起始文件架路径
        if (!empty($data))
            $this->assign($data);

        extract($this->data);
        ob_start();
        require $fileName;
        $html = ob_get_contents();
        ob_end_clean();

        // 解析{}变量
        if (!empty($data)) {
            $keys = preg_replace(['/^/', '/$/'], ['{', '}'], array_keys($data));
            $data = array_combine($keys, $data);
            foreach ($data as $k => $v) {
                if (is_array($v))
                    unset($data[$k]);
            }
            $html = strtr($html, $data);
        }
        return v\App::resp(200, $html);
    }

    /**
     * 获取变量值
     * 支持模板作为默认值
     * @param string $name
     * @param string $default
     * @return string
     */
    public function data($name, $default = null) {
        $value = array_value($this->data, $name);
        if (is_null($value) && !is_null($default)) {
            if (is_string($default) && strpos($default, '.php')) {
                $value = $this->crumb($default);
            } else {
                $value = $default;
            }
        }
        return $value;
    }

    /**
     * 引入模板文件
     * @param string $file
     * @param array $data 模板碎片变量
     * @return string
     */
    public function crumb($file, $data = null) {
        // 模板文件
        $fileName = $this->filePath($file);
        $dir = $this->dir;
        $this->dir = $this->dirPath($file); // 改变文件架路径为当前膜拜所在路径
        if (!empty($data))
            extract($data);
        ob_start();
        require $fileName;
        $value = ob_get_contents();
        ob_end_clean();
        $this->dir = $dir; // 恢复源文件夹路径
        return $value;
    }

    /**
     * 模板碎片开始
     * @param string  $name 碎片名或者碎片文件
     * @param array $data 传给模板的变量
     */
    public function startCrumb($name, $data = []) {
        $this->crumbs[] = [$name, $data];
        ob_start();
        return '';
    }

    /**
     * 载入碎片模板，用于模板的载入
     * @param string $name
     * @param array $data 传给模板的变量
     */
    public function loadCrumb($name, $data = []) {
        return $this->startCrumb($name, $data);
    }

    /**
     * 模板碎片结束，必须和startCrumb或者loadCrumb成对出现
     * @param string $name
     * @return string
     */
    public function endCrumb($name) {
        $content = ob_get_contents();
        ob_end_clean();
        $var = array_pop($this->crumbs);
        $name = $var[0];
        if (!strpos($name, '.php')) {
            $this->data[$name] = $content;
        } else {
            $this->data['content'] = $content;
            $var[1]['content'] = $content;
            return $this->crumb($name, $var[1]);
        }
        return '';
    }

}
