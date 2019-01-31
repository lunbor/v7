# v7 PHP框架入门

## 框架特点

简单、快速、模块化、服务化

## 框架目录

    lib 框架程序目录
        third 第三方程序目录
        cache 缓存类服务目录
        db 数据库服务目录
        session session类服务目录
        ext trait扩展服务目录
        kit 服务工具目录
        Boot.php 启动程序
        Func.php 公共函数
        Comn.php 服务与接口公共定义
        App.php 应用服务类
        Req.php 请求处理服务类
        Res.php 响应处理服务类
        View.php 视图服务类
        Controller.php 控制器父类
        Crontab.php 任务父类
        Model.php 模型父类
        Router.php 路由服务类
        Err.php 错误服务类
        DB.php 数据库服务类
        Session.php session服务类
        Redis.php redis服务类
    v.php 框架类

## 建立一个应用

### 应用目录定义

    myapp  应用主
        _ 应用程序目录
            config 配置文件目录
                global.php  主配置文件
            controller 控制器目录
            crontab 后台任务目录
            lib 类文件目录
            model 模型目录
            static 静态文件目录，放置js、css、图片、上传文件
            view 视图目录
            lang 语言目录
                zh-cn 中文语言目录
        module1 子模块1
        module2 子模块2
        index.php 入口文件

### index.php 统一入口文件

    error_reporting(E_ALL);
    date_default_timezone_set('Asia/Shanghai');
    require dirname(dirname(__FILE__)) . '/v7/v.php';

    v\App::run();

    exit;

### global.php 应用主配置文件

主配置文件路径 _/config/global.php

    return [
        'debug' => true,
        'logDir' => null, // 日志文件夹，如果为空当前模块logs目录
        'v\App' => [
            'service' => 'lib\Application',  // 应用服务类
        ],
        'v\Req' => [
            'rewrite' => false, // 重写开启
            'defaultType' => 'html', // 默认请求类型
            'err404Controller' => 'err404', // 404错误控制器
            'staticController' => 'static', // 静态文件控制器
        ],
        'v\Res' => [
            'service' => 'lib\Response',  // 响应服务类
            'charset' => 'utf-8', // 字符集编码
            'jsonpParam' => 'jsonp', // jsonp参数名
        ],
        'v\DB' => [
            'service' => 'v\db\MongoDB' // 数据服务类
        ],
        'v\Controller' => [
            'enableTest' => true, // 开启控制器测试，注意线上版本必须关闭
        ],
    ];

注意，子模块配置会自动覆盖父模块配置

### lib下建立应用的服务的类

位于_\lib目录下，要使用自己的应用服务类，请在global.php文件中配置该服务的service

Application.php 应用服务类，该类提供v\App服务的方法，该服务为应用的全局服务类，需要从v\Application继承

    namespace vApp\lib;

    class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));
    use v;

    class Application extends v\Application  {

    }

Controller.php 应用的控制器父类，应用下的控制器应该从该类继承

    namespace vApp\lib;

    class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));
    use v;

    abstract class Controller extends v\Controller {

    }

Model.php 模型的控制器父类，应用下的模型应该从该类继承

    namespace vApp\lib;

    class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));
    use v;

    abstract class Model extends v\Model {

    }

### 模型初探Model

位于_\model 目录下

Staff.php 管理员模型

    namespace vApp\model;

    class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));
    use v, vApp;

    class Staff extends vApp\lib\Model {

        /**
         * 表名字
         * @var string
         */
        protected $table = 'staff';

        /**
         * 字段定义
         * @var array
         */
        protected $fields = [
            '_id' => ['string'],
            'username' => ['string', ['*', 'alnum']],
            'realname' => ['string', ['length, 2, 50']],
            'age' => []
        ];

        /**
         * 索引定义，由子类定义
         * 每个数组代表一个索引
         * @var array
         */
       protected $indexes = [
               ['filedname' => -1, 'fieldname2' => 1],
               [['fieldname' => 1, 'fieldname2' => '2dsphere'], ['background' => true, 'unique' => true]]
       ];
    }

以上定义了一个简单的模型，表名与字段必须定义

