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
 * Class Res
 * 响应服务
 * 响应格式转换与处理
 * @package v
 */
class Res extends v\ServiceFactory {

    /**
     * 服务提供对象名
     * 必须子类定义
     * @var string
     */
    protected static $objname = 'v\Response';

    /**
     * 服务提供对象
     * 必须子类定义
     * @var object
     */
    protected static $object;

}

class Response extends v\Service {

    /**
     * 配置
     * @var array
     */
    protected static $configs = [
        'charset' => 'utf-8',
        'chartypes' => [
            'html' => 1,
            'xml' => 1,
            'json' => 1,
            'css' => 1,
            'js' => 1,
            'csv' => 1,
            'txt' => 1,
            'md' => 1
        ],
        'types' => [
            'html' => 'text/html',
            'xml' => 'text/xml',
            'json' => 'application/json',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'gif' => 'image/gif',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'ico' => 'image/x-icon',
            'swf' => 'application/x-shockwave-flash',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'otf' => 'application/x-font-otf',
            'eot' => 'application/octet-stream',
            'woff' => 'application/x-font-woff',
            'svg' => 'image/svg+xml',
            'ttf' => 'application/octet-stream',
            'csv' => 'text/csv',
            'file' => 'application/octet-stream',
            'md' => 'text/html'
        ]
    ];

    /**
     * http 头
     * @var array
     */
    protected $headers = [];

    /**
     * 响应正文
     * @var string
     */
    protected $body = '';

    /**
     * 最后响应状态
     * @var string
     */
    protected $status = 200;

    /**
     * 格式
     * @var string
     */
    protected $type = 'txt';

    /**
     * 编码
     * @var string
     */
    protected $charset = 'utf-8';

    /**
     * 缓存时间
     * @var int
     */
    protected $cacheAge = 0;

    /**
     * 跨域jsonp函数
     * @var string
     */
    protected $jsonpCallback = null;

    /**
     * 是否文件流
     * @var boolean
     */
    protected $isStream = false;

    /**
     * 响应json转换参数
     * @var bool
     */
    protected $jsonOption = 0;

    /**
     * 初始化设置系统编码
     */
    public function __construct() {
        $this->type = v\Req::type();
        $this->charset = $this->conf('charset', 'utf-8');
    }

    /**
     * 设置cookie
     * @param string $name
     * @param string $value 为0或false时删除cookie
     * @param int $expire 过期时间，秒。如60
     * @param string $domain 域名
     */
    public function cookie($name, $value, $expire = 0, $domain = '') {
        $expire = empty($value) ? NOW_TIME - 60 : ($expire === 0 ? 0 : NOW_TIME + $expire);
        $base = v\Req::url('/');
        setcookie($name, $value, $expire, $base, $domain);
        return $this;
    }

    /**
     * 响应缓存头
     * @param int $sec 大于当前时间为到期时间，小于0不缓存
     */
    public function cache($sec = null) {
        if (is_null($sec))
            return $this->cacheAge;

        if ($sec <= 0) {
            header('Cache-Control:no-cache,private');
            $this->cacheAge = 0;
        } else {
            $now = NOW_TIME;
            if ($sec > $now) {
                // 过期时间
                $expire = gmdate('D, d M Y H:i:s', $sec) . ' GMT';
                $last = gmdate('D, d M Y H:i:s', $now) . ' GMT';
                $this->header("Expires: $expire");
                $this->header("Last-Modified: $last");
                $this->cacheAge = $sec - $now;
            } else {
                // 缓存时长
                $last = gmdate('D, d M Y H:i:s', $now) . ' GMT';
                $this->header("Cache-Control:max-age=$sec");
                $this->header("Last-Modified: $last");
                $this->cacheAge = $sec;
            }
        }
        return $this;
    }

    /**
     * 输出http头，同php header
     * @param string $string
     * @param boolean $replace
     * @param int $http_response_code
     */
    public function header($string, $replace = true, $http_response_code = null) {
        if (strpos($string, 'Status:') === 0)
            $string = str_replace('Status', $_SERVER['SERVER_PROTOCOL'], $string);
        $this->headers[] = [$string, $replace, $http_response_code];
        return $this;
    }

    /**
     * 响应状态码
     * @param int $no
     * @param string $descr 该参数没实际意义，只做描述说明
     * @return int
     */
    public function status($no = null, $descr = null) {
        if (empty($no)) {
            return $this->status;
        }
        $this->status = $no;
        return $this;
    }

    /**
     * 响应类型
     * @param string $type
     */
    public function type($type) {
        $this->type = $type;
        return $this;
    }

    /**
     * 设置字符编码
     * @param string $charset
     * @return $this
     */
    public function charset($charset) {
        $this->charset = $charset;
        return $this;
    }

    /**
     * 响应字符串
     * @param string $text
     */
    public function text($text) {
        $this->body = $text;
        $this->type('txt');
        return $this;
    }

    /**
     * 响应html
     * @param array | string $data
     */
    public function html($data) {
        if (is_array($data)) {  // 数组解析成JSON
            $this->xml($data);
        } else {
            $this->body = $data;
        }
        $this->type('html');
        return $this;
    }

