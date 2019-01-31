<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/9/6
 * Time: 12:24
 *
 * 加密服务
 * 支持aes加密与16位md5加密
 *
 */

namespace v\kit;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

class Mcrypt extends v\ServiceFactory {

    /**
     * 服务提供对象名
     * 必须子类定义
     * @var string
     */
    protected static $objname = 'v\kit\McryptService';

    /**
     * 服务提供对象
     * 必须子类定义
     * @var object
     */
    protected static $object;

}

class McryptService extends v\Service {

    /**
     * 密钥字符
     * @var array 
     */
    protected $chars = [
        '1', '2', '3', '4', '5', '6', '7', '8', '9', '0',
        'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z',
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
        '~', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '-', '_', '=', '+',
        '.', '?', '/', ",", "'", '"', ':', ';', '|', '[', ']', '{', '}'
    ];

    /**
     * 签名与aes密钥，16位
     * @var string
     */
    protected $ekey = '#QvLABlM/?1z3!b#';

    /**
     * 设置KEY
     * @param string $key
     * @return \v\ext\McryptSvc
     */
    public function setKey($key) {
        // key只允许16位
        $this->ekey = substr("{$key}#QvLABlM/?1z3!b#", 0, 16);
        return $this;
    }

    /**
     * AESCBC加密，自动生成16位向量
     * @param string $str
     * @return string
     */
    public function enAesCBC($str) {
        $cipher = 'AES-128-CBC';
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        // 向量 + 密文
        $code = $iv . openssl_encrypt($str, $cipher, $this->ekey, OPENSSL_RAW_DATA, $iv);
        $code = base64url_encode($code);
        return $code;
    }

    /**
     * AESCBC解密
     * @param string $code
     * @return string
     */
    public function deAesCBC($code) {
        $code = base64url_decode($code);
        $cipher = 'AES-128-CBC';
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = substr($code, 0, $ivlen);
        if(strlen($iv) !== $ivlen)  return '';//fix因为cooike字段加密异常导致的报错爆路径问题
        $code = substr($code, $ivlen);
        $str = openssl_decrypt($code, $cipher, $this->ekey, OPENSSL_RAW_DATA, $iv);
        $str = trim($str);  // 因为会空格填充，所以解密后必须trim掉空格
        return $str;
    }

    /**
     * AESCBC加密,16进制，自动生成16位向量
     * @param string $str
     * @return string
     */
    public function enAesCBC16($str) {
        $cipher = 'AES-128-CBC';
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        // 向量 + 密文
        $code = $iv . openssl_encrypt($str, $cipher, $this->ekey, OPENSSL_ZERO_PADDING, $iv);
        return unpack("H*", $code)[1];
    }

    /**
     * AESCBC解密，16进制，自动生成16位向量
     * @param string $str
     * @return string
     */
    public function deAesCBC16($str) {
        $code = pack("H*", $str);
        $cipher = 'AES-128-CBC';
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = substr($code, 0, $ivlen);
        $code = substr($code, $ivlen);
        $str = openssl_decrypt($code, $cipher, $this->ekey, OPENSSL_ZERO_PADDING, $iv);
        $str = trim($str);  // 因为会空格填充，所以解密后必须trim掉空格
        return $str;
    }

    /**
     * 生成16位MD5
     * @param string $str
     * @return string
     */
    public function md516($str) {
        return substr(md5($str), 8, 16);
    }

    /**
     * 生成密钥
     * @param int $min_length 最小长度
     * @param int $max_length 最大长度
     * @return string
     */
    public function randKey($min_length = 16, $max_length = 0) {
        $pwdlen = $max_length <= $max_length ? $min_length : rand($min_length, $max_length);
        $strlen = count($this->chars) - 1;
        $pwdstr = '';
        for ($i = 1; $i <= $pwdlen; $i++) {
            $pos = rand(0, $strlen);
            $pwdstr .= $this->chars[$pos];
        }
        return $pwdstr;
    }

}
