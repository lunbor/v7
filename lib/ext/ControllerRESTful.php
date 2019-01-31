<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/9/8
 * Time: 10:31
 * RESTful控制器
 * 实现了对模块数据的增删查改
 * 只适用于父类使用
 * 
 * 注意文件上传，上传input file的名字必须为 upfile_$filedname;
 */

namespace v\ext;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

trait ControllerRESTful {
    /**
     * @var 模块名
     * 类中定义
     */
    //protected $model = null;

    /**
     * 禁止用户取得的字段
     * 类中定义
     * @var array
     */
    //protected $forbidFields = [];

    /**
     * 允许查询字段
     * 为空则模型所有字段均可查询
     * 类中定义
     * @var array
     */
    //protected $queryFields = [];

    /**
     * 允许排序字段
     * 为空则模型所有字段均可排序
     * 类中定义
     * @var array
     */
    //protected $sortFields = [];

    /**
     * 允许用户修改的字段
     * 类中定义
     * @var array
     */
    //protected $allowFields = [];

    /**
     * 处理GET的查询数据
     * @param $params
     * @return array
     */
    protected function filterQuery($params) {
        $query = $params;
        // 提取允许字段
        if (!empty($this->queryFields))
            array_column_filter($query, $this->queryFields);
        array_filter_null($query);
        // 转换字段的值
        $query = v\App::model($this->model)->castData($query);
        foreach ($query as &$v) {  // 查找时数组的处理
            if (is_array($v))
                $v = reset($v);
        }
        // 允许多个ID查询
        if (!empty($params['ids'])) {
            $query['_id'] = ['$in' => arrayval($params['ids'])];
        }
        // 对于时间等在此由子类处理
        return $query;
    }

    /**
     * 处理 filed limit skip sort
     * @param array $params
     * @return array
     */
    protected function filterOptions($params) {
        // field limit skip sort
        $options = array_filter_key($params, ['field', 'skip', 'limit', 'sort']);
        // 分页
        $options['row'] = empty($params['row']) ? null : intval($params['row']);
        $options['page'] = empty($params['page']) ? 1 : intval($params['page']);
        // 排序
        if (!empty($options['sort']) && !empty($this->sortFields)) {
            if (is_string($options['sort'])) {
                if (!in_array($options['sort'], $this->sortFields))
                    unset($options['sort']);
            } else {
                array_column_filter($options['sort'], $this->sortFields);
            }
        }
        return $options;
    }

    /**
     * 添加效验成功后调用该函数
     */
    protected function hookPostIsValidated() {
        
    }

    /**
     * 修改效验成功后调用该函数
     * @param array $item 原数据
     */
    protected function hookPutIsValidated($item) {
        
    }

    /**
     * 上传文件
     * 只要有一组文件上传成功，即视为成功
     * @param array $item 原有字段
     * @return boolean
     */
    protected function upFile($item = []) {
        $model = v\App::model($this->model);
        $upData = $model->data();
        $fields = $model->fields();
        // 取得允许上传文件类型与字段
        foreach ($fields as $field => $options) {
            $type = $options[0];
            if (!isset($upData[$field]) || !isset($options[1]) || ($type !== 'file' && $type !== 'files')) {
                unset($fields[$field]);
            } else {
                $fields[$field] = $type;
            }
        }
        if (empty($fields))
            return 1;

        $rs = empty($_FILES) ? 1 : 0;
        $upFile = v\kit\UpFile::options($this->conf('upfile', []));
        foreach ($fields as $field => $type) {
            // 删除文件处理
            $files = $upData[$field];
            if (isset($item[$field])) {
                if ($type === 'file') {
                    if ($fields !== $item[$field])
                        $upFile->delete($item[$field]);
                } elseif (!empty($item[$field])) {
                    $files1 = array_diff($item[$field], $files);  // 找出被删除的数据
                    if (!empty($files1))
                        $upFile->delete($files1);
                }
            }
            // 检查保存上传的文件
            $upName = "upfile_$field";
            if (!empty($_FILES[$upName]) && $upFile->file($_FILES[$upName])->isValid()) {
                $urls = $upFile->save();
                if (!empty($urls)) {
                    if ($type === 'file') {
                        // 只允许单个文件则覆盖
                        $files = reset($urls);
                    } else {
                        // 多个文件，按顺序覆盖blob:开头的上传文件
                        foreach ($files as $k => $file) {
                            if (strpos($file, 'blob:') === 0) {
                                $files[$k] = array_shift($urls);
                            }
                        }
                    }
                    $rs++;
                }
            }
            // 删除预览的客户本地图片
            if (is_array($files)) {
                foreach ($files as $k => $file) {
                    if (strpos($file, 'blob:') === 0) {
                        unset($files[$k]);
                    }
                }
            }
            $model->addData([$field => $files]);
        }
        return $rs;
    }

