<?php

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));
/*
 * v framework
 * 公共函数
 *
 * @copyright knyon.com
 * @author knyon <knyon@qq.com>
 * @version SVN: $Id: Func.php 13724 2016-05-25 03:07:22Z wangyong $
 */

/**
 * 交换数值
 *
 * @param mixed $a 值1
 * @param mixed $b 值2
 */
function swap(&$a, &$b) {
    $temp = $a;
    $a = $b;
    $b = $temp;
}

/**
 * 生成12位唯一ID，到2075年有效
 * 默认会按顺序生成
 *
 * @param string $prev_char 前缀字符，默认为a-z的随机数
 * @return string
 */
function uniqid12($prev_char = '') {
    // 距2015年的微秒, 60年范围内
    $nowmics = explode(' ', microtime());
    $sec = substr('00' . base_convert(substr(($nowmics [1] - 1420041600), -9), 10, 36), -6);
    $msec = substr('00' . base_convert(substr($nowmics [0], 2, -2), 10, 36), -4);
    // 最后一位随机产生
    return $prev_char . $sec . $msec . base_convert(mt_rand(0, 35), 10, 36) . base_convert(mt_rand(0, 35), 10, 36);
}

/**
 * 生成16位唯一ID，到2075年有效
 * 默认会按顺序生成
 *
 * @param string $prev_char 前缀字符，默认为a-z的随机数
 * @return string
 */
function uniqid16($prev_char = '') {
    // 距2015年的微秒, 60年范围内
    $nowmics = explode(' ', microtime());
    $sec = substr('00' . base_convert(substr(($nowmics[1] - 1420041600), -9), 10, 36), -6);  // 秒
    $msec = substr('00' . base_convert(substr($nowmics[0], 2, -2), 10, 36), -4); // 微妙
    $mem = substr('00' . base_convert(memory_get_usage(), 10, 36), -4); // 内存用量
    // 最后两位随机产生
    return $prev_char . $sec . $msec . $mem . base_convert(mt_rand(0, 35), 10, 36) . base_convert(mt_rand(0, 35), 10, 36);
}

/**
 * 去除字符串左边的子字符串
 *
 * @param string $string 字符串
 * @param string $trim_string 要去除的子字符串
 * @return string
 */
function ltrim_string($string, $trim_string) {
    return preg_replace('/^' . preg_quote($trim_string, '/') . '/i', '', $string);
}

/**
 * 去除字符串右边的子字符串
 *
 * @param string $string 字符串
 * @param string $trim_string 要去除的子字符串
 * @return string
 */
function rtrim_string($string, $trim_string) {
    return preg_replace('/' . preg_quote($trim_string, '/') . '$/i', '', $string);
}

/**
 * 调用函数
 *
 * @param string|array $fun
 * @param array $objs 函数所在的对象
 * @example $fun
 *          [$obj, 'methodname'], 'functionname, param1, param2...', ['functionname', params1, params2], [[$obj, 'methodname'], params1, params2]
 * @return mixed
 */
function call_function($fun, $objs = []) {
    $args = [];
    if (is_string($fun)) {
        $fun = [$fun];
    }
    if (is_array($fun) && !is_object($fun [0])) {
        $args = $fun;
        unset($args [0]);
        if (is_string($fun [0])) {
            $funs = explode(',', strtr($fun [0], [';' => ',', ' ' => '']));
            $fun = $funs [0];
            unset($funs [0]);
            $args = array_merge($args, $funs);
        }
    }
    if (is_string($fun)) {
        foreach ($objs as $obj) {
            if (method_exists($obj, $fun)) {
                $fun = [$obj, $fun];
                break;
            }
        }
    }
    return call_user_func_array($fun, $args);
}

/**
 * 字符串去html标签与首尾空格
 * 对用户的数据使用该函数，防止xss攻击
 *
 * @param string $string 字符串
 * @param string $allow 允许的标签
 * @return string
 */
function str_striptrim($string, $allow = null) {
    $string = strval($string);
    $string = strip_tags($string, $allow);
    $string = htmlentities(trim($string));
    return $string;
}

/**
 * 去除标点符号与标签空格
 *
 * @param string $string 字符串
 * @return string
 */
