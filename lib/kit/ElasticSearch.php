<?php

/**
 * Created by PhpStorm.
 * User: knyon
 * Date: 2016/9/6
 * Time: 12:24
 * 
 * ElasticSearch全文索引服务
 * 参考文档
 * https://www.elastic.co/guide/cn/elasticsearch/guide/current/index.html
 * 
 * 示例
 * // 添加索引index，添加索引一次即可
 * $this->index('indexName', 
        ['index' => [
            'number_of_shards'   => 1, // 分片数
            'number_of_replicas' => 1  // 备份数
        ]]);
 * // 添加类型type，同一个索引里type中的字段应该大致相同
 * $this->type('typeName', 
        [
            'name'     => ['type' => 'text', 'analyzer' => 'standard'],  // 普通搜索字段，中文使用smartcn
            'suggest'  => ['type' => 'completion', 'analyzer' => 'standard'],  // 自动完成suggest字段
            'position' => ['type' => 'integer']  // 数值字段
        ]);
 * $this->getOne('_id')  // 按ID取得文档
 * $this->filter([])->search([])  // 按条件过滤并取得文档，filter不带权重速度快，search带权重速度稍慢
 * $this->sort([field=>-1])->limit()->skip()  // 索引，条数，跳过
 */

namespace v\kit;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

/**
 * Class ES
 * ElasticSearch处理类
 *
 * @package v
 */
class ElasticSearch extends v\ServiceFactory {

    /**
     * 服务提供对象名
     * 必须子类定义
     * @var string
     */
    protected static $objname = 'v\kit\ElasticSearchService';

    /**
     * 服务提供对象
     * 必须子类定义
     * @var object
     */
    protected static $object;

}

class ElasticSearchService extends v\Service {

    /**
     * 配置定义
     * @var array
     */
    protected static $configs = [
        'host'     => 'http://127.0.0.1',
        'port'     => 9200,
        'username' => null,
        'password' => null
    ];

    /**
     * curl对象
     * @var Redis
     */
    protected $curl = null;

    /**
     * es主机与端口dsn
     * @var string
     */
    protected $host = null;

    /**
     * 索引名称 即db
     * @var string
     */
    protected $index = null;

    /**
     * 索引类型 即表名
     * @var string
     */
    protected $type = null;

    /**
     * 条数
     * @var int
     */
    protected $limit = 0;

    /**
     * 跳过多少条
     * @var int
     */
    protected $skip = 0;

    /**
     * 排序
     * @var array
     */
    protected $sort = [];

    /**
     * 过滤条件 不带权重
     * @var array
     */
    protected $filter = [];

    /**
     * 初始化，开启缓存
     */
    public function __construct() {
        // 建立curl
        $this->curl = curl_init();
        // 计算host
        $this->host();
    }

    /**
     * 生成host
     * @param string $host
     * @return string
     */
    protected function host($host = null) {
        if (!empty($host)) {
            $conf = $this->config();
            $this->host = (empty($conf['username']) ? '' : "--user {$conf['username']}:{$conf['password']}") . "{$host}:{$conf['port']}";
            // 加入host协议
            if (substr($this->host, 0, 4) !== 'http') {
                $this->host = 'http://' . $this->host;
            }
        } elseif (empty($this->host)) {
            // 随机使用host
            $hosts = explode(',', $this->conf('host'));
            $host = $hosts[0];
            // 多个host代表读写分离，随机使用host
            if (count($hosts) > 1) {
                array_shift($hosts);
                $host = array_rand($hosts);
            }
            return $this->host($host);
        }
        return $this->host;
    }

