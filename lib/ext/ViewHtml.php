<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/9/8
 * Time: 10:31
 * 
 * html视图处理
 * 
 */

namespace v\ext;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

define('vViewPositionHEAD', 0);
define('vViewPositionFOOT', 1);
define('vViewPositionFIRST', 2);

trait ViewHtml {

    /**
     * 模板JS
     * @var array
     */
    protected $js = [];

    /**
     * 模板css
     * @var array
     */
    protected $css = [];

    /**
     * 模板title
     * @var array
     */
    protected $title = [];

    /**
     * 关键字
     * @var array
     */
    protected $keywords = [];

    /**
     * 描述
     * @var array
     */
    protected $description = [];

    /**
     * 页面title设置|获取
     * @param string $title
     * @return self
     */
    public function title($title = null) {
        if (is_null($title)) {
            if (empty($this->title)) {
                return v\App::conf('sysname', 'v7');
            }
            return implode('_', $this->title) . '_' . v\App::conf('sysname', 'v7');
        }
        if (is_string($title)) {
            $title = explode(',', strtr($title, [' ' => '', ';' => ',']));
        }
        $this->title = array_merge($this->title, $title);
        return $this;
    }

    /**
     * 关键字
     * @param string $keyword
     * @return self
     */
    public function keywords($keyword = null) {
        if (is_null($keyword)) {
            return implode(',', $this->keywords);
        }
        array_push($this->keywords, $keyword);
        return $this;
    }

    /**
     * 描述
     * @param string $descr
     * @return self
     */
    public function description($descr = null) {
        if (is_null($descr)) {
            return implode(',', $this->description);
        }
        array_push($this->description, $descr);
        return $this;
    }

    /**
     * 载入js
     * @param string $file
     * @param int $position
     * @return self
     */
    public function loadJs($file, $position = 0) {
        $file = ltrim($file, '/');
        $js = v\App::link("/$file", true, true);
        switch ($position) {
            case 1:
                $this->js[$js] = 1;
                break;
            case 2:
                $this->js = array_merge([$js => 0], $this->js);
                break;
            default:
                $this->js[$js] = 0;
        }
        return $this;
    }

    /**
     * 载入css
     * @param string $file
     * @param int $position
     */
    public function loadCss($file, $position = 0) {
        $file = ltrim($file, '/');
        $key = md5($file);
        if (!isset($this->css[$key])) {
            $this->css[$key] = v\App::link("/$file", true, true);
        }
        if ($position) {
            $css = $this->css[$key];
            $this->css = array_merge([$key => $css], $this->css);
        }
        return $this;
    }

    /**
     * 生成JS标签
     * @return string
     */
    public function htmlJs($position = 0) {
        $jss = [];
        foreach ($this->js as $js => $pos) {
            if ($pos == $position) {
                $dir = dirname($js);
                if (!isset($jss[$dir]))
                    $jss[$dir] = [];
                $jss[$dir][] = basename($js);
            }
        }
        $jstr = '';
        foreach ($jss as $path => $js) {
            $jstr .= '<script type="text/javascript" src="' . "$path/" . strtr(implode('-', $js), ['.js-' => '-']) . '"></script>' . "\n";
        }
        return $jstr;
    }

    /**
     * 生成css标签
     * @return string
     */
    public function htmlCss() {
        $csss = [];
        foreach ($this->css as $css) {
            $dir = dirname($css);
            if (!isset($csss[$dir]))
                $csss[$dir] = [];
            $csss[$dir][] = basename($css);
        }
        $csstr = '';
        foreach ($csss as $path => $css) {
            $csstr .= '<link type="text/css" rel="stylesheet" href="' . "$path/" . strtr(implode('-', $css), ['.css-' => '-']) . '" />' . "\n";
        }
        return $csstr;
    }

    /**
     * 生成页面title
     * @return string
     */
    public function htmlTitle() {
        $html = '<title>' . $this->title() . '</title>';
        return $html;
    }

