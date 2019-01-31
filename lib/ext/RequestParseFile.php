<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/10/21
 * Time: 10:31
 * 
 * 请求静态文件解析
 * 
 */

namespace v\ext;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

trait RequestParseFile {

    /**
     * 解析图片文件
     * @param string $file
     * @return string
     */
    protected function parseImage($file) {
        $pathinfo = pathinfo($file);
        if (preg_match('/_\d+x\d+(x\d)?$/', $pathinfo['filename'], $mathes)) {
            $path = substr($file, 0, strrpos($file, '_'));
            $filename = v\App::absolutePath("$path.{$pathinfo['extension']}", true);
            if (!is_file($filename))
                throw new v\FileException("File $filename is not found");
            $size = explode('x', trim($mathes[0], '_'));
            $content = v\kit\ImgCrop::file($filename)
                    ->rect($size[0], $size[1])
                    ->quality(array_value($size, 2, 8))
                    ->reSize();
        } else {
            $content = '';
        }
        return $content;
    }

    /**
     * 解析文本文件
     * @param string $file
     * @return string
     */
    protected function parseText($file) {
        $pathinfo = pathinfo($file);
        $content = '';
        if (strpos($pathinfo['filename'], '-') > 0) {
            $names = explode('-', $pathinfo['filename']);
            foreach ($names as $name) {
                $filename = v\App::absolutePath("{$pathinfo['dirname']}/$name.{$pathinfo['extension']}", true);
                if (!is_file($filename))
                    throw new v\FileException("File $filename is not found");
                $content .= file_get_contents($filename) . "\n";
            }
        }
        return $content;
    }

    /**
     * 解析JPG
     * @param string $file
     * @return string
     */
    protected function parseJpg($file) {
        return $this->parseImage($file);
    }

    /**
     * 解析PNG
     * @param string $file
     * @return string
     */
    protected function parsePng($file) {
        return $this->parseImage($file);
    }

    /**
     * 解析GIF
     * @param string $file
     * @return string
     */
    protected function parseGif($file) {
        return $this->parseImage($file);
    }

    /**
     * 解析JS
     * @param string $file
     * @return string
     */
    protected function parseJs($file) {
        return $this->parseText($file);
    }

    /**
     * 解析CSS
     * @param string $file
     * @return string
     */
    protected function parseCss($file) {
        return $this->parseText($file);
    }

    /**
     * 生产链接的缩略图地址
     * @param string $url url地址
     * @param string $thumbStr 缩略参数定义 宽x高x质量 宽高任一为0表示自动按比例计算 质量0-9 越大质量越高
     */
    public function thumb($url, $thumbStr) {
        $pos = strpos($url, '.');
        $url = substr($url, 0, $pos) . '_' . $thumbStr . substr($url, $pos);
        return $url;
    }

}