function str_stripmark($string) {
    $string = trim(strval($string));
    if ($string !== '') {
        $string = preg_replace("/[[:punct:]\s]/", ' ', $string);
        $string = urlencode($string);
        $string = preg_replace("/(%7E|%60|%21|%40|%23|%24|%25|%5E|%26|%27|%2A|%28|%29|%2B|%7C|%5C|%3D|\-|_|%5B|%5D|%7D|%7B|%3B|%22|%3A|%3F|%3E|%3C|%2C|\.|%2F|%A3%BF|%A1%B7|%A1%B6|%A1%A2|%A1%A3|%A3%AC|%7D|%A1%B0|%A3%BA|%A3%BB|%A1%AE|%A1%AF|%A1%B1|%A3%FC|%A3%BD|%A1%AA|%A3%A9|%A3%A8|%A1%AD|%A3%A4|%A1%A4|%A3%A1|%E3%80%82|%EF%BC%81|%EF%BC%8C|%EF%BC%9B|%EF%BC%9F|%EF%BC%9A|%E3%80%81|%E2%80%A6%E2%80%A6|%E2%80%9D|%E2%80%9C|%E2%80%98|%E2%80%99|%EF%BD%9E|%EF%BC%8E|%EF%BC%88)+/", ' ', $string);
        $string = str_striptrim(urldecode($string));
    }
    return $string;
}

/**
 * 去除空格与标签
 * 只允许3层
 *
 * @param string|array $value 字符串或字符串组成的数组
 * @param string $allow 允许的标签
 * @return string
 */
function striptrim($value, $allow = null) {
    if (!is_array($value)) {
        $value = str_striptrim($value, $allow);
    } else {
        foreach ($value as &$v) {
            if (!is_array($v)) {
                $v = str_striptrim($v, $allow);
            } else {
                foreach ($v as &$v1) {
                    $v1 = str_striptrim($v1, $allow);
                }
            }
        }
    }
    return $value;
}

/**
 * 去除标点符号、标签、空格
 * 只允许3层
 *
 * @param string|array $value 字符串或字符串组成的数组
 * @return string
 */
function stripmark($value) {
    if (!is_array($value)) {
        $value = str_stripmark($value);
    } else {
        foreach ($value as &$v) {
            if (!is_array($v)) {
                $v = str_stripmark($v);
            } else {
                foreach ($v as &$v1) {
                    $v1 = str_stripmark($v1);
                }
            }
        }
    }
    return $value;
}

/**
 * 变量转换成数据
 * @param string|array $string
 * @param string $spliter 分割字符，逗号必定会被分割，默认分号也会被分割
 * @return array
 */
function arrayval($var, $spliter = ';') {
    if (empty($var))
        return [];
    switch (gettype($var)) {
        case 'string':
            $var = strtr($var, [', ' => ',', "$spliter, " => ',', "$spliter" => ',']);
            $var = explode(',', trim($var, ', '));
            break;
        case 'array':
            $var = array_values($var);
            break;
        default :
            $var = [$var];
    }
    return $var;
}

/**
 * 删除数据元素并返回删除的值
 * @param array $array
 * @param string | array $key
 * @return mixed 数组返回数组，字符串返回字符串
 */
function array_delete(&$array, $key) {
    if (is_array($key)) {
        $value = [];
        foreach ($key as $k) {
            $value[$k] = array_delete($array, $k);
        }
    } else {
        $value = null;
        if ($pos = strpos($key, '.')) {
            $pkey = substr($key, 0, $pos);
            $key = substr($key, $pos + 1);
            if (isset($array[$pkey]))
                return array_delete($array[$pkey], $key);
        } elseif (isset($array[$key])) {
            $value = $array[$key];
            unset($array[$key]);
        }
    }
    return $value;
}

/**
 * 删除数组中某几列的值
 *
 * @param array $array 数组
 * @param string|array $keys 列键，多个可用逗号隔开
 * @return array
 */
function array_unset(&$array, $keys) {
    if (is_string($keys))
        $keys = explode(',', $keys);
    $keys = array_flip($keys);
    $array = array_diff_key($array, $keys);
    return $array;
}

/**
 * array merge extend
 * 深度扩展合并数组
 *
 * @param array $array 数组1
 * @param array $array1 数组2
 * @param boolean $override 如果为false，当对应数据为空时才覆盖
 * @return array
 */
