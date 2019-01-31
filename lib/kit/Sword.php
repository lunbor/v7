<?php

/*
 * v framework
 * 
 * v框架扩展敏感词过滤服务
 * 
 * @copyright daojon.com
 * @author daojon <daojon@live.com>
 * @version SVN: $Id: Sword.php 37809 2018-01-19 07:22:39Z wangwenxing $
 */

namespace v\kit;

class_exists('v') or die(header('HTTP/1.1 403 Forbidden'));

use v;

final class Sword extends v\ServiceFactory {

    /**
     * 服务提供对象名
     * @var string
     */
    protected static $objname = 'v\kit\SwordService';

    /**
     * 服务提供对象
     * @var object
     */
    protected static $object;

}

/**
 * 敏感词实际服务对象
 */
class SwordService extends v\Service {

    /**
     * 需过滤的关键词
     * @var array
     */
    protected $words = [
        '她妈' => '*',
        '它妈' => '*',
        '他妈' => '*',
        '你妈' => '*',
        '去死' => '*',
        '贱人' => '*',
        '共产党' => '*',
        '我党' => '*',
        '傻逼' => '*',
        '傻B' => '*',
        '江泽民' => '*',
        '九评' => '*',
        '办证' => '*',
        '辦證' => '*',
        '法轮功' => '*',
        '李洪志' => '*',
        '肉棍' => '*',
        '淫靡' => '*',
        '淫水' => '*',
        '反华' => '*',
        '反共' => '*',
        '宪章' => '*',
        '迷药' => '*',
        '迷昏药' => '*',
        '窃听器' => '*',
        '六合彩' => '*',
        '买卖枪支' => '*',
        '退党' => '*',
        '疆独' => '*',
        '藏独' => '*',
        '三唑仑' => '*',
        '麻醉药' => '*',
        '麻醉乙醚' => '*',
        '短信群发器' => '*',
        '色情服务' => '*',
        '摇头丸' => '*',
        '军长发威' => '*',
        'PK黑社会' => '*',
        '恶搞晚会' => '*',
        '枪决女犯' => '*',
        '投毒杀人' => '*',
        '强硬发言' => '*',
        '出售假币' => '*',
        '监听王' => '*',
        '昏药' => '*',
        '侦探设备' => '*',
        '麻醉钢枪' => '*',
        '官商勾结' => '*',
        '升达毕业证' => '*',
        '手机复制' => '*',
        '自杀指南' => '*',
        '自杀手册' => '*',
        '蒙汗药' => '*',
        '迷香' => '*',
        '失意药' => '*',
        '迷奸药' => '*',
        '透视眼镜' => '*',
        '偷拍' => '*',
        '手枪' => '*',
        '激情小电影' => '*',
        '黄色小电影' => '*',
        '色情小电影' => '*',
        '高校群体事件' => '*',
        '大学骚乱' => '*',
        '高校骚乱' => '*',
        '催情药' => '*',
        '拍肩神药' => '*',
        '春药' => '*',
        '窃听器材' => '*',
        '身份证生成器' => '*',
        '麻醉枪' => '*',
        '办理证件' => '*',
        '办理文凭' => '*',
        '枪支' => '*',
        '弹药' => '*',
        '血腥图片' => '*',
        '反政府' => '*',
        '禁书' => '*',
        '特码' => '*',
        '成人片' => '*',
        '成人电影' => '*',
        '换妻' => '*',
        '三级片' => '*',
        '炸药' => '*',
        '仿真枪' => '*',
        '猎枪' => '*',
        '真枪' => '*',
        '阻击枪' => '*',
        '军用枪' => '*',
        '狩猎器材' => '*',
        '三用弩' => '*',
        '麻醉' => '*',
        '挨了一炮' => '*',
        '爱液横流' => '*',
        '安街逆' => '*',
        '安局办公楼' => '*',
        '安局豪华' => '*',
        '安门事' => '*',
        '安眠藥' => '*',
        '八九民' => '*',
        '八九学' => '*',
        '八九政治' => '*',
        '办本科' => '*',
        '办理本科' => '*',
        '办理各种' => '*',
        '办理票据' => '*',
        '办理真实' => '*',
        '办理证书' => '*',
        '办理资格' => '*',
        '办文凭' => '*',
        '报复执法' => '*',
        '爆发骚' => '*',
        '被打死' => '*',
        '被中共' => '*',
        '本无码' => '*',
        '毕业證' => '*',
        '变牌绝' => '*',
        '辩词与梦' => '*',
        '冰毒' => '*',
        '冰火毒' => '*',
        '冰火佳' => '*',
        '冰火九重' => '*',
        '冰火漫' => '*',
        '冰淫传' => '*',
        '冰在火上' => '*',
        '波推龙' => '*',
        '博彩娱' => '*',
        '博会暂停' => '*',
        '博园区伪' => '*',
        '不查都' => '*',
        '不查全' => '*',
        '不思四化' => '*',
        '布卖淫女' => '*',
        '部忙组阁' => '*',
        '部是这样' => '*',
        '才知道只生' => '*',
        '财众科技' => '*',
        '采花堂' => '*',
        '踩踏事' => '*',
        '苍山兰' => '*',
        '苍蝇水' => '*',
        '藏春阁' => '*',
        '藏獨' => '*',
        '操了嫂' => '*',
        '操嫂子' => '*',
        '策没有不' => '*',
        '插屁屁' => '*',
        '察象蚂' => '*',
        '拆迁灭' => '*',
        '车牌隐' => '*',
        '成人电' => '*',
        '成人卡通' => '*',
        '成人聊' => '*',
        '成人视' => '*',
        '成人图' => '*',
        '成人文' => '*',
        '成人小' => '*',
        '城管灭' => '*',
        '惩公安' => '*',
        '惩贪难' => '*',
        '充气娃' => '*',
        '出售发票' => '*',
        '春水横溢' => '*',
        '纯度白' => '*',
        '纯度黄' => '*',
        '次通过考' => '*',
        '催眠水' => '*',
        '催情粉' => '*',
        '催情藥' => '*',
        '挫仑' => '*',
        '打标语' => '*',
        '打死人' => '*',
        '打砸办公' => '*',
        '大鸡巴' => '*',
        '大雞巴' => '*',
        '大揭露' => '*',
        '大奶子' => '*',
        '大批贪官' => '*',
        '大肉棒' => '*',
        '大嘴歌' => '*',
        '代办发票' => '*',
        '代办各' => '*',
        '代办文' => '*',
        '代办学' => '*',
        '代办制' => '*',
        '代辦' => '*',
        '代表烦' => '*',
        '代開' => '*',
        '代考' => '*',
        '代理发票' => '*',
        '代理票据' => '*',
        '代您考' => '*',
        '代写毕' => '*',
        '代写论' => '*',
        '代孕' => '*',
        '贷办' => '*',
        '贷借款' => '*',
        '贷开' => '*',
        '当代七整' => '*',
        '当官要精' => '*',
        '当官在于' => '*',
        '党的官' => '*',
        '党后萎' => '*',
        '党前干劲' => '*',
        '刀架保安' => '*',
        '导的情人' => '*',
        '导叫失' => '*',
        '导人的最' => '*',
        '导人最' => '*',
        '导小商' => '*',
        '到花心' => '*',
        '得财兼' => '*',
        '的同修' => '*',
        '灯草和' => '*',
        '等级證' => '*',
        '等屁民' => '*',
        '等人老百' => '*',
        '等人是老' => '*',
        '等人手术' => '*',
        '邓爷爷转' => '*',
        '邓玉娇' => '*',
        '地产之歌' => '*',
        '地下先烈' => '*',
        '地震哥' => '*',
        '递纸死' => '*',
        '点数优惠' => '*',
        '电狗' => '*',
        '电话监' => '*',
        '电鸡' => '*',
        '甸果敢' => '*',
        '蝶舞按' => '*',
        '丁香社' => '*',
        '丁子霖' => '*',
        '顶花心' => '*',
        '东北独立' => '*',
        '东复活' => '*',
        '东京热' => '*',
        '東京熱' => '*',
        '洞小口紧' => '*',
        '都当警' => '*',
        '都当小姐' => '*',
        '都进中央' => '*',
        '毒蛇钻' => '*',
        '独立台湾' => '*',
        '赌球网' => '*',
        '短信截' => '*',
        '多美康' => '*',
        '躲猫猫' => '*',
        '俄羅斯' => '*',
        '恶势力操' => '*',
        '恶势力插' => '*',
        '恩氟烷' => '*',
        '儿园惨' => '*',
        '儿园砍' => '*',
        '儿园杀' => '*',
        '儿园凶' => '*',
        '二奶大' => '*',
        '发牌绝' => '*',
        '发票出' => '*',
        '发票代' => '*',
        '发票销' => '*',
        '發票' => '*',
        '法车仑' => '*',
        '法伦功' => '*',
        '法轮' => '*',
        '法轮佛' => '*',
        '法维权' => '*',
        '法一轮' => '*',
        '法院给废' => '*',
        '法正乾' => '*',
        '反测速雷' => '*',
        '反雷达测' => '*',
        '反屏蔽' => '*',
        '范燕琼' => '*',
        '方迷香' => '*',
        '防电子眼' => '*',
        '防身药水' => '*',
        '房贷给废' => '*',
        '仿真证' => '*',
        '诽谤罪' => '*',
        '费私服' => '*',
        '封锁消' => '*',
        '佛同修' => '*',
        '夫妻交换' => '*',
        '福尔马林' => '*',
        '福娃的預' => '*',
        '福娃頭上' => '*',
        '福香巴' => '*',
        '府包庇' => '*',
        '府集中领' => '*',
        '妇销魂' => '*',
        '附送枪' => '*',
        '复印件生' => '*',
        '复印件制' => '*',
        '富民穷' => '*',
        '富婆给废' => '*',
        '改号软件' => '*',
        '感扑克' => '*',
        '冈本真' => '*',
        '肛交' => '*',
        '肛门是邻' => '*',
        '岡本真' => '*',
        '钢针狗' => '*',
        '钢珠枪' => '*',
        '港澳博球' => '*',
        '港馬會' => '*',
        '港鑫華' => '*',
        '高就在政' => '*',
        '高考黑' => '*',
        '高莺莺' => '*',
        '搞媛交' => '*',
        '告长期' => '*',
        '告洋状' => '*',
        '格证考试' => '*',
        '各类考试' => '*',
        '各类文凭' => '*',
        '跟踪器' => '*',
        '工程吞得' => '*',
        '工力人' => '*',
        '公安错打' => '*',
        '公安网监' => '*',
        '公开小姐' => '*',
        '攻官小姐' => '*',
        '共狗' => '*',
        '共王储' => '*',
        '狗粮' => '*',
        '狗屁专家' => '*',
        '鼓动一些' => '*',
        '乖乖粉' => '*',
        '官商勾' => '*',
        '官也不容' => '*',
        '官因发帖' => '*',
        '光学真题' => '*',
        '跪真相' => '*',
        '滚圆大乳' => '*',
        '国际投注' => '*',
        '国家妓' => '*',
        '国家软弱' => '*',
        '国家吞得' => '*',
        '国库折' => '*',
        '国一九五七' => '*',
        '國內美' => '*',
        '哈药直销' => '*',
        '海访民' => '*',
        '豪圈钱' => '*',
        '号屏蔽器' => '*',
        '和狗交' => '*',
        '和狗性' => '*',
        '和狗做' => '*',
        '黑火药的' => '*',
        '红色恐怖' => '*',
        '红外透视' => '*',
        '紅色恐' => '*',
        '胡江内斗' => '*',
        '胡紧套' => '*',
        '胡錦濤' => '*',
        '胡适眼' => '*',
        '胡耀邦' => '*',
        '湖淫娘' => '*',
        '虎头猎' => '*',
        '华国锋' => '*',
        '华门开' => '*',
        '化学扫盲' => '*',
        '划老公' => '*',
        '还会吹萧' => '*',
        '还看锦涛' => '*',
        '环球证件' => '*',
        '皇冠投注' => '*',
        '浑圆豪乳' => '*',
        '活不起' => '*',
        '火车也疯' => '*',
        '机定位器' => '*',
        '机号定' => '*',
        '机号卫' => '*',
        '机卡密' => '*',
        '机屏蔽器' => '*',
        '基本靠吼' => '*',
        '绩过后付' => '*',
        '激情电' => '*',
        '激情短' => '*',
        '激情妹' => '*',
        '激情炮' => '*',
        '级办理' => '*',
        '级答案' => '*',
        '急需嫖' => '*',
        '集体打砸' => '*',
        '集体腐' => '*',
        '挤乳汁' => '*',
        '擠乳汁' => '*',
        '佳静安定' => '*',
        '家一样饱' => '*',
        '家属被打' => '*',
        '甲虫跳' => '*',
        '甲流了' => '*',
        '奸成瘾' => '*',
        '兼职上门' => '*',
        '监听器' => '*',
        '简易炸' => '*',
        '江胡内斗' => '*',
        '江太上' => '*',
        '江系人' => '*',
        '江贼民' => '*',
        '疆獨' => '*',
        '蒋彦永' => '*',
        '叫自慰' => '*',
        '揭贪难' => '*',
        '姐包夜' => '*',
        '姐服务' => '*',
        '姐兼职' => '*',
        '姐上门' => '*',
        '金扎金' => '*',
        '金钟气' => '*',
        '津大地震' => '*',
        '津地震' => '*',
        '进来的罪' => '*',
        '京地震' => '*',
        '京要地震' => '*',
        '经典谎言' => '*',
        '精子射在' => '*',
        '警察被' => '*',
        '警察的幌' => '*',
        '警察殴打' => '*',
        '警察说保' => '*',
        '警车雷达' => '*',
        '警方包庇' => '*',
        '警用品' => '*',
        '径步枪' => '*',
        '敬请忍' => '*',
        '究生答案' => '*',
        '九龙论坛' => '*',
        '九评共' => '*',
        '酒象喝汤' => '*',
        '酒像喝汤' => '*',
        '就爱插' => '*',
        '就要色' => '*',
        '举国体' => '*',
        '巨乳' => '*',
        '据说全民' => '*',
        '绝食声' => '*',
        '军刺' => '*',
        '军品特' => '*',
        '军用手' => '*',
        '开邓选' => '*',
        '开锁工具' => '*',
        '開碼' => '*',
        '開票' => '*',
        '砍杀幼' => '*',
        '砍伤儿' => '*',
        '康没有不' => '*',
        '康跳楼' => '*',
        '磕彰' => '*',
        '克分析' => '*',
        '克千术' => '*',
        '克透视' => '*',
        '空和雅典' => '*',
        '孔摄像' => '*',
        '浪穴' => '*',
        '猎枪' => '*',
        '猎槍' => '*',
        '獵槍' => '*',
        '乱奸' => '*',
        '乱伦类' => '*',
        '乱伦小' => '*',
        '亂倫' => '*',
        '伦理大' => '*',
        '伦理电影' => '*',
        '伦理毛' => '*',
        '伦理片' => '*',
        '轮功' => '*',
        '轮手枪' => '*',
        '论文代' => '*',
        '罗斯小姐' => '*',
        '裸聊网' => '*',
        '裸舞视' => '*',
        '落霞缀' => '*',
        '麻古' => '*',
        '麻果配' => '*',
        '麻果丸' => '*',
        '麻将透' => '*',
        '麻醉狗' => '*',
        '麻醉槍' => '*',
        '麻醉藥' => '*',
        '卖发票' => '*',
        '卖银行卡' => '*',
        '卖自考' => '*',
        '漫步丝' => '*',
        '忙爱国' => '*',
        '猫眼工具' => '*',
        '媒体封锁' => '*',
        '每周一死' => '*',
        '美艳少妇' => '*',
        '妹按摩' => '*',
        '妹上门' => '*',
        '门按摩' => '*',
        '门保健' => '*',
        '門服務' => '*',
        '氓培训' => '*',
        '蒙汗药' => '*',
        '迷幻型' => '*',
        '迷幻药' => '*',
        '迷幻藥' => '*',
        '迷昏口' => '*',
        '迷昏藥' => '*',
        '迷魂香' => '*',
        '迷魂药' => '*',
        '迷魂藥' => '*',
        '迷情水' => '*',
        '迷情药' => '*',
        '迷藥' => '*',
        '谜奸药' => '*',
        '蜜穴' => '*',
        '民抗议' => '*',
        '摩小姐' => '*',
        '母乳家' => '*',
        '内射' => '*',
        '嫩穴' => '*',
        '嫩阴' => '*',
        '娘两腿之间' => '*',
        '妞上门' => '*',
        '浓精' => '*',
        '女被人家搞' => '*',
        '女激情' => '*',
        '女人和狗' => '*',
        '女上门' => '*',
        '女優' => '*',
        '全真证' => '*',
        '人权律' => '*',
        '人体艺' => '*',
        '人游行' => '*',
        '人在云上' => '*',
        '人真钱' => '*',
        '柔胸粉' => '*',
        '肉洞' => '*',
        '乳交' => '*',
        '骚妇' => '*',
        '骚浪' => '*',
        '骚穴' => '*',
        '骚嘴' => '*',
        '色电影' => '*',
        '色妹妹' => '*',
        '色视频' => '*',
        '色小说' => '*',
        '失身水' => '*',
        '管理' => '*',
        '众聚' => '*',
        '盛趣' => '*',
        '深度' => '*',
    ];

    /**
     * 过滤数据
     * @param string $str
     * @return string
     */
    public function filter($str) {
        $str = strtr($str, $this->words);
        return $str;
    }

    /**
     * 添加过滤词
     * @param string|array $word
     * @return $this
     */
    public function addWord($word) {
        $word = arrayval($word);
        $word = array_fill_keys($word, '*');
        array_extend($this->words, $word);
        return $this;
    }

    /**
     * 字符串是否合法(不包含敏感字符)
     * @param string $str 需要校验的字符串
     * @return bool true合法 false不合法
     */
    public function isValid($str) {
        $words = implode(array_keys($this->words), '|');
        return !preg_match("/$words/i", $str);
    }

}

?>