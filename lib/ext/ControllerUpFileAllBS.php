<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2017/6/25
 * Time: 10:31
 * 
 * 前端文件上传
 * 配合v2_2/upfile.js 使用
 * 支持所有浏览器
 * 
 * 1.iframe上传文件，返回上传后的链接，此时文件位于临时目录中
 * 2.保存数据时候，把新加的文件保持到正式目录，把删除的文件进行删除
 * 
 * 
 * 注意文件上传，上传input file的名字必须为 upfile;
 * 
 * 有文件上传的控制器使用该trait
 * 
 */

namespace v\ext;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

trait ControllerUpFileAllBS {
    /*
     * 子类定义upfile类型与大小
      protected $configs = [
      'upfile' => [
      'size' => 1024, // KB
      'type' => '.jpg.png.gif',
      'dir' => '/upload/files',
      'splitDate' => 'Ym'
      ]
      ]; */

    /**
     * 响应回调数据
     * @param string $body
     * @param int $status
     */
    protected function resUpBack($status, $body) {
        // 延迟一点时间才能调到函数
        $body = '<script>setTimeout(function(){' . "completeUp(" . json_encode($body) . ", $status)" . '},13);</script>';
        v\Res::html($body)->end($status);
    }

    /**
     * 文件上传方法，单个
     * 上传到零时目录中
     */
    public function resUpFile() {
        $upName = 'upfile';
        if (empty($_FILES[$upName])) {
            v\Err::add('File not found');
        } else {
            $conf = $this->conf('upfile');
            // 先保存到临时文件夹中
            $upFile = v\kit\UpFile::options($conf)->inTemp(true);
            if ($upFile->file($_FILES[$upName])->isValid()) {
                $urls = $upFile->save();
                $url = reset($urls);
                $this->resUpBack(200, $url);
            }
        }
        $this->resUpBack(422, v\Err::get('*'));
    }

    /**
     * 取得模型的上传字段定义
     * @param v\Model $model
     * @return array
     */
    protected function getUpFields($model) {
        $fields = $model->fields();
        // 取得允许上传文件类型与字段
        foreach ($fields as $field => $options) {
            $type = $options[0];
            if (!isset($options[1]) || ($type !== 'file' && $type !== 'files')) {
                unset($fields[$field]);
            } else {
                $fields[$field] = $type;
            }
        }
        return $fields;
    }

    /**
     * 保存与删除上传文件
     * @param string|array $filesOld
     * @param string|array $filesNow
     * @return array 当前上传文件路径
     */
    protected function upSaveAndDel($filesOld, $filesNow) {
        $filesOld = arrayval($filesOld);
        $filesNow = arrayval($filesNow);
        $filesAdd = array_diff($filesNow, $filesOld);
        $filesDel = array_diff($filesOld, $filesNow);

        // 添加文件保存从临时目录保存到目录
        $conf = $this->conf('upfile');
        $upFile = v\kit\UpFile::options($conf);
        if (!empty($filesAdd)) {
            $filesNow = array_diff($filesNow, $filesAdd);
            $filesNow = array_merge($filesNow, $upFile->move2dir($filesAdd));
        }
        // 删除文件处理
        if (!empty($filesDel)) {
            $upFile->delete($filesDel);
        }
        return $filesNow;
    }

    /**
     * 保存或者删除文件
     * 通过对比原字段处理
     * 注意必须先save file 再保存数据
     * @param array $item  原字段值
     * @return boolean
     */
    protected function upFileSave($item = []) {
        $model = v\App::model($this->model);
        $fields = $this->getUpFields($model);
        $upData = $model->data();
        foreach ($fields as $field => $type) {
            if (isset($upData[$field])) {
                $filesNow = $upData[$field];
                $filesOld = empty($item[$field]) ? [] : $item[$field];
                $filesNow = $this->upSaveAndDel($filesOld, $filesNow);
                if ($type === 'file') {
                    $filesNow = reset($filesNow);
                }
                $model->addData([$field => $filesNow]);
            }
        }
    }

}
