<?php


namespace v\kit;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

class Sms extends v\ServiceFactory {

    /**
     * 服务提供对象名
     * 必须子类定义
     * @var string
     */
    protected static $objname = 'v\kit\SmsService';

    /**
     * 服务提供对象
     * 必须子类定义
     * @var object
     */
    protected static $object;

}

class SmsService extends v\Service {


    const API_URL = 'http://sms.snqu.com/sms.json'; // 请求地址

    const SMS_CAPTCHA  = 1;//验证码类
    const SMS_NOTICE  = 2;//公告通知类
    const SMS_AD  = 4;//推广短信类
    const SMS_VOICE  = 8;//语音通知类


    protected static $configs = [
        'appid' => '',
        'appkey' => ''
    ];

    /**
     * 配置
     * @var array|mixed
     */
    private $conf = [];


    /**
     * 短信类型:短信通知(方便短信中心做统计)
     * @var int
     */
    protected $sms_type = self::SMS_NOTICE;


    /**
     * 自动初始化配置
     * global.php 写入

     'v\kit\Sms' => [
    'appid' => 'foo',
    'appkey' => 'foo',
    ]

     */
    public function __construct() {
        //初始化配置
        $this->conf = $this->conf();
        //设置超时
        v\kit\Curl::setTimeout(10);
    }


    /**
     * 是否设置短信类型,默认为短信通知
     * @param $type
     * @return $this
     */
    public function setType($type){
        //转换类型
        $this->sms_type = $type;
        return $this;
    }

    /**
     * @param string $phone 手机号码
     * @param string $content 短信内容
     * @param string $appkey
     * @return mixed
     */
    public function send($phone,$content){

        //编制url
        $url = http_build_query([
            'appid'=>array_value($this->conf,'appid'),
            'phone'=>$phone,
            'sms_type'=>$this->sms_type,
            'content'=>$content
        ]);

        //签名
        $url = v\kit\UrlSign::setKey(array_value($this->conf,'appkey'))->sign(self::API_URL."?$url");

        //请求
        $rs = v\kit\Curl::xhr('get',$url) ;

        if (!$rs){
            v\Err::add(['time'=>'ERR_CONNECTION_TIMED_OUT']);
            return false;
        }

        $rs = json_decode($rs,true);

        //返回错误提示
        if (array_value($rs,'errno.0')) {
            v\Err::add($rs['message']);
            return false;
        }

        return true;
    }






}
