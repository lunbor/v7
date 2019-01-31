<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/9/6
 * Time: 12:24
 *
 * 汇率查询服务
 *
 */

namespace v\kit;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

class Forex extends v\ServiceFactory {

    /**
     * 服务提供对象名
     * 必须子类定义
     * @var string
     */
    protected static $objname = 'v\kit\ForexService';

    /**
     * 服务提供对象
     * 必须子类定义
     * @var object
     */
    protected static $object;

}

class ForexService extends v\Service {

    /**
     * 从hexun获取汇率
     * @param string $code
     * @return int
     */
    public function fromHexun($code = 'usdcny') {
        $code = 'FOREX' . strtoupper($code);
        $time = NOW_TIME;
        $url = "http://webforex.hermes.hexun.com/forex/quotelist?code=$code&column=code,price&callback=ongetjsonpforex&_=$time";
        try {
            $con = file_get_contents($url);
        } catch (\Exception $e) {
            $con = null;
        }
        switch ($code) {
            case 'FOREXUSDCNY':
                $rate = 685;
                break;
        }
        if (!empty($con)) {
            $con = trim(strtr($con, ['ongetjsonpforex' => '', '(' => '', ')' => '', ';' => '']));
            $data = json_decode($con, true);
            $rate = array_value($data, 'Data.0.0.1', 0);
            $rate = intval($rate) / 100;
        }
        if (empty($rate)) {
            throw new v\Exception('Get forex rate from hexun has error');
        }
        return $rate;
    }

}
