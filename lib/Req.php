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

class Req extends v\ServiceFactory {

    /**
     * 服务提供对象名
     * 必须子类定义
     * @var string
     */
    protected static $objname = 'v\Request';

    /**
     * 服务提供对象
     * 必须子类定义
     * @var object
     */
    protected static $object;

    /**
     * 必须是单例模式
     * @var boolean
     */
    protected static $single = true;

}

class Request extends v\Service {

    /**
     * 配置
     * @var array
     */
    protected static $configs = [
        // 支持取IP的header
        'ipHeaders' => [],
        'rewrite' => false, // 重写开启
        'defaultType' => 'html', // 默认请求类型
        'err404Controller' => 'err404' // 404错误控制器
    ];

    /**
     * 静态方法，只允许实例化一次
     * @var self
     */
    protected static $object = null;

    /**
     * 是否已经启动
     * @var bool
     */
    protected static $started = false;

    /**
     * 解码后的请求地址
     * @var string
     */
    protected $url = '';

    /**
     * 请求根目录
     * @var string
     */
    protected $base = '';

    /**
     * 请求相对名称
     * @var string
     */
    protected $path = '';

    /**
     * 控制器
     * @var string
     */
    protected $controller = null;

    /**
     * 请求类型
     * get|post|put|delete|head
     * @var string
     */
    protected $method = '';

    /**
     * 请求格式
     * @var string
     */
    protected $type = '';

    /**
     * 请求格式版本, _标识
     * filename_201801020304.extname 201801020304为版本号
     * @var string
     */
    protected $version = '';

    /**
     * 是否ajax请求
     * @var boolean
     */
    protected $isAjax = null;

    /**
     * 是否移动端请求
     * @var boolean
     */
    protected $isMobile = null;

    /**
     * 是否友好的url请求，即不带参数
     * @var boolean
     */
    protected $isFriendly = null;

    /**
     * 是否GET请求
     * @var boolean
     */
    protected $isGet = null;

    /**
     * 域名定义
     * @var array
     */
    protected $domains = null;

    /**
     * 请求IP地址
     * @var string
     */
    protected $ip = null;

    /**
     * 构造函数
     */
    public function __construct() {
        if (self::$object)
            return;
        self::$object = $this;

        $this->method = strtoupper(str_striptrim(empty($_REQUEST['_method']) ? array_value($_SERVER, 'REQUEST_METHOD', 'GET') : $_REQUEST['_method']));
        $this->url = isset($_SERVER['HTTP_X_REWRITE_URL']) ? $_SERVER['HTTP_X_REWRITE_URL'] : array_value($_SERVER, 'REQUEST_URI');
        $this->base = rtrim(substr($_SERVER['SCRIPT_NAME'], 0, strrpos($_SERVER['SCRIPT_NAME'], '/') + 1), '/');
        $this->type = $this->conf('defaultType', '');
        $this->isGet = $this->method === 'GET';
        $this->parsePath();
        $this->parseParam();
        if (IS_CRONTAB) {
            // 计划任务不路由
            $this->controller = $this->path;
        } elseif (strpos($this->path, 'static/') !== false) {
            // static 目录文件不路由
            $this->controller = $this->path;
        } else {
            // 解析路由控制器
            $this->controller = v\Router::parse($this->path);
            // 控制器不允许由下向上找，必须准确定位，没有该控制器最后一级则作action处理
            if (!is_file(v\App::absolutePath("/controller/{$this->controller}.php"))) {
                $this->method = strtoupper(basename($this->controller));
                $this->controller = dirname($this->controller);
            }
        }
    }

