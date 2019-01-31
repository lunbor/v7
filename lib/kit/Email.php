<?php


namespace v\kit;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

class Email extends v\ServiceFactory {

    /**
     * 服务提供对象名
     * 必须子类定义
     * @var string
     */
    protected static $objname = 'v\kit\EmailService';

    /**
     * 服务提供对象
     * 必须子类定义
     * @var object
     */
    protected static $object;

}

class EmailService extends v\Service {



    protected static $configs = [
        'from' => [],
        'smtp' => '',
        'port' => '',
        'username' => '',
        'password' => '',
        'charset' => '',
    ];

    /**
     * PHPMailer对象
     * @var null|\PHPMailer
     */
    protected $email_object = null;

    /**
     * 配置
     * @var array|mixed
     */
    private $conf = [];

    /**
     * 自动初始化配置
     * global.php 写入
     *
     * 'v\kit\Email' => [
     * 'from' => ['mail' => 'no-reply@snqu.com', 'name' => '您的项目名称'],
     * 'smtp' => 'smtp.ym.163.com',
     * 'port' => '25',
     * 'username' => 'foo',
     * 'password' => 'foo',
     * 'charset' => 'utf-8',
     * ]

     */
    public function __construct() {
        //载入
        v::load('third/PHPMailer/PHPMailerAutoload.php');
        //单列
        $this->email_object = $this->email_object ? : new \PHPMailer;
        //配置读取
        $this->conf = $this->conf();
    }


    /**
     * 发邮件的方法
     * @param $toEmail
     * @param $subject
     * @param $body
     * @return bool
     * 发送的邮件地址,题目,主体
     */
    public function send($toEmail, $subject, $body){

        $this->email_object->isSMTP(); // 设置使用SMTP服务器发送邮件
        $this->email_object->Host = array_value($this->conf,'smtp');  // 设置SMTP服务器地址
        $this->email_object->SMTPSecure = array_value($this->conf, 'SMTPSecure'); // 设置ssl加密方式
        $this->email_object->SMTPDebug = 0; // 开启 SMTP debug information
        $this->email_object->SMTPAuth = true;  // 使用SMTP的授权规则
        $this->email_object->Username = array_value($this->conf,'username'); // 要使用哪一个邮箱发邮件
        $this->email_object->Password = array_value($this->conf,'password'); //密码
        $this->email_object->Port = array_value($this->conf,'port'); // SMTP ssl协议的端口
        $this->email_object->CharSet = array_value($this->conf,'charset'); //编码
        $this->email_object->setFrom(array_value($this->conf,'from.mail'), array_value($this->conf,'from.name'));//设定邮件来自哪里,发件人名字叫什么
        $this->email_object->addAddress($toEmail); //设定目标邮件的地址
        $this->email_object->isHTML(true); // 表示发送的邮件内容以html的形式发送
        $this->email_object->Subject = $subject;
        $this->email_object->Body    = $body;
        return $this->email_object->send();
    }

    /**
     * 清除收件人
     * @author winson.wong<wangwx@snqu.com>
     * @return $this
     */
    public function clearAddresses() {
        $this->email_object->clearAddresses();
        return $this;
    }
}