字段定义格式为： 字段名 => ['字段类型', ['效验方法1', '效验方法2']]

注意通过setData方法设置的模型数据未定义效验方法的字段会丢弃

### 第一个控制器Controller

v7框架符合restful规范，提供了标准的RESTful控制器trait v\ext\ControllerRESTful.php

文件：_\controller\staff.php  注意控制器文件名要小写

    namespace vApp\controller;

    class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));
    use v;

    class Staff extends \vApp\lib\ControllerRESTful {
        use v\ext\ControllerRESTful;

        /**
         * 模型名称
         * @var string
         */
        protected $model = 'Staff';

    }

只需要定义一个控制器所对应的模型名称就可以实现staff表数据的增删查改拉

## 命名空间与服务、文件路径

框架的核心技术服务，服务分服务工厂与服务提供程序组成，服务提供程序提供具体的服务功能。

服务相当于一个某类型的公司，服务程序相当于某个具体的公司，如医院是一种服务，成都市第一人民医院是提供医院这种服务的具体程序，当然你可以选择第二人民医院提供服务。

如框架中v\App类是一个服务，改服务主要提供应用层的方法，v\Application是框架中默认的应用服务提供者，我们也可以使用自己编写的应用服务提供者。

程序中使用的是服务中的方法，如v\App::url()，服务提供者的变化不会影响到你程序的书写方式，这样你就可以灵活配置自己的服务提供程序

*什么时候使用服务呢？* 
适合使用单利模式的情况应该豪不犹豫的使用服务功能

服务应该从 v\ServiceFactory 继承，服务提供者应该从v\Service 继承 如框架中提供的IpAddr服务

    namespace v\kit;

    class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));
    use v;

    class IpAddr extends v\ServiceFactory {

        /**
         * 服务提供对象名
         * 必须子类定义
         * @var string
         */
        protected static $objname = 'v\kit\IpAddrService';

        /**
         * 服务提供对象
         * 必须子类定义
         * @var object
         */
        protected static $object;

    }

    class IpAddrService extends v\Service {

        /**
         * 从IP138获取IP地址
         * @param string $ip
         * @return string
         */
        public function fromIp138($ip) {
            $url = "http://www.ip138.com/ips138.asp?ip=$ip";
            $con = file_get_contents($url);
            if (!empty($con) && preg_match('/<li>(.*?)<\/li>/', $con, $mathes)) {
                $addr = iconv('gb2312', 'utf-8', $mathes[1]);
                $addr = explode('：', explode(' ', $addr)[0])[1];
                return $addr;
            }
            return '';
        }

    }

   
怎样使用服务中的方法呢？ v\kit\IpAddr::fromIp138()

服务只能市单咧的吗？当然不是，你总有建立多个对象的需求，比如说数据库访问服务，我可能会同时访问多个数据库，你使用 v\DB::object($conf) 即可获取到不同的服务对象啦，当然如果配置相同，服务对象也会一样的拉。

服务的配置文件是可以向上合并继承的，意思是子类的配置文件会合并父类的配置文件，你只需要在类中定义  protected static $configs = [...] 就成啦

v7框架必须使用命名空间对象，框架命名空间为 v\目录\文件， 应用命名空间为 vApp\模块\目录\文件

当然，视图是不需要命名空间的啦

v7框架中的文件具有上溯功能，即在当前模块下没找到该文件，会向上级模块寻找，直到找到文件为止


## 模型、视图、控制器 MVC 啦

### 模型该干啥？

模型在应用中负责数据处理与业务逻辑

数据处理当然是指数据的增删插改拉

业务逻辑见得说就是指你写的处理该模型数据的共用方法啦，比如加入商品到购物车，你应改写个addGoods方法，而不是把这个逻辑写到控制器里面去，为啥呢？因为这个方法要共用哇

模型必须定义一个表名table，会自动实现对该表的基础操作，当然也是可以在这里操作其他表（其他模型）的，如果没有table，那就不应该是模型啦，你该把这个程序扔到lib下面去了

模型的字段你必须定义清楚拉，这里我们要详细说的是数据类型与效验啦

        protected $fields = [
            字段1 => ['字段类型', ['效验方法1', '效验方法2']],
            字段2 => ['字段类型', ['效验方法1', '效验方法2']]
        ];