    /**
     * 执行命令
     * @param string $url
     * @param array $data
     */
    protected function exec($method, $url, $data = null) {
        // curl基本设置
        curl_setopt_array($this->curl, [
            CURLOPT_USERAGENT      => 'v7',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_HTTPHEADER     => ['Expect:', 'Content-Type: application/json'],
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_NOBODY         => false,
            CURLOPT_URL            => $url
        ]);
        // 请求体
        if (!is_null($data)) {
            if (!array_is_column($data)) {
                // 非批量
                $content = empty($data) ? '{}' : json_encode($data);
            } else {
                // 批量插入或更新
                $content = '';
                foreach ($data as $item) {
                    $content .= json_encode($item) . "\n";
                }
            }
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $content);
        }
        // 请求结果
        $content = curl_exec($this->curl);
        if (empty($content)) {
            $this->error = curl_error($this->curl);
        }
        return json_decode($content, true);
    }

    /**
     * 设置工作的索引库 | 索引库字段配置
     * 索引库即table,可以使用多个索引库使用,号隔开
     * @param string $name
     * @param array $setting
     * @return $this
     */
    public function index($name, $setting = null) {
        $this->index = $name;
        if (!is_null($setting)) {
            $rs = $this->exec('PUT', "{$this->host}/$name", ['settings' => $setting]);
            if (!empty($rs['error']))
                throw new v\Exception('ElasticSearch index setting error:' . json_encode($rs));
        }
        return $this;
    }

    /**
     * 删除索引
     * @param string $index
     * @return $this
     */
    public function drop($index = null) {
        if (empty($index))
            $index = $this->index;
        if (!empty($index)) {
            $this->exec('DELETE', "{$this->host}/$index");
        }
        return $this;
    }

    /**
     * 设置类型映射
     * 同索引下的类型字段名不能有冲突
     * 类型不适合完全不同类型的数据,类型只是不同集合中的细分
     * @param string $name
     * @param array $mapping
     * @return $this
     */
    public function type($name, $mapping = null) {
        $this->type = $name;
        if (!is_null($mapping)) {
            $rs = $this->exec('PUT', "{$this->host}/{$this->index}/_mapping/$name", ['properties' => $mapping]);
            if (!empty($rs['error']))
                throw new v\Exception('ElasticSearch type mapping error:' . json_encode($rs));
        }
        return $this;
    }

    /**
     * 更新或插入数据
     * 数据必须要有_id
     * @return int 成功的数量
     */
    public function insert($data) {
        if (!array_is_column($data)) {
            // 单条
            $id = array_delete($data, '_id');
            $rs = $this->exec('PUT', "{$this->host}/{$this->index}/{$this->type}/{$id}", $data);
            if (!empty($rs['error']))
                throw new v\Exception('ElasticSearch insert error:' . json_encode($rs));
            $rs = 1;
        } else {
            $items = [];
            foreach ($data as $item) {
                $items[] = ['index' => ['_id' => array_delete($item, '_id')]];
                $items[] = $item;
            }
            $rs = $this->exec('PUT', "{$this->host}/{$this->index}/{$this->type}/_bulk", $items);
            if (!empty($rs['errors']))
                throw new v\Exception('ElasticSearch bulk error:' . json_encode($rs));
            $rs = count($rs['items']);
        }
        return $rs;
    }

    /**
     * 要删除的数据库ID
     * @param string|array $id
     */
    public function delete($id) {
        $ids = arrayval($id);
        if (count($ids) === 1) {
            $rs = $this->exec('DELETE', "{$this->host}/{$this->index}/{$this->type}/{$id}");
            if (!empty($rs))
                $rs = 1;
        } else {
            $items = [];
            foreach ($ids as $id) {
                $items[] = ['delete' => ['_id' => $id]];
            }
            $rs = $this->exec('PUT', "{$this->host}/{$this->index}/{$this->type}/_bulk", $items);
            if (!empty($rs))
                $rs = count($rs['items']);
        }
        return $rs;
    }

    /**
     * 解析查询条件
     * @param array $criteria 查询条件 mongodb条件书写方式
     * @return array
     */
    protected function parseQuery($criteria = []) {
        $query = [];
        $range = [];
        foreach ($criteria as $k => $v) {
            if ($k == '$or') { // or关系
                foreach ($v as $v1) {
                    // 简化查询体
                    $query1 = $this->parseQuery($v1);
                    if (count($query1['bool'] === 1) && isset($query1['bool']['must']) && count($query1['bool']['must']) === 1) {
                        $query1 = $query1['bool']['must'][0];
                    }
                    $query['bool']['should'][] = $query1;
                }
            } elseif (is_array($v)) { // 数组计算逻辑符
                foreach ($v as $k1 => $v1) {
                    switch ($k1) {
                        case '$in': // 做精确查询处理
                            $query['bool']['filter']['bool']['must'][]['terms'][$k] = $v1;
                            break;
                        case '$ne':
                            $query['bool']['must_not'][]['match'][$k] = $v1;
                            break;
                        case '$lt':
                        case '$gt':
                        case '$lte':
                        case '$gte':
                            $range[$k][substr($k1, 1)] = $v1;
                            break;
                        default :
                            $query['bool']['must'][]['match'][$k] = $v1;
                    }
                }
            } else {  // 普通and关系
                $query['bool']['must'][]['match'][$k] = $v;
            }
        }
        // 范围查询处理，范围查询数据精确匹配不用评分，使用filter过滤器
        if (!empty($range)) {
            foreach ($range as $field => $set) {
                $query['bool']['filter']['bool']['must'][]['range'][$field] = $set;
            }
        }
        return $query;
    }

    /**
     * 搜索查询
     * @param array $query
     */
    public function search($query) {
        $criteria = [];
        // 分页处理
        if (!empty($this->limit)) {
            $criteria['from'] = $this->skip;
            $criteria['size'] = $this->limit;
        }
        // 排序处理
        if (!empty($this->sort)) {
            foreach ($this->sort as $k => $v) {
                $criteria['sort'][][$k]['order'] = ($v == -1 ? 'desc' : 'asc');
            }
            if (!empty($query)) // 有相关性排序查询，按评分排序
                $criteria['sort'][]['_score']['order'] = 'desc';
        }
        // 查询条件 带权重
        $criteria['query'] = $this->parseQuery($query);
        // 过滤条件 不带权重 性能好
        if (!empty($this->filter)) {
            $filter = ['bool' => ['filter' => $this->parseQuery($this->filter)]];
            array_extend($criteria['query'], $filter);
        }
        if (empty($criteria['query'])) {
            unset($criteria['query']);
        }
        $rs = $this->reset()->exec('GET', "{$this->host}/{$this->index}/{$this->type}/_search", $criteria);
        return $rs['hits'];
    }

    /**
     * 自动完成建议
     * @param array $query 自动完成条件
     * @return array
     */
    public function suggest($query) {
        $criteria = [];
        foreach ($query as $field => $text) {
            $criteria['suggest'][$field] = [
                'prefix'     => $text,
                'completion' => ['field' => $field]
            ];
        }
        $rs = $this->exec('GET', "{$this->host}/{$this->index}/{$this->type}/_search", $criteria);
        $data = [];
        foreach ($rs['suggest'] as $item) {
            foreach ($item[0]['options'] as $row) {
                $data[] = $row['_source'];
            }
        }
        return $data;
    }

    /**
     * 按ID取得一条数据
     * @param string $id
     * @return array
     */
    public function getOne($id) {
        $rs = $this->reset()->exec('GET', "{$this->host}/{$this->index}/{$this->type}/{$id}");
        if (!empty($rs) && $rs['found'] > 0) {
            $rs = $rs['_source'];
            $rs['_id'] = $id;
            return $rs;
        }
        return null;
    }

    /**
     * 取得所有数据
     * @return array
     */
    public function getAll() {
        return $this->search([]);
    }

    /**
     * 重设查询参数
     * @return $this
     */
    public function reset() {
        $this->filter = [];
        $this->sort = [];
        $this->limit = 0;
        $this->skip = 0;
        return $this;
    }

    /**
     * 过滤条件，过滤条件不带权重
     * @param array $filter
     * @return $this
     */
    public function filter($filter) {
        $this->filter = $filter;
        return $this;
    }

    /**
     * 排序方式
     * @param array $sort
     * @return $this
     */
    public function sort($sort) {
        $this->sort = $sort;
        return $this;
    }

    /**
     * 要查找的条数
     * @param int $limit
     * @return $this
     */
    public function limit($limit) {
        $this->limit = $limit;
        return $this;
    }

    /**
     * 要跳过的条数，数据太多可能会有性能问题
     * @param int $skip
     * @return $this
     */
    public function skip($skip) {
        $this->skip = $skip;
        return $this;
    }

}