    /**
     * render html keywords
     * @return string
     */
    public function htmlKeywords() {
        $html = '<meta name="keywords" content="' . $this->keywords() . '" />';
        return $html;
    }

    /**
     * render html description
     * @return string
     */
    public function htmlDescription() {
        $html = '<meta name="description" content="' . $this->description() . '" />';
        return $html;
    }

    /**
     * 设置属性
     * @param array $options
     * @return string
     */
    public function htmlAttr($options) {
        $html = '';
        foreach ($options as $k => $v) {
            $html .= $k . '="' . $v . '" ';
        }
        return $html === '' ? '' : ' ' . $html;
    }

    /**
     * 绘制span标签
     * @param string $field 域名
     * @param string $text 文字
     * @param array $options 属性
     * @return string 
     */
    public function htmlLabel($field, $text, $options = []) {
        if (empty($options['name']))
            $options['name'] = $field;
        $html = '<span ' . $this->htmlAttr($options) . '>' . $text . '</span>';
        return $html;
    }

    /**
     * 绘制input文本输入框
     * @param string $field
     * @param array $options
     * @return string 
     */
    public function htmlText($field, $value = null, $options = []) {
        if (is_array($value))
            swap($value, $options);

        if (empty($options['name']))
            $options['name'] = $field;
        $options['value'] = is_null($value) ? $this->data($field, '') : $value;
        $html = '<input type="text" ' . $this->htmlAttr($options) . '/>';
        return $html;
    }

    /**
     * 绘制hidden
     * @param string $field 字段名
     * @param array $options html属性
     * @return string
     */
    public function htmlHidden($field, $value = null, $options = []) {
        if (is_array($value))
            swap($value, $options);

        if (empty($options['name']))
            $options['name'] = $field;
        $options['value'] = is_null($value) ? $this->data($field, '') : $value;
        $html = '<input type="hidden" ' . $this->htmlAttr($options) . '/>';
        return $html;
    }

    /**
     * 绘制hidden
     * @param array $options html属性
     * @return string
     */
    public function htmlCSRF($options = []) {
        $value = $this->controller->tokenCSRF();
        $field = $this->controller->conf('csrf_name');
        return $this->htmlHidden($field, $value, $options);
    }

    /**
     * 绘制按纽
     * @param string $field 字段名
     * @param string $text 按钮文本
     * @param array $options html属性
     * @return string 
     */
    public function htmlButton($field, $text, $options = []) {
        if (empty($options['name']))
            $options['name'] = $field;
        $html = '<button ' . $this->htmlAttr($options) . '><span>' . $text . '</span></button>';
        return $html;
    }

    /**
     * 绘制textarea
     * @param string $field 字段名
     * @param array $options html属性
     * @return string
     */
    public function htmlTextarea($field, $value = null, $options = []) {
        if (is_array($value))
            swap($value, $options);

        if (empty($options['name']))
            $options['name'] = $field;
        $html = '<textarea ' . $this->htmlAttr($options) . '>' . (is_null($value) ? $this->data($field, '') : $value) . '</textarea>';
        return $html;
    }

    /**
     * 绘制password
     * @param string $field 字段名
     * @param array $options html属性
     * @return string
     */
    public function htmlPassword($field, $value = null, $options = []) {
        if (is_array($value))
            swap($value, $options);

        if (empty($options['name']))
            $options['name'] = $field;
        $options['value'] = is_null($value) ? $this->data($field, '') : $value;
        $html = '<input type="password" ' . $this->htmlAttr($options) . ' />';
        return $html;
    }

    /**
     * 绘制file
     * @param string $field 字段名
     * @param array $options html属性
     * @return string
     */
    public function htmlFile($field, $options = []) {
        if (empty($options['name']))
            $options['name'] = $field;
        $html = '<input type="file" ' . $this->htmlAttr($options) . ' />';
        return $html;
    }