function array_extend(&$array, $array1, $override = true) {
    foreach ($array1 as $key => $value) {
        if (isset($array[$key]) && (is_array($array[$key]) && !isset($array[$key][0])) && is_array($value)) {
            array_extend($array[$key], $value);
        } elseif ($override || !isset($array[$key])) {
            $array [$key] = $value;
        }
    }
    return $array;
}

/**
 * 根据字符键获取多维数组中的值
 * 如array_value(array, 's.2')
 *
 * @param array $array 数组
 * @param string $key 键
 * @param mixed $default 默认值
 * @return mixed
 */
function array_value($array, $key, $default = null) {
    if (strpos($key, '.')) {
        $keys = explode('.', $key);
        $data = $array;
        foreach ($keys as &$key) {
            if (isset($data [$key])) {
                $data = $data [$key];
            } else {
                return $default;
            }
        }
        return $data;
    }
    return isset($array [$key]) && $array [$key] !== '' ? $array [$key] : $default;
}

/**
 * 设置多维数组中的值
 * 如array_setval('s.2', 'good')
 *
 * @param array $array 数组
 * @param string $key 键
 * @param mixed $value 值
 * @return array
 */
function array_setval(&$array, $key, $value) {
    if (!($pos = strpos($key, '.'))) {
        $array[$key] = $value;
    } else {
        $pkey = substr($key, 0, $pos);
        $key = substr($key, $pos + 1);
        if (!isset($array[$pkey]))
            $array[$pkey] = [];
        array_setval($array[$pkey], $key, $value);
    }
    return $array;
}

/**
 * 数组KEY重命名
 * @param array $array
 * @param array $keys
 * @return array
 */
function array_rekey(&$array, $keys) {
    foreach ($keys as $key => $keyNew) {
        if (isset($array[$key])) {
            $array[$keyNew] = $array[$key];
            unset($array[$key]);
        }
    }
    return $array;
}

/**
 * 二位数组重命名列
 * @param array $array
 * @param array $keys
 * @return array
 */
function array_column_rekey(&$array, $keys) {
    if (!array_is_column($array)) {
        array_rekey($array, $keys);
    } else {
        foreach ($array as &$item) {
            array_rekey($item, $keys);
        }
    }
    return $array;
}

/**
 * 是否数组列表
 * 必须下标从0开始
 * @param $array
 * @return bool
 */
function array_is_column($array) {
    return isset($array[0]) && is_array($array[0]);
}

/**
 * 将二维数组按照指定的字段进行升序排序
 * @param array $data 排序的数组
 * @param string $key 排序的字段
 * @param int|float|string $default 排序字段值不存在时，默认值
 * @return array
 */
function array_column_asort(&$data, $key, $default = 0) {
    if (!empty($data)) {
        $values = [];
        foreach ($data as $v) {
            $values[] = isset($v[$key]) ? $v[$key] : $default;
        }
        array_multisort($values, SORT_ASC, $data);
    }
    return $data;
}

/**
 * 将二维数组按照指定的字段进行降序排序
 * @param array $data 排序的数组
 * @param string $key 排序的字段
 * @param int|float|string $default 排序字段值不存在时，默认值
 * @return array
 */
function array_column_arsort(&$data, $key, $default = 0) {
    if (!empty($data)) {
        $values = [];
        foreach ($data as $v) {
            $values[] = isset($v[$key]) ? $v[$key] : $default;
        }
        array_multisort($values, SORT_DESC, $data);
    }
    return $data;
}

/**
 * 把二维数组某一列作为key键
 * @param array $array 数组
 * @param string $key 数组要作为key键所在列的键
 * @return array
 */
function array_column_askey(&$array, $key) {
    $data = array();
    foreach ($array as $row) {
        $data [$row [$key]] = $row;
    }
    $array = $data;
    return $array;
}

/**
 * 取得二维数组的key/value组成的options值
 * @param array $array
 * @param string $fieldVal
 * @param string $fieldID
 * @return array
 */
function array_column_keyval($array, $fieldVal, $fieldID = '_id') {
    $data = [];
    foreach ($array as $item) {
        $data[$item[$fieldID]] = isset($item[$fieldVal]) ? $item[$fieldVal] : '';
    }
    return $data;
}