    /**
     * 按ID取得单条数据
     * @return array
     */
    public function resByID() {
        $params = v\App::param();
        if (empty($params['id'])) {
            return v\Err::add('Required id')->resp(400);
        }
        // field字段
        $options = array_filter_key($params, ['field']);
        $data = v\App::model($this->model)->getByID($params['id'], $options);

        // 错误返回
        if (empty($data)) {
            return v\Err::add('Not found by id')->resp(404);
        }

        // 去除不允许返回的信息
        if (!empty($this->forbidFields)) {
            array_column_unset($data, $this->forbidFields);
        }

        // 数据返回，API接口数据移动要return返回
        return v\App::resp(200, $data);
    }

    /**
     * 响应数据列表
     * @return array
     */
    public function resList() {
        $params = v\App::param();
        $options = $this->filterOptions($params);
        $query = $this->filterQuery($params);
        $model = v\App::model($this->model);
        $fuzzyCount = array_value($params, 'fuzzy', array_value($params, 'fuzzyCount'));
        $data = $model->where($query)->getPaging($options, $fuzzyCount);

        // 错误返回
        if (empty($data['data'])) {
            return v\Err::add('Not found any one')->resp(404);
        }

        // 去除不允许返回的信息
        if (!empty($this->forbidFields)) {
            array_column_unset($data['data'], $this->forbidFields);
        }
        // 数据返回，API接口数据移动要return返回
        return v\App::resp(200, $data);
    }

    /**
     * 取得模型数据，单条或者多条
     * @return array
     */
    public function resGet() {
        // ID查询
        if (v\App::param('id')) {
            return $this->resByID();
        }
        return $this->resList();
    }

    /**
     * 添加数据
     * @return mixed
     */
    public function resPost() {
        $params = v\App::param();
        $model = v\App::model($this->model);
        $model->setData($params);
        if ($model->isMust()) {  // 注意添加需要必填校验
            $this->hookPostIsValidated();
            // 文件上传处理，文件上传不成功不会保存
            if ($this->upFile() && $model->addOne()) {
                // 取得成功返回的数据
                $options = array_filter_key($params, ['field']);
                $lastID = $model->lastID();
                $data = $model->getByID($lastID, $options);
                return v\App::resp(200, $data);
            }
        }
        // 数据返回，API接口数据移动要return返回
        return v\Err::resp(422);
    }

    /**
     * 修改数据
     */
    public function resPut() {
        $params = v\App::param();
        if (empty($params['id']))
            return v\Err::add('Required id')->resp(400);

        $model = v\App::model($this->model);
        // 检查是否有该记录
        $row = $model->getByID($params['id']);
        if (empty($row))
            return v\Err::add('Not found by id')->resp(404);

        // 只允许可修改字段
        if (!empty($this->allowFields))
            array_column_filter($params, $this->allowFields);

        $model->setData($params)->subData($row);  // 需要减去值相同的数据
        if ($model->isValid()) {  // 修改不需要必填校验
            $this->hookPutIsValidated($row);
            if ($model->hasData()) {
                $this->upFile($row);  // 有字段变化进行文件上传处理
                $rs = $model->field(array_value($params, 'field', '*'))->upByID($row['_id']); // 修改并返回修改后的数据
            } else {
                $rs = $model->field(array_value($params, 'field', '*'))->getByID($row['_id']);  // 无数据直接返回
            }
            return v\App::resp(200, $rs);
        }
        // 数据返回，API接口数据移动要return返回
        return v\Err::resp(422);
    }

    /**
     * 删除数据
     */
    public function resDelete() {
        $params = v\App::param();
        if (empty($params['id']))
            return v\Err::add('Required id')->resp(400);

        $id = $params['id'];
        $model = v\App::model($this->model);
        $rs = $model->delByIDs($id);
        if ($rs) {
            return v\App::resp(200, ['_id' => $id]);
        }
        // 数据返回，API接口数据移动要return返回
        return v\Err::add('Delete has a error')->resp(422);
    }

    /**
     * 测试restful接口
     */
    public function resTest() {
        if (!($this->conf('enableTest') && v\App::debug()))
            v\Res::end(400);

        $model = v\App::model($this->model);
        $fields = $model->fields();
        $id = v\App::param('id', '');
        $data = empty($id) ? [] : $model->getByID($id);
        if (empty($data))
            $data = [];
        print_r($data);
        $hstr = '<html><body>';
        $hstr .= '<h5>' . $this->model . ' Model</h5>';
        $hstr .= '<p>Count:' . $model->count() . '&nbsp;<a href="' . v\App::url('.json', ['sort' => '-_id']) . '" target="_blank">GET</a></p>';
        $hstr .= '<p>ID:' . $id . '(id param in url)</p>';
        $hstr .= '<form action="' . v\App::url('.json') . '" method="POST" target="_blank">';
        $hstr .= '<input type="hidden" name="_method" value="' . (empty($id) ? 'POST' : 'PUT') . '"/><input type="hidden" name="id" value="' . $id . '" />';
        foreach ($fields as $field => $options) {
            if (!empty($options) && isset($options[1])) {
                $value = array_value($data, $field, '');
                if (is_array($value))
                    $value = implode(',', $value);
                $hstr .= $field . '<input name="' . $field . '" value="' . $value . '" /></br>';
            }
        }
        $hstr .= '<button type="submit">Submit</button></form>';
        $hstr .= '</body></html>';
        return v\App::resp(200, $hstr);
    }

}
