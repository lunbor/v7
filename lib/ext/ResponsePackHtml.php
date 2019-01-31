<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/9/8
 * Time: 10:31
 */

namespace v\ext;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

trait ResponsePackHtml {

    /**
     * JS mini
     * @param string $string
     * @return string
     */
    protected function packJs($string) {
        if (!v\App::debug()) {
            if (strpos($string, "\n") !== false) {
                v::load('third/minify/JSMin.php');
                $string = \JSMin::minify($string);
            }
        }
        return $string;
    }

    /**
     * css 解析
     * 支持 less 与 scss
     * @param string $string
     * @return string
     */
    protected function packCss($string) {
        if (!v\App::debug()) {
            if (strpos($string, "\n") !== false) {
                v::load('third/minify/CSSmin.php');
                $packer = new \CSSmin();
                $string = $packer->run($string);
            }
        }
        return $string;
    }

    /**
     * markdown格式解析
     * @param string $string
     * @return string
     */
    protected function packMd($string) {
        v::load('third/Parsedown.php');
        $parsedown = new \Parsedown();
        $string = $parsedown->text($string);
        return '<html><body>' . $string . '</body></html>';
    }

    /**
     * css less 解析
     * 不解析@import
     * @param string $string
     * @return string
     */
    protected function packCssLess($string) {
        v::load('third/lessc.inc.php');
        $less = new \lessc();
        if (!v\App::debug())
            $less->setFormatter('compressed');
        return $less->compile($string);
    }

    /**
     * css scss 解析
     * 不解析@import
     * @param string $string
     * @return string
     */
    protected function packCssScss($string) {
        v::load('third/scssphp/scss.inc.php');
        $scss = new \Leafo\ScssPhp\Compiler();
        if (!v\App::debug())
            $scss->setFormatter('Leafo\ScssPhp\Formatter\Compressed');
        return $scss->compile($string);
    }

}