字段类型与效验方法可以是你自己写的方法啦，把他放在模型里面就可以了，系统提供了一系列默认的类型与效验方法，你可以在v\lib\ext\AuditData.php的castValue方法与validByRule方法中找到

转换时候使用呢？$model->setData($params)，调用该方法的时候就会自动转换数据类型拉，注意没有定义类型与效验的字段会被丢弃，如果不效验，你可以使用'pass'规则

校验什么时候使用呢？$model->isMust(), $model->isValid() 这两个方法会自动校验数据的正确性

相同模型怎样同时链接多个数据库呢？$model2 = $model->copy($conf)，传入数据库配置这样就建立了一个新的模型实例啦

数据库一定要建立索引哟，在模型中定义$indexes属性就可以了，定义的格式如下

       protected $indexes = [
               ['fieldname' => -1, 'fieldname2' => 1],  // 普通索引，1个及以上的字段为组合索引
               [['fieldname2' => '2dsphere'], ['background' => true, 'unique' => true]]  // 定义了索引的属性
       ];

怎样HOOK添加与更新数据，方法里默认要改变或者添加一些数据，V7不再使用钩子函数，需要去继承setAdd与setUp方法，注意只需要继承这两个方法即可解决添加与更新的所有事情，调用add、up与save开头的方法会自动调用对应的方法处理数据

模型有哪些方法呢？身为程序员你该自己去看源代码拉，位置 v\lib\Model.php  v\lib\ext\AuditData.php  v\lib\QueryData.php，有很多惊喜在其中哟

善于使用upsert、$model->field()->upOne()方法，可以返回当时修改后的数据或修改前的数据。

### 控制器干啥呢？

控制器主要处理用户逻辑啦，是模型和视图的胶水

用户逻辑指权限检查，数据获取，视图组织，记得别把模型的业务逻辑写到控制器来了

控制器中有用户可以通过url访问到的action，方法命名为resAction

控制器访问URL为 "/模块/控制器/action.请求类型"，如 /staff/test.html 这个表明访问staff控制器的resTest方法啦

控制器文件支持下划线隔开的方式，但类名仍然使用帕斯卡的命名方式，如access_other_module.php文件，类名为AccessOtherModule

控制器文件名请小写

请注意当模块名与控制器名称冲突时，模块优先

action方法应该return数组或者字符串，系统会根据请求格式转换成对应的格式，如果你有兴趣可以去看看v\Res.php类

RESTful规范你应该掌握一下，简单的说控制器是一个资源，该资源有4种类型的操作GET\POST\DELETE\PUT，对于查\增\删\改，通过http的请求类型来判断数据的操作，所以RESTful控制中通常有resGet,resPost,resPut,resDelete四个方法

控制取用户GET或者POST的数据，可以通过v\App::param()方法取得，注意该方法取得的数据没有经过任何安全方面的处理，请不要使用$_GET或者$_POST取数据