/**
 * 删除数组中某几列的值
 *
 * @param array $array 数组
 * @param string|array $keys 列键，多个可用逗号隔开
 * @return array
 */
function array_column_unset(&$array, $keys) {
    if (is_string($keys))
        $keys = explode(',', $keys);
    $keys = array_flip($keys);

    if (!is_array(reset($array))) {
        $array = array_diff_key($array, $keys);
    } else {
        foreach ($array as $k => $row) {
            $array [$k] = array_diff_key($row, $keys);
        }
    }
    return $array;
}

/**
 * 过滤出数组中某几列的值
 *
 * @param array $array
 * @param $keys
 * @return array
 */
function array_column_filter(&$array, $keys) {
    $keys = array_flip(arrayval($keys));

    if (!array_is_column($array)) {
        $array = array_intersect_key($array, $keys);
    } else {
        foreach ($array as $k => $row) {
            $array [$k] = array_intersect_key($row, $keys);
        }
    }
    return $array;
}

/**
 * 过滤掉数组的空值
 * @param array $array
 */
function array_filter_null(&$array) {
    foreach ($array as $k => $val) {
        if (empty($val) && $val !== 0 && $val !== '0')
            unset($array[$k]);
    }
    return $array;
}

/**
 * 删除两个数组相同的值
 *
 * @param array $array 被过滤的数组
 * @param array $array1 须比较的值
 * @return array
 */
function array_unset_same(&$array, $array1) {
    foreach ($array as $k => $v) {
        if (isset($array1 [$k]) && $v === $array1 [$k])
            unset($array [$k]);
    }
    return $array;
}

/**
 * 按key取得数组中的数据
 * @param array $array
 * @param array | string $keys
 * @return array
 */
function array_filter_key($array, $keys) {
    $keys = array_flip(arrayval($keys));
    return array_intersect_key($array, $keys);
}

/* * 是否为索引数组
 * @param $array
 * @return bool
 */

function is_assoc($array) {
    if (is_array($array)) {
        $keys = array_keys($array);
        return $keys != array_keys($keys);
    }
    return false;
}

/**
 * 判断是否绝对路径
 *
 * @param string $path 路径
 * @return boolean
 */
function is_absolute_path($path) {
    return substr($path, 0, 1) === '/' || substr($path, 1, 1) === ':';
}

/**
 * 写入文件，如果没有该目录则创建该目录
 *
 * @param string $filename 文件名
 * @param string $data 文件内容
 * @param int $flags 写入方式，参考file_put_contents flags
 * @return boolean
 */
function file_put_autodir($filename, $data, $flags) {
    $pathname = dirname($filename); // 先建立目录
    is_dir($pathname) or @ mkdir($pathname, 0777, true);
    $rs = file_put_contents($filename, $data, $flags);
    return $rs;
}

/**
 * url安全base64_encode
 *
 * @param string $string 字符串
 * @return string
 */
function base64url_encode($string) {
    return rtrim(strtr(base64_encode($string), '+/', '-_'), '=');
}

/**
 * url安全base64_decode
 *
 * @param string $string 字符串
 * @return string
 */
function base64url_decode($string) {
    return base64_decode(str_pad(strtr($string, '-_', '+/'), ceil(strlen($string) / 4) * 4, '=', STR_PAD_RIGHT));
}

/**
 * urldecode数据
 *
 * @param string|array $value 字符串或数组
 * @return mixed
 */
function url_decode($value) {
    if (is_array($value)) {
        foreach ($value as &$v) {
            $v = url_decode($v);
        }
    } else {
        $value = urldecode($value);
    }
    return $value;
}

/**
 * 生成16位MD5
 * @param string $str
 * @return string
 */
function md516($str) {
    return substr(md5($str), 8, 16);
}

/**
 * 生成无符号crc32
 * 只能用于校验，不能作为唯一使用，碰撞几率1/10000
 * @param string $str
 * @return string
 */
function crc32u($str) {
    $val = crc32($str);
    return sprintf("%u\n", $val);
}

/**
 * 获取变量值
 * 支持模板作为默认值
 * @param string $name
 * @param string $default
 * @return string
 */
function view($name, $default = '') {
    return v\View::data($name, $default);
}