    /**
     * 绘制checkbox
     * 要求默认值为数组，字符串则 , 号隔开
     * @param string $field 字段名
     * @param array $items 选项，为值由显示text组成的键值对
     * @param array $options html属性
     * @return string
     */
    public function htmlCheckbox($field, $items, $checkedstr = null, $options = []) {
        if (is_array($checkedstr))
            swap($checkedstr, $options);

        $checkeds = is_null($checkedstr) ? $this->data($field, []) : $checkedstr;
        if (is_string($checkeds)) {
            $checkeds = explode(',', preg_replace('/\s*[,;]\s*/', ',', $checkeds));
        }

        // 多条生成数据格式名字，单条普通名字
        $name = array_value($options, 'name', $field);
        unset($options['name']);
        if (count($items) <= 1) {
            $namelock = 1;
            $options['name'] = $name;
        }

        $attrArray = ['style' => '', 'class' => ''];
        $attrs = array_intersect_key($options, $attrArray);
        $options = array_diff_key($options, $attrArray);

        $html = '';
        //$pos = 0;
        foreach ($items as $k => $v) {
            if (in_array($k, $checkeds))
                $options['checked'] = 'checked';
            else
                unset($options['checked']);
            $options['value'] = $k;
            if (!isset($namelock))
                $options['name'] = "{$name}[]";
            $html .= '<label' . $this->htmlAttr($attrs) . '><input type="checkbox"' . $this->htmlAttr($options) . ' /><i></i><span>' . $v . '</span></label>';
            //$pos++;
        }
        return $html;
    }

    /**
     * 绘制radio
     * @param string $field 字段名
     * @param array $items 选项，为值由显示text组成的键值对
     * @param array $options html属性
     * @return string
     */
    public function htmlRadio($field, $items, $selected = null, $options = []) {
        if (is_array($selected))
            swap($selected, $options);

        if (empty($options['name']))
            $options['name'] = $field;

        $attrArray = ['style' => '', 'class' => ''];
        $attrs = array_intersect_key($options, $attrArray);
        $options = array_diff_key($options, $attrArray);

        $selected = is_null($selected) ? $this->data($field, null) : $selected;
        $html = '';
        foreach ($items as $k => $v) {
            if ($selected !== null && $k == $selected)
                $options['checked'] = 'checked';
            else
                unset($options['checked']);
            $options['value'] = $k;
            $html .= '<label' . $this->htmlAttr($attrs) . '><input type="radio"' . $this->htmlAttr($options) . ' /><i></i><span>' . $v . '</span></label>';
        }
        return $html;
    }

    /**
     * 绘制select
     * @param string $field 字段名
     * @param array $items 选项，为值由显示text组成的键值对
     * @param array $options html属性
     * @return string
     */
    public function htmlSelect($field, $items, $selected = null, $options = []) {
        if (is_array($selected))
            swap($selected, $options);

        if (empty($options['name']))
            $options['name'] = $field;

        $selected = is_null($selected) ? $this->data($field, null) : $selected;
        $html = '<select ' . $this->htmlAttr($options) . '>';
        foreach ($items as $k => $v) {
            $selstr = $selected !== null && $k == $selected ? ' selected="selected"' : '';
            $html .= '<option value="' . $k . '"' . $selstr . '>' . $v . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    /**
     * 按位宽截取字符
     * @param string $str
     * @param init $width 一个汉字约两个位宽
     * @param string $fixed 要补上的后缀
     * @return string
     */
    public function htmlSubstr($str, $width = 0, $fixed = '') {
        $len = strlen($str);
        if ($width > 0 && $width < $len) {
            $width *= 10;
            $w = 0;
            $i = 0;
            while ($i < $len && $w < $width) {
                $c = ord($str[$i]);
                if ($c >= 240) {  // 4字节头
                    $w += 21;
                    $i += 4;
                } elseif ($c > 224) {  // 3字节头
                    $w += 21;
                    $i += 3;
                } elseif ($c > 192) {  // 2字节头
                    $w += 16;
                    $i += 2;
                } else {
                    $w += 11;
                    $i += 1;
                }
            }
            $str = substr($str, 0, $i);
            return $str . $fixed; //说明截取过了,补上fix
        }
        return $str;
    }

}