下面我们通过分析一个RESTful接口的控制器源码v\ext\ControllerRESTful.php，让你掌握控制器与模型

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
                // 转换字段的值
                $query = v\App::model($this->model)->castData($query);
                // 对于时间等在此由子类处理
                return $query;
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
             * 取得模型数据，单条或者多条
             * @return array
             */
            public function resGet() {
                $params = v\App::param();
                // ID查询
                if (!empty($params['id'])) {
                    return $this->resByID();
                }
                // field limit skip sort
                $options = array_filter_key($params, ['field', 'skip', 'limit', 'sort']);
                // 分页
                $options['row'] = empty($params['row']) ? null : intval($params['row']);
                $options['page'] = empty($params['page']) ? 1 : intval($params['page']);

                $query = $this->filterQuery($params);
                $model = v\App::model($this->model);
                $data = $model->where($query)->getPaging($options);

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
             * 添加数据
             * @return mixed
             */
            public function resPost() {
                $params = v\App::param();
                $model = v\App::model($this->model);
                $model->setData($params);
                if ($model->isMust()) {  // 注意添加需要必填校验
                    $rs = $model->addAll();
                    if ($rs) {
                        // 取得成功返回的数据
                        $options = array_filter_key($params, ['field']);
                        $data = $model->lastRow($options);
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
                    $rs = $model->upByID($row['_id']);
                    if ($rs || !$model->hasData()) {
                        // 取得成功返回的数据
                        $options = array_filter_key($params, ['field']);
                        $data = $model->getByID($row['_id'], $options);
                        return v\App::resp(200, $data);
                    }
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

                $ids = $params['id'];
                $model = v\App::model($this->model);
                $rs = $model->delByIDs($ids);

                // 数据返回，API接口数据移动要return返回
                return v\App::resp(200, $rs);
                // return $this->resp($rs); 这样也是可以的
            }

        }

控制器响应正确的数据可以使用v\App::resp方法，也可以使用$this->resp方法，应用的resp方法会直接返回数据，控制器的resp方法格式与v\Err相同，errno编号为0

### 视图就是这么简单

视图即是php脚本片段，当然不一定是php，可以是JS或者CSS

视图位于 _/view文件夹下

控制器怎样访问视图呢？看下面的示例程序

        public function resGet() {
            // 传值到视图
            v\View::assign([
                'username' => 'daojon'
            ]);
            // 响应视图
            return v\View::resp('index.php');
        }

视图支持框架与继承，看如下示例

view/index.php

        <?=v\View::loadCrumb('crumb/layout.php');?>  <!-- 加载视图框架 layout.php 与结束符之间的内容将放在 layout.php模板的content处 -->

        <?= v\View::startCrumb('header');?>  <!-- 修改框架里的header定义，相当与继承：用自定义模板碎片覆盖框架中的header -->
        <p>This my header</p>
        <?=v\View::endCrumb('header');?>  <!-- 模板碎片 header 结束 -->

        <p>Content This my username <?= view('username'); ?></p>  <!-- 取数据username -->

        <?=v\View::endCrumb('crumb/layout.php');?>  <!-- 视图框架 layout.php 结束 -->

view/layout.php

        <html>
            <?=view('header', 'header.php');?>  <!-- 取数header，如果没有则使用header.php模板，可被子模板覆盖，脚本路径请用相对路径 -->
            <?=view('content');?> <!-- 取content数据 -->
        </html>

视图主要是两个方法 v\View::startCrumb方法与view函数

v\View::startCrumb 用于载入视图碎片与定义视图碎片，与endCrumb成对使用

加载模板需要写明模板后缀名，loadCrumb与endCrumb成对使用

view函数用于从视图取数据，允许默认模板数据

控制器中绘制视图请优先使用$this->view方法，这样可以自动把控制器对象传入到视图之中

### 数据库与分布式

数据是系统的核心。在框架中提供了对mongodb数据库的配置与操作。

配置应用的默认数据库：
_/config/global.php中

        'v\DB' => [
            'service' => 'v\db\MongoDB',  // 使用mongodb驱动
            'splitRW' => false,  // 是否读写分离，false为非读写分离，host为多台并且mongodb数据库组建为副本集，从库要设为可读，全局配置，在model中配置无效
            'host' => '127.0.0.1:27017',
            'dbname' => 'test1',
            'username' => '', // 用户名，无用户名与密码不配置
            'password' => '', // 密码
        ],

        'v\DB' => [
            'service' => 'v\db\PdoSQL',  // 使用sql pdo驱动
            'prefix' => 'pgsql', // 使用pgsql数据库
            'splitRW' => false,  // 是否读写分离，false为非读写分离，host为多台并且数据库配置了流复制，全局配置，在model中配置无效
            'host' => '127.0.0.1;192.168.1.1',  // 第一个为主库，后面的从库会再只读模式下随机挑选
            'port' => 5432
            'dbname' => 'test1',
            'username' => '', // 用户名，无用户名与密码不配置
            'password' => '', // 密码
        ],

MongoDB如果要做读写分离，host的配置请注意

        host => '192.168.1.1:27017,192.168.1.2:27018/?replicaSet=myReplicaSet'
        splitRW => true

在读写分离模式下，请注意刚刚写入的数据因为复制的关系会不能立即读出来，如果希望对刚刚写入的数据可读，请使用db方法中的splitRW方法。最好只在确定业务全为读的情况下开启读写分离，避免建立过多链接

        v\DB::splitRW(false);  // 关闭读写分离
        v\DB::splitRW(true);  // 开启读写分离

不同模型使用不同数据库，可在模型中配置，未配置的使用db默认配置

       protected static $configs = [
           'db' => [
               'host' => ''  // 使用不同服务器的数据库
           ]
       ];

在程序允许中改变model的数据库配置，可以改变当前模型的数据库配置，也可以复制一个新模型

        $model1->db(['host'=>''])  // 重新配置数据链接

        $model2 = $model1->copy(['host'=>'']);  // 复制出一个新的模型


### 定时任务 Crontab

定时任务需要系统的支持,linux下为contab任务

crontab位于应用_/crontab目录下，从v\Crontab继承，请注意crontab只能通过php命令行调用执行，不是控制器哟

crontab的命名与访问路径同控制器，请下划线分割并且小写

crontab可以一直守护执行，最小执行间隔为1毫秒，最长时间为10分钟，所以守护任务请每10分钟调用一次crontab，而只执行一次的定时任务请程序的interval熟悉设置为10分钟

        namespace vApp\crontab;

        class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));
        use v, vApp;

        class Indexes extends v\Crontab {

            /**
             * 任务间隔时间
             * 执行一次，间隔设为0.1秒
             * @var int
             */
            protected $interval = 0.1;

            /**
             * 开始任务执行
             */
            public function start() {
                $model = v\App::model('Staff');
                $model->db()->indexes();
            }

        }

