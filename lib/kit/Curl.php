<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/9/6
 * Time: 12:24
 *
 * Curl远程数据获取服务
 *
 */

namespace v\kit;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

class Curl extends v\ServiceFactory
{

    /**
     * 服务提供对象名
     * 必须子类定义
     * @var string
     */
    protected static $objname = 'v\kit\CurlService';

    /**
     * 服务提供对象
     * 必须子类定义
     * @var object
     */
    protected static $object;

}

class CurlService extends v\Service
{

    /**
     * 用户代理
     * @var string
     */
    protected $userAgent = '';

    /**
     * 超时秒
     * @var int
     */
    protected $timeoutSec = 10;

    /**
     * .json的请求自动解析成JSON格式
     * @var boolean
     */
    protected $decodeJson = false;

    /**
     * @var bool
     * 证书是否包含域名
     */
    protected $hasHost = false;

    /**
     * @var bool
     * 是否获取响应头
     */
    protected $getHeader = false;

    /**
     * @var bool
     * 是否获取响应头的setcookie的内容,如:['username=foo','token=foo']
     */
    protected $getCookie = false;

    /**cookie保存的文件
     * @var string
     */
    protected $cookieFile = '';

    /**求情头的cookie
     * @var string
     */
    protected $cookie = '';

    /**
     * @var bool
     * 是否不要响应正文
     */
    protected $noBody = false;

    /**是否跟踪302跳转
     * @var bool
     */
    protected $notFollow = false;

    /**是否获取302跳转的地址
     * @var bool
     */
    protected $getRedirectUrl = false;

    protected $reset = true;

    /**
     * header头
     * @var array
     */
    protected $header = [];

    /**
     * 初始化代理
     */
    public function __construct()
    {
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $this->userAgent = $_SERVER['HTTP_USER_AGENT'];
        }
    }

    /**
     * 设置超时
     * @param int $sec
     * @return $this;
     */
    public function setTimeout($sec)
    {
        $this->timeoutSec = $sec;
        return $this;
    }

    /**
     * 设置useragent
     * @param string $str
     * @return $this;
     */
    public function setUserAgent($str)
    {
        $this->userAgent = $str;
        return $this;
    }

    /**
     * 设置头部参数
     * @param array $value
     * @return $this
     */
    public function setHeader($value)
    {
        $header = $value;
        if (is_array($value) && !isset($value[0])) {
            $header = [];
            foreach ($value as $k => $v) {
                $header[] = "$k:$v";
            }
        }
        $this->header = $header;
        return $this;
    }

    /**带上cookie请求
     * @param $value
     */
    public function setCookie($value){
        $this->cookie = $value;
        return $this;
    }
    /**存放cookie
     * @param $fileName
     * @return $this
     */
    public function saveCookie($fileName){
        $this->cookieFile = $fileName;
        return $this;
    }

    /**
     * 设置json是否自动解码
     * @param string $boolean
     * @return $this;
     */
    public function decodeJson($boolean)
    {
        $this->decodeJson = $boolean;
        return $this;
    }

    /**
     * @param array $option
     * 设置检查证书域名等
     * 如: ['hasHost','getHeader']
     */
    public function setOptions(array $options)
    {
        foreach ($options as $option) {
            $this->$option = true;
        }
        return $this;
    }

    /**
     *
     * @param string $method
     * @param string $url
     * @param array|string $data
     * @param function $backfn
     * @return string
     */
    public function xhr($method, $url, $data = null, $backfn = null)
    {

        if (is_callable($data))
            list($data,$backfn) = [$backfn,$data];

        $method = strtoupper($method);

        // GET DELETE请求
        if (!empty($data) && ($method === 'GET' || $method === 'DELETE')) {
            $data = (is_array($data) ? http_build_query($data) : $data);
            $url .= (strpos($url, '?') ? '&' : '?') . $data;
            $data = null;
        }


        $ch = curl_init();
        if (!empty($this->userAgent))
            curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeoutSec);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        //带上cookie访问
        if (!empty($this->cookie)) {
            curl_setopt($ch, CURLOPT_COOKIE, $this->cookie);
        }

        if ($this->cookieFile !== '' ) {
            $path = APP_ROOT .DIRECTORY_SEPARATOR. $this->cookieFile;
            curl_setopt($ch, CURLOPT_COOKIEJAR, $path);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $path);
        }
        //要获取cookie,先得有header
        if ($this->getCookie)
            $this->getHeader = true;
        // 返回 response_header,如果不为 true, 只会获得响应的正文
        curl_setopt($ch, CURLOPT_HEADER, $this->getHeader);
        // 为了节省带宽及时间,要头就不要正文了
        curl_setopt($ch, CURLOPT_NOBODY, $this->noBody);
        if (!empty($this->header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->header);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);

        // POST PUT数据
        if (!empty($data)) {
            $data = (is_array($data) ? http_build_query($data) : $data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        if (strpos($url, 'https://') === 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // 跳过证书检查
        }
        if ($this->hasHost)
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // 检查证书中是否设置域名,第三参数必须是2

        // 使用自动跳转(注意使用自动follow可能存在一定的问题,要想完全模拟真实的请求,请使用getRedirect来做)
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, !$this->notFollow);

        $content = curl_exec($ch);

        $info = curl_getinfo($ch);

        //只要setCookie的信息
        if ($this->getCookie) {
            preg_match_all('/Set-Cookie:\s(.*);/iU', $content, $matches); //匹配头信息中的setCookie:什么什么的
            $content = array_value($matches, '1'); //获得COOKIE
        } elseif ($this->getHeader) {
            $content = explode("\n", trim($content));
        }

        //是否获取302跳转的目标地址
        if ($this->getRedirectUrl){
            $content['redirect_url'] = $info['redirect_url'];
        }

        curl_close($ch);

        //重置参数
        if ($this->reset){
            $this->hasHost =
            $this->getHeader =
            $this->getCookie =
            $this->notFollow =
            $this->noBody =
            $this->getRedirectUrl =
            $this->cookieFile =
            $this->cookie = '';
            $this->timeoutSec = 10;
            $this->header = [];
        }

        // 解析成json格式
        if ($info['http_code'] < 500 && $this->decodeJson && strpos($url, '.json') !== false) {
            $content = json_decode($content, true);
        }

        if (is_callable($backfn)) {
            return call_user_func($backfn, $content, $info['http_code']);
        }



        return $content;
    }


    /**是否初始化参数
     * @param $boolean
     * @return $this
     */
    public function reset($boolean)
    {
        if (!is_bool($boolean)){
            foreach ($boolean as $key => $b){
                $this->$key = $b;
            }
        }else{
            $this->reset = $boolean;
        }
        return $this;
    }

    /**返回类属性,作为调试程序用
     * @return array
     *
     */
    public function show (){
        return get_object_vars($this);
    }

}