    /**
     * 响应xml
     * @param array|string $data
     * @return string
     */
    public function xml($data) {
        $charset = $this->conf('charset', 'utf-8');
        if (!is_array($data))
            return substr($data, 0, 5) == '<?xml' ? $data : "<?xml version='1.0' encoding='{$charset}'?><data>" . htmlentities($data, null, $charset) . '</data>';

        $xml = simplexml_load_string("<?xml version='1.0' encoding='{$charset}'?><data />");
        $fun = function($v, $k, $xml) use (&$fun, $charset) {
            (is_int($k) || ctype_digit(substr($k, 0, 1))) and $k = "d$k";
            if (is_array($v)) {
                $node = $xml->addChild($k);
                array_walk($v, $fun, $node);
            } else {
                $xml->addChild($k, htmlentities($v, null, $charset));
            }
        };
        array_walk($data, $fun, $xml);
        $this->body = $xml->asXML();
        $this->type('xml');
        return $this;
    }

    /**
     * 响应json
     * @param $data
     * @return $this|Response
     */
    public function json($data) {
        $this->body = json_encode($data, $this->jsonOption);
        $this->type('json');
        return $this;
    }

    /**
     * 响应json的格式化参数
     * 默认强制转换为json对象
     * @param int $option
     * @return $this
     */
    public function jsonOption($option = JSON_FORCE_OBJECT) {
        $this->jsonOption = $option;
        return $this;
    }

    /**
     * jsonp响应
     * @param string $param
     */
    public function withJsonp($param = null) {
        if (is_null($param))
            $param = $this->conf('jsonpParam');
        if (!empty($_GET[$param]))
            $this->jsonpCallback = $_GET[$param];
        return $this;
    }

    /**
     * 设置|取得响应正文
     * @param string $string
     * @return string
     */
    public function body($string = null) {
        if (is_null($string))
            return $this->body;
        $this->body = $string;
        return $this;
    }

    /**
     * 响应json或xml的api格式
     * @param array $data
     */
    public function auto($data) {
        $type = $this->type;
        if (in_array($type, ['json', 'xml', 'html'])) {
            $this->$type($data);
        } elseif (is_string($data)) {
            $this->body = $data;
        } elseif (is_array($data) && $this->status >= 400) {
            $this->json($data);
        } else {
            $this->end(406, 'Not Acceptable');
        }
        return $this;
    }

    /**
     * 返回响应数据
     * @param $status
     * @param $data
     */
    public function resp($status, $data) {
        $this->status = $status;
        $this->body = $data;
        return $data;
    }

    /**
     * 响应结束
     * @param int $status
     * @param string|array $content
     */
    public function end($status = null, $content = null) {
        // 文件流输出，直接结束
        if ($this->isStream)
            exit;

        if (!empty($status))
            $this->status($status);
        if (!empty($content)) {
            $this->auto($content);
        } elseif (is_array($this->body)) {  // 数组需要重新格式化数据
            $this->auto($this->body);
        }

        if (IS_CRONTAB) {
            $controller = v\Req::controller();
            $data = "$controller\t{$this->status}\t{$this->body}";
            v\App::log($data, 'jobs.log');
        } else {
            // 类型响应
            $this->headers[] = ["{$_SERVER['SERVER_PROTOCOL']}: {$this->status}"];
            $type = $this->conf("types.{$this->type}");
            if (!empty($type)) {
                $this->headers[] = ["Content-Type: {$type}" . ($this->conf("chartypes.{$this->type}") ? "; charset={$this->charset}" : '')];
            }
            // 响应header
            foreach ($this->headers as $param) {
                call_user_func_array('header', $param);
            }

            // 格式化数据
            if (!empty($this->body)) {
                $packfn = 'pack' . ucfirst($this->type);
                if (method_exists($this, $packfn)) {
                    $this->body = $this->$packfn($this->body);
                }
            }

            if ($this->jsonpCallback)  // 响应JSONP
                $this->body = "{$this->jsonpCallback}({$this->body}, {$this->status})";

            // 输出数据
            echo $this->body;

            // 缓存数据，必须大于60秒，实际缓存时间比前端缓存时间少10秒
            if ($this->cacheAge >= 60 && v\Req::method() === 'GET' && v\Req::isFriendly() && !v\App::debug()) {
                $path = v\Req::path() . '.' . v\Req::type();
                v\PCache::set($path, $this->body, $this->cacheAge - 10);
            }
        }
        exit;
    }

    /**
     * 重定向
     * @param string $url
     * @param int $status
     */
    public function redirect($url, $status = 302) {
        $this->body = '';
        $this->header("Location: $url")->end($status);
    }

    /**
     * 开始输出文件流
     * @param string $filename
     */
    public function stream($filename) {
        ob_clean();
        $this->isStream = true;
        $type = $this->conf("types.{$this->type}");
        if (!empty($type))
            header("Content-Type: {$type}" . ($this->conf("chartypes.{$this->type}") ? "; charset={$this->charset}" : ''));
        header("{$_SERVER['SERVER_PROTOCOL']} 200 OK");
        header('Cache-Control:must-revalidate,post-check=0,pre-check=0');
        header('Expires:0');
        header('Pragma:public');
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");
        header("Content-Disposition: attachment; filename=$filename");
        header("Content-Transfer-Encoding: binary ");
    }

}