你的任务程序只需要写在start函数里面就可以啦

怎样在系统里面调用crontab呢，v\App::crontab($crontabName) 就成啦

crontab的执行间隔时间interval取值区间为 >= 0.000001秒 <= 600秒，linux crontab设置为

    */10 * * * * /usr/bin/php /var/www/v7x/index.php myjob


## 应用v\App的那些事儿

应用是具有上下文的，不同的模块应用的上下文不同的，怎样在该模块下正确访问其他模块的方法呢？

        $data = [];
        // 访问API module
        v\App::module('api', function() use (&$data) {
            // api模型的操作写在这里
            $rs = v\App::param(['row' => 20])->controller('staff')->resGet();
            if (empty($rs['errno'])) {
                $data['staff'] = $rs['data'];
            }
            // 下划线控制器的访问goods_category
            $rs = v\App::param()->controller('goodsCategory')->resGet();
        });
        return v\App::resp(200, $data);

以上程序会在处理api模块时候，将上下文切换到API模块，代码执行完毕后上下文会切换回当前模块

## 安全是一个永远的话题

### 我们不用sql，不用防sql注入了，错了，还是要防数据库注入的，和XSS跨站攻击一起防吧

所有入库的数据应该进行类型转换，框架里面带了AduitData的trait库，用于处理常见的数据类型转换，castValue()函数中定义了可转换的类型

所有入库的数据应该进行数据效验，框架里面带了AduitData的trait库，用于处理常见的数据效验，validByRule()函数中定义了常用的效验方式

一般在模型中进行数据的效验与转换的定义，然后使用$model->setData($param)->data()进行数据的转换，使用$model->isValid()与$model->isMust()进行数据的效验工作，详见模型处理章节

当然你也可以在控制器中 use v\ext\AuditData 然后就可以想模型一样转换与效验数据了

### csrf攻击，这是最危险的攻击防范

csrf攻击不容忽视，理论上每一个带有权限检查（用户权限）的接口都应该做csrf效验

控制器与视图中提供了基本的csrf效验方法，并可在控制器中进行csrf参数的配置

默认的csrf的参数名为 csrf_token，你可以通过配置进行改变，注意要在视图中使用csrf，需要在控制器中载入视图的时候使用控制器的view方法

        // 视图中取得csrf表单域
        <?= v\View::htmlCSRF() ?>
        // 接口中取得csrf的值，APP端需要开发专门的接口取得csrf_token
        $csrf_token = $this->tokenCSRF()
        // 控制器程序中进行csrf效验
        if ($this->checkCSRF()) {
            // 你的程序
        }
        return v\Err::resp(403);

