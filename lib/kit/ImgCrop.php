<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/9/6
 * Time: 12:24
 *
 * 图片缩放裁剪服务
 *
 */

namespace v\kit;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

class ImgCrop extends v\ServiceFactory {

    /**
     * 服务提供对象名
     * 必须子类定义
     * @var string
     */
    protected static $objname = 'v\kit\ImgCropService';

    /**
     * 服务提供对象
     * 必须子类定义
     * @var object
     */
    protected static $object;

}

class ImgCropService extends v\Service {

    /**
     * 原始图片路径
     * @var string
     */
    protected $file = null;

    /**
     * 图片类型
     * @var string
     */
    protected $type = null;

    /**
     * 新图片宽
     * @var int
     */
    protected $width = 0;

    /**
     * 新图片高
     * @var int
     */
    protected $height = 0;

    /**
     * 裁剪起始位置left
     * @var int
     */
    protected $left = -1;

    /**
     * 裁剪缩放比例，原始/新
     * @var int
     */
    protected $ratio = 1;

    /**
     * 裁剪起始位置top
     * @var int
     */
    protected $top = -1;

    /**
     * 新图片质量
     * 1-9 越大质量越高
     * @var int
     */
    protected $quality = 7;

    /**
     * 设置宽高
     * @param int $width
     * @param int $height
     */
    public function rect($width, $height) {
        $this->width = max([intval($width), 0]);
        $this->height = max([intval($height), 0]);
        return $this;
    }

    /**
     * 设置left top
     * @param int $x
     * @param int $y
     */
    public function point($x, $y) {
        $this->left = intval($x);
        $this->top = intval($y);
        return $this;
    }

    /**
     * 比率，原始/裁剪框
     * 变化该比例，图片实际宽高会改变
     * @param int $ratio
     */
    public function ratio($ratio) {
        $this->ratio = max([1, floatval($ratio)]);
        return $this;
    }

    /**
     * 设置质量
     * @param int $value
     */
    public function quality($value) {
        $this->quality = min([max([intval($value), 1]), 9]);
        return $this;
    }

    /**
     * 设置要处理的文件路径
     * @param string $file
     * @return $this
     * @throws Exception
     */
    public function file($file) {
        $info = getimagesize($file);
        if ($info === false) {
            throw new Exception("File $file not a legal image");
        }
        $this->file = $file;
        switch ($info[2]) {
            case 1:
                $this->type = 'gif';
                break;
            case 2:
                $this->type = 'jpg';
                break;
            case 3:
                $this->type = 'png';
                break;
            default :
                throw new Exception("File $file must be gif jpg png");
        }
        return $this;
    }

    /**
     * 生成新图像
     * @return resource
     */
    protected function newImage($type, $width, $height) {
        $im = imagecreatetruecolor($width, $height);
        switch ($type) {
            case 'png':
                $color = imagecolorallocate($im, 255, 255, 255);
                imagealphablending($im, false);
                imagecolortransparent($im, $color);
                imagefill($im, 0, 0, $color);
                imagesavealpha($im, true);
            case 'gif':
                $color = imagecolorallocate($im, 255, 255, 255);
                imagecolortransparent($im, $color);
        }
        return $im;
    }

    /**
     * 从文件取得图片
     * @return resource
     */
    protected function getImage() {
        switch ($this->type) {
            case 'gif': $fn = 'imagecreatefromgif';
                break;
            case 'png': $fn = 'imagecreatefrompng';
                break;
            case 'jpg': $fn = 'imagecreatefromjpeg';
        }
        $im = $fn($this->file);
        return $im;
    }

    /**
     * 保持图片到文件
     * @param resource $im
     * @param string $filename
     */
    protected function saveImage($im, $filename = null) {
        ob_start();
        switch ($this->type) {
            case 'gif': imagegif($im, $filename);
                break;
            case 'jpg':
            case 'jpeg': imagejpeg($im, $filename, ($this->quality + 1) * 10);
                break;
            case 'png':
                imagesavealpha($im, true);
                imagepng($im, $filename, 10 - $this->quality);
        }
        $content = ob_get_contents();
        ob_end_clean();
        @imagedestroy($im);
        return $content;
    }

    /**
     * 生成缩略图
     * @param object $im 图形对象
     * @param array $xy 高宽限制 width,height
     * @param string $type 图片类型
     * @return object
     */
    public function reSize($newfile = null) {
        if (empty($this->file))
            throw new v\PropertyException('Property file is null');
        $width = $this->width;
        $height = $this->height;
        $im = $this->getImage();
        if ($width > 0 || $height > 0) {
            $sx = imagesx($im);
            $sy = imagesy($im);
            //$width = min([$width, $sx]);
            //$height = min($height, $sy);
            if ($width <= 0) {  // 限高不限宽
                $rate = $height / $sy;
            } elseif ($height <= 0) {  // 限宽不限高
                $rate = $width / $sx;
            } else {  // 高宽同限
                $rate = min([$width / $sx, $height / $sy]);
            }
            if ($rate > 0) {
                $nsx = floor($sx * $rate);
                $nsy = floor($sy * $rate);
                $nim = $this->newImage($this->type, $nsx, $nsy);
                imagecopyresampled($nim, $im, 0, 0, 0, 0, $nsx, $nsy, $sx, $sy);
                @imagedestroy($im);
                $im = $nim;
            }
        }
        return $this->saveImage($im, $newfile);
    }

    /**
     * 生成裁剪图
     *
     * @param object $im 图形对象
     * @param array $xy 坐标定义 left,top,width,height
     *      left与top<0时不缩放裁剪，否则按尺寸裁剪
     * @param string $type 图片类型
     * @return object
     */
    public function reSample($newfile = null) {
        if (empty($this->file))
            throw new v\PropertyException('Property file is null');

        $width = $this->width;
        $height = $this->height;
        $left = $this->left;
        $top = $this->top;
        $im = $this->getImage();
        if ($width > 0 || $height > 0 || $left >= 0 || $top >= 0) {
            $sx = imagesx($im);
            $sy = imagesy($im);
            if ($width > 0 && $height > 0 && $left < 0 && $top < 0) {  // 自动缩放裁剪
                $left = $top = 0;
                if ($sx > $width && $sy > $height) {
                    $ratex = $sx / $width;
                    $ratey = $sy / $height;
                    if ($ratex > $ratey) {  // 按高度比例缩放
                        $sx = $width * $ratey;
                    } else {
                        $sy = $height * $ratex;  // 按宽度比例缩放
                    }
                } else {  // 某一边过小则直接裁剪另一边
                    $sx = min([$sx, $width]);
                    $sy = min([$sy, $height]);
                }
            } else {  // 按比例坐标及宽度定义剪裁
                $ratio = $this->ratio;
                $left = max(array($left, 0)) * $ratio;
                $top = max(array($top, 0)) * $ratio;
                if ($width <= 0)
                    $width = $sx;
                if ($height <= 0)
                    $height = $sy;
                $sx = $width * $ratio;
                $sy = $height * $ratio;
            }
            $width = min([$width, $sx]);
            $height = min([$height, $sy]);
            $nim = $this->newImage($this->type, $width, $height);
            imagecopyresampled($nim, $im, 0, 0, $left, $top, $width, $height, $sx, $sy);
            @imagedestroy($im);
            $im = $nim;
        }
        return $this->saveImage($im, $newfile);
    }

}
