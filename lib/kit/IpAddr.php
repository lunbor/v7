<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/9/6
 * Time: 12:24
 *
 * IP地址转换服务
 *
 */
namespace v\kit;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));
use v;

class IpAddr extends v\ServiceFactory {

    /**
     * 服务提供对象名
     * 必须子类定义
     * @var string
     */
    protected static $objname = 'v\kit\IpAddrService';

    /**
     * 服务提供对象
     * 必须子类定义
     * @var object
     */
    protected static $object;


}

class IpAddrService extends v\Service {

    /**
     * 从IP138获取IP地址
     * @param string $ip
     * @return string
     */
    public function fromIp138($ip) {
        $url = "http://www.ip138.com/ips138.asp?ip=$ip";
        $con = file_get_contents($url);
        if (!empty($con) && preg_match('/<li>(.*?)<\/li>/', $con, $mathes)) {
            $addr = iconv('gb2312', 'utf-8', $mathes[1]);
            $addr = explode('：', explode(' ', $addr)[0])[1];
            return $addr;
        }
        return '';
    }

    /**
     * 从sina获取IP地址
     * @param string $ip
     * @return string
     */
    public function fromSina($ip) {
        $url = "http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=json&ip=$ip";
        $con = file_get_contents($url);
        if (!empty($con)) {
            $addr = json_decode($con, true);
            $addr = $addr['province'] . $addr['city'];
            return $addr;
        }
        return '';
    }

    /**
     * 从taobao获取IP地址
     * @param string $ip
     * @return string
     */
    public function fromTaobao($ip) {
        $url = "http://ip.taobao.com/service/getIpInfo.php?ip=$ip";
        $con = file_get_contents($url);
        if (!empty($con)) {
            $addr = json_decode($con, true);
            if ($addr['code'] == 0) {
                $addr = $addr['data'];
                $addr = $addr['region'] . $addr['city'];
                return $addr;
            }
        }
        return '';
    }


}