安全问题不容忽视，请注意

## 在MongoDB中进行事务处理

mongodb中是没有数据库事务支持的，框架中带了数据的守护工具代替数据库事务功能，保证事务的完整性

v\kit\Guard 数据守护工具不支持回滚功能，只能保证多条数据写入数据库的完整性，如果涉及到逻辑性的操作请不要依靠该工具来保证

订单、账户收支、账户余额 实现第三方支付回调示例

        /**
        * 支付事务
        * @param array $data 处理的数据
        * @param string $guardID  守护事务ID
        * @return int  >=4  成功，其他情况则发生一个未知位错
        */
       public function guardPayin($data, $guardID) {
           $mStaff = v\App::model('Staff');
           $mAccount = v\App::model('Account');
           $mOrder = v\App::model('Order');

           // 取回事务中的数据
           $orderID = $data['order_id'];
           $memberID = $data['member_id'];
           $amount = $data['amount'];

           // 1. 把钱加入账户
           $rs = v\kit\Guard::stepAdd($guardID, $mAccount, ['member_id' => $memberID, 'money' => $amount, 'order_id' => $orderID]);
           if (!$rs) {
               return 1;
           }

           // 2. 更改账户余额
           $rs = v\kit\Guard::stepUp($guardID, $mStaff, ['$inc' => ['amount' => $amount]], ['_id' => $memberID]);
           if (!$rs) {
               return 2;
           }
           // 3. 更改订单状态
           $rs = v\kit\Guard::stepUp($guardID, $mOrder, ['state' => 1, 'amount_pay' => $amount], ['_id' => $orderID]);
           if (!$rs) {
               return 3;
           }

           // 完成事务
           v\kit\Guard::finish($guardID, [$mAccount, $mStaff, $mOrder]);
           return 4;
       }


       /**
        * 完成支付，第三方支付异步回调该接口
        * @param int $amount
        * @param string $orderID
        * @return int
        */
       public function payin($orderID, $amount) {
           // 第一步：先把数据写入数据库
           $order = v\App::model('Order')->getByID($orderID, ['field' => 'member_id']);
           if (empty($order)) {
               return 0;  // 没有订单视为不成功
           }
           $account = v\App::model('Account')->where(['order_id' => $orderID])->count();
           if (!empty($account)) {
               return 0;  // 已经处理该笔支付，防多次调用
           }

           // 开启事务时候使用orderID做为事务的ID，防止多次处理同一笔支付开启事务
           $guardID = v\kit\Guard::start(
                           ['amount' => intval($amount), 'order_id' => $orderID, 'member_id' => $order['member_id']], ['vApp\model\Staff', 'guardPayin'], $orderID
           );  // 开始一个事务
           // 处理事务
           $rs = v\kit\Guard::doing($guardID);
           return $rs;   // 
       }


在payin函数中开启一个守护事务，在guardPayin函数中处理事务，最后再开启一个事务修复的计划任务

        v\kit\Guard::fixOne();  // 修复一个最早的事务
        
注意要使用数据守护事务必须启用redis支持、并且关闭读写分离、涉及到事务的集合请添加 guards 字段并进行索引

## 在SQL中进行事务处理

mongodb的事务只能使用程序保证，需要有很强的设计能力才能再mongodb下保证数据安全，而在sql中事务处理就简单多了，建议与金钱利益相关的数据都保持在sql中，便于处理事务。

  sql的驱动下的事务处理入下例
        
        $model = v\App::model('Account');
        $model->db()->beginBulk();  // 取得模型的数据库驱动实例，开启一个事务，事务必须保证在同一个数据库中。
        try {
            $model->data([xxxx])->addOne();
            $model->data([xxxx])->where([xxx])->upAll();
            v\App::model('xxxx)->where([xxxx])->delAll();
            $model->db()->commitBulk();  // 提交事务
        } catch (Exception $e) {
            $model->db()->rollBulk();  // 出错则回滚事务，显示回滚事务能够缩短锁库时间。
        }

  注意sql事务应该显示回滚，并且事务处于同一个库中，框架支持事务嵌套，可以方便函数之间的调用，写法不变。