    /**
     * 生成带域名端口的url
     * @param string $url
     * @return string
     */
    public function furl($url = null) {
        if (empty($url))
            $url = $this->url;
        if (strpos($url, '://') === false && strpos($url, '//') !== 0) {
            $prol = 'http://';
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                $prol = 'https://';
            } elseif (!empty($_SERVER['SERVER_PROTOCOL'])) {
                $prol = explode('/', $_SERVER['SERVER_PROTOCOL']);
                $prol = strtolower(reset($prol)) . '://';
            }
            $host = $prol . strtolower($_SERVER['HTTP_HOST']);
            $url = "$host$url";
        }
        return $url;
    }

    /**
     * 生成带基准路径的url
     * / 表示取得顶级路径
     * @param string $url
     * @param boolean $full 是否带协议
     * @return string
     */
    public function url($url = null, $full = false) {
        if (is_bool($url))
            swap($url, $full);

        if (is_null($url))  // 取得url
            return $this->url;
        if (strpos($url, '://') !== false)  // 判断是否全路径
            return $url;
        $url = empty($url) ? $this->base : $this->base . '/' . ltrim($url, '/');
        return $full ? $this->furl($url) : $url;
    }

    /**
     * 获得域名定义
     * 默认当前请求域名
     * @param string $url 带域名url
     * @param int $key 0域名，1\2\3对应顶级、二级、三级域名字符
     * @return array|string
     */
    public function domain($key = 0, $url = null) {
        if (!empty($url)) {
            $host = $url;
        } else {
            if (!empty($this->domains)) {
                $ds = $this->domains;
                return $key === null ? $ds : (isset($ds[$key]) ? $ds[$key] : '');
            }
            $host = $_SERVER['HTTP_HOST'];
        }

        // 取出域名部分
        if ($pos = strpos($host, '://'))
            $host = substr($host, $pos + 3);
        $host = preg_replace('/[^\w\._-].*$/i', '', strtolower($host));

        $ds = explode('.', $host);
        $ct = count($ds);
        if ($ct > 1) {
            $slds = ['com' => 1, 'edu' => 1, 'gov' => 1, 'net' => 1, 'org' => 1, 'info' => 1];  // 排.com.cn类似形式域名
            if ($ct > 2 && isset($slds, $ds[$ct - 2]) && !isset($slds, $ds[$ct - 1])) {
                $ds[$ct - 2] .= ".{$ds[$ct - 1]}";
                unset($ds[--$ct]);
            }
            $ds[$ct - 2] .= ".{$ds[$ct - 1]}";
            $ds[$ct - 1] = $host;
            $ds = array_reverse($ds);
        }
        if (empty($url)) {
            $this->domains = $ds;
        }
        return $key === null ? $ds : (isset($ds[$key]) ? $ds[$key] : '');
    }

    /**
     * 获得http请求格式类型
     * 默认处理xml|json|html|text四种格式，更多格式请在主配置文件中定义res->types
     * @return string
     */
    public function type() {
        return $this->type;
    }

    /**
     * 获得请求资源名称
     * @return string
     */
    public function path() {
        return $this->path;
    }

    /**
     * 获得请求方法
     * get|delete|post|put
     * @return string
     */
    public function method() {
        return $this->method;
    }

    /**
     * 是否ajax请求
     * @return string | boolean
     */
    public function isAjax() {
        if (is_null($this->isAjax)) {
            $this->isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
        }
        return $this->isAjax;
    }

    /**
     * 是否移动设备
     * @return string
     */
    public function isMobile() {
        if (is_null($this->isMobile)) {
            $this->isMobile = !!preg_match('/android|iphone|ipad|mobile/i', $this->ua());
        }
        return $this->isMobile;
    }

    /**
     * 是否请求友好的url
     * @return bool
     */
    public function isFriendly() {
        if (is_null($this->isFriendly)) {
            $this->isFriendly = strpos($this->url, '.php') === false ? empty($_GET) || (count($_GET) == 1 && isset($_GET['q'])) : false;
        }
        return $this->isFriendly;
    }

    /**
     * 是否html请求
     * @return boolean
     */
    public function isHtml() {
        return $this->type === 'html';
    }

    /**
     * 是否GET请求
     * @return boolean
     */
    public function isGet() {
        return $this->isGet;
    }

    /**
     * 获得请求ip
     * 如果转发或代理情况下需要获取用户原IP，需要配置ipHeaders数据，但这样头部可伪造，可能存在安全隐患
     * @param boolean $safe 是否取得安全的IP，不能伪造
     * @return string
     */
    public function ip($safe = false) {
        if (!empty($safe)) {
            return array_value($_SERVER, 'REMOTE_ADDR', '127.0.0.1'); // crontab没有该参数，则用本地地址
        }
        if (empty($this->ip)) {
            $headers = $this->conf('ipHeaders');
            foreach ($headers as $key) {
                if (isset($_SERVER[$key])) {
                    $ip = $_SERVER[$key];
                    if (ctype_digit(str_replace('.', '', $ip))) {
                        $this->ip = $ip;
                        return $ip;
                    }
                }
            }
            $this->ip = $this->ip(true);  // 无转发IP则使用真实IP
        }
        return $this->ip;
    }

    /**
     * 获取user agent
     * @return string
     */
    public function ua() {
        return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    }

    /**
     * 获得cookie
     * @param string $name
     * @param string $default 默认值
     * @return string
     */
    public function cookie($name, $default = null) {
        return array_value($_COOKIE, $name, $default);
    }

    /**
     * 获得cookie后删除
     * @param string $name
     * @param string $default 默认值
     * @return string
     */
    public function cookieOnce($name, $default = null) {
        $value = array_value($_COOKIE, $name, $default);
        v\Res::cookie($name, 0);
        return $value;
    }

    /**
     * 获得请求来路地址
     * @return string
     */
    public function referer() {
        return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    }

    /**
     * 获得GET查询参数
     * @param string $key 键
     * @param string|array $default 默认值
     * @return mixed
     */
    public function get($key, $default = '') {
        $value = array_value($_GET, $key, $default);
        return striptrim($value);
    }

    /**
     * 获得POST查询参数
     * @param string $key 键
     * @param string|array $default 默认值
     * @return mixed
     */
    public function post($key, $default = '') {
        $value = array_value($_POST, $key, $default);
        return striptrim($value);
    }

    /**
     * 获得REQUEST查询参数
     * @param string $key 键
     * @param string|array $default 默认值
     * @return mixed
     */
    public function request($key, $default = '') {
        $value = array_value($_REQUEST, $key, $default);
        return striptrim($value);
    }

    /**
     * 取得控制器名称
     * @return string
     */
    public function controller() {
        return $this->controller;
    }

    /**
     * 取得控制器名称
     * @return string
     */
    public function ctrler() {
        return $this->controller;
    }

    /**
     * 开始运行应用
     * 应用的允许必须在boot之后
     * 只能允许一次应用
     */
    public function start() {
        if (self::$started)
            return;
        self::$started = true;

        if (IS_CRONTAB) {
            $this->parseCrontab();
        } elseif (strpos($this->controller, 'static/') !== false) {
            $this->parseStatic();
        } else {
            $this->parseController();
        }
    }

    /**
     * 解析查询字串
     * q为查询资源
     */
    protected function parsePath() {
        $qstr = empty($_GET['q']) ? '/' : strtr(str_striptrim($_GET['q']), ['$' => '']);
        $qstr = preg_replace(['/\.\.+/', '/\/\/+/'], ['.', '/'], $qstr);
        $qstr = ltrim(strtr($qstr, ['.php' => '', './' => '']), '/.');  // 不允许访问php文件
        // 解析格式
        if ($pos = strrpos($qstr, '.')) {
            $this->type = substr($qstr, $pos + 1);
            $qstr = substr($qstr, 0, $pos);
        }
        if (!empty($this->type) && !ctype_alnum($this->type)) {
            v\Res::end(406);
        }
        $this->path = $qstr;
    }

    /**
     * 解析查询参数
     * 包含get|delete|put|post方法的查询
     * post与put允许json  fromData 格式的数据
     */
    protected function parseParam() {
        $mth = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');
        if ($mth === 'POST' || $mth === 'PUT') {
            if (!empty($_SERVER['HTTP_CONTENT_TYPE']) && strpos($_SERVER['HTTP_CONTENT_TYPE'], 'json') !== false) {
                // json 解析
                $raw_data = file_get_contents('php://input');
                $_POST = json_decode($raw_data, true);
            } elseif ($mth === 'PUT') {
                // json 解析
                $this->parsePut();
            }
            $_REQUEST = array_merge($_REQUEST, $_POST);
        }
        unset($_GET['q']);
    }

    /**
     * 解析PUT请求
     */
    protected function parsePut() {
        $raw_data = file_get_contents('php://input');
        $boundary = substr($raw_data, 0, strpos($raw_data, "\r\n"));
        if (empty($boundary)) {
            // www-form-urlencode 解码
            parse_str($raw_data, $_POST);
        } else {
            // formData Fetch each part 
            $parts = array_slice(explode($boundary, $raw_data), 1);
            foreach ($parts as $part) {
                // If this is the last part, break
                if ($part == "--\r\n")
                    break;

                // Separate content from headers
                $part = ltrim($part, "\r\n");
                list($raw_headers, $body) = explode("\r\n\r\n", $part, 2);

                // Parse the headers list
                $raw_headers = explode("\r\n", $raw_headers);
                $headers = [];
                foreach ($raw_headers as $header) {
                    list($name, $value) = explode(':', $header);
                    $headers[strtolower($name)] = ltrim($value, ' ');
                }

                // Parse the Content-Disposition to get the field name, etc.
                if (isset($headers['content-disposition'])) {
                    $filename = null;
                    $tmp_name = null;
                    preg_match('/^(.+); *name="([^"]+)"(; *filename="([^"]+)")?/', $headers['content-disposition'], $matches);
                    list(, $type, $name) = $matches;

                    // 解析上传文件
                    if (isset($matches[4])) {
                        //if labeled the same as previous, skip
                        if (isset($_FILES[$matches[2]])) {
                            continue;
                        }
                        //get filename
                        $filename = $matches[4];
                        //get tmp name
                        $tmp_name = tempnam(sys_get_temp_dir(), 'php');
                        //populate $_FILES with information, size may be off in multibyte situation
                        $name = strtr($matches[2], ['[' => '.', ']' => '']);
                        array_setval($_FILES, $name, [
                            'error' => 0,
                            'name' => $filename,
                            'tmp_name' => $tmp_name,
                            'size' => strlen($body),
                            'type' => $value
                        ]);
                        //place in temporary directory
                        file_put_contents($tmp_name, $body);
                    } else {
                        //Parse Field
                        $name = strtr($name, ['[' => '.', ']' => '']);
                        array_setval($_POST, $name, substr($body, 0, strlen($body) - 2));
                    }
                }
            }
        }
    }

    /**
     * 解析404控制器
     */
    protected function parseErr404() {
        $controller = v\App::conf('err404Controller', 'err404');
        $file = v\App::absolutePath("/controller/{$controller}.php");
        if (is_file($file)) {
            $this->controller = $controller;
            $this->method = 'GET';
            require $file;
            $this->parseAction();
        } else {
            v\Res::header('Status: 404 Not Found')->end(404);
        }
    }

    /**
     * 执行控制器action
     */
    protected function parseAction() {
        $ctrler = v\App::controller($this->controller);
        $action = 'res' . ucfirst(strtolower($this->method));
        if (method_exists($ctrler, $action) || isset($ctrler->$action) || method_exists($ctrler, '__call')) {
            $rs = call_user_func([$ctrler, $action]);
            if (!empty($rs) && !is_object($rs))
                v\Res::auto($rs);
            v\Res::end();
        }
        // 没有该方法直接返回405
        v\Res::header('Status: 405 Method Not Allowed')->end(405);
    }

    /**
     * 控制器请求解析
     * 查找顺序：控制器文件->控制器与action->404控制器
     */
    protected function parseController() {
        // 判断是否有该控制器，控制器不允许由下向上找，必须准确定位
        $file = v\App::absolutePath("/controller/{$this->controller}.php");
        if (is_file($file)) {
            require_once $file;
            $this->controller = strtr($this->controller, ['_' => '']); // 控制器名称实际去掉_
            $this->parseAction();
        } else {
            $this->parseErr404();
        }
    }

    /**
     * crontab任务请求解析
     */
    protected function parseCrontab() {
        $crontab = v\App::crontab($this->controller);
        $crontab->run();
    }

    /**
     * 文件请求解析
     * 解析static文件夹内容
     * 所有静态文件放入static文件夹，包括界面样式相关文件
     */
    protected function parseStatic() {
        // 去掉文件版本信息
        $this->controller = preg_replace('/_\d{12}/', '', $this->controller);
        $file = "/{$this->controller}.{$this->type}";
        $filename = v\App::absolutePath($file, true);
        if (is_file($filename)) {
            $content = file_get_contents($filename);
        } else {
            $parsefn = 'parse' . ucfirst($this->type);
            if (method_exists($this, $parsefn)) {
                $content = $this->$parsefn($file);
            }
            if (empty($content)) {
                $this->parseErr404();
            }
        }
        $this->isFriendly = true;  // 静态文件去掉参数，强制缓存
        v\Res::body($content)->cache(86400 * 360 * 10)->end(200);
    }

    /**
     * 销毁时删除PUT上传的临时文件
     */
    public function __destruct() {
        if ($this->method === 'PUT') {
            foreach ($_FILES as $files) {
                if (isset($files['name'])) {
                    if (file_exists($files['tmp_name']))
                        unlink($files['tmp_name']);
                } else {
                    foreach ($files as $file) {
                        if (isset($file['tmp_name'])) {
                            if (file_exists($file['tmp_name']))
                                unlink($file['tmp_name']);
                        } else {
                            foreach ($file as $file1) {
                                if (isset($file1['tmp_name']) && file_exists($file1['tmp_name']))
                                    unlink($file1['tmp_name']);
                            }
                        }
                    }
                }
            }
        }
    }

}
