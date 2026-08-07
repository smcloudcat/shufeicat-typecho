<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Emoji 表情解析
 * 从 functions.php 分层迁移
 */

/**
 * 解析评论内容中的表情代码为图片
 * 支持阿鲁、QQ、微博、贴吧表情
 *
 * @param string $html HTML 内容
 * @param object $options 主题选项
 * @return string 处理后的 HTML 内容
 */
function shufei_parse_emoji_code($html, $options = null)
{
    if (empty($html)) return $html;

    // 支持 CDN/custom 模式
    $resourceMode = !empty($options->resourceMode) ? $options->resourceMode : 'local';
    $customCdn = !empty($options->customCdn) ? rtrim($options->customCdn, '/') : '';
    $themeUrl = !empty($options->themeUrl) ? $options->themeUrl : '';
    $emojiAssetBase = rtrim($themeUrl, '/') . '/assets/vendor/jquery-emoji';
    if ($resourceMode === 'custom' && $customCdn) {
        $emojiAssetBase = $customCdn . '/assets/vendor/jquery-emoji';
    }
    $basePath = $emojiAssetBase . '/images/emoji/';

    // 阿鲁表情: [aru_1] ~ [aru_164]
    $html = preg_replace_callback('/\[aru_(\d+)\]/', function($m) use ($basePath) {
        return '<img class="wp-smiley" src="' . $basePath . 'aru/' . $m[1] . '.png" alt="[aru_' . $m[1] . ']" />';
    }, $html);

    // QQ表情: [qq:微笑] [qq:撇嘴] 等（新格式，避免Markdown冲突）
    $qqEmojiMap = array(
        '微笑','撇嘴','色','发呆','得意','流泪','害羞','闭嘴','睡','大哭',
        '尴尬','呲牙','发怒','调皮','惊讶','难过','酷','冷汗','抓狂','吐',
        '偷笑','可爱','白眼','傲慢','饥饿','困','惊恐','流汗','憨笑','大兵',
        '奋斗','咒骂','疑问','嘘','晕','折磨','衰','骷髅','敲打','再见',
        '擦汗','抠鼻','鼓掌','嗅大了','坏笑','左哼哼','右哼哼','哈欠','鄙视','委屈',
        '可怜','阴险','亲亲','吓','快哭了','菜刀','西瓜','啤酒','篮球','乒乓',
        '咖啡','饭','猪头','玫瑰','凋谢','心','心碎','蛋糕','闪电','炸弹',
        '刀','足球','瓢虫','便便','夜晚','太阳','礼物','拥抱','强','弱',
        '握手','胜利','抱拳','勾引','拳头','差劲','爱你','NO','OK','爱情',
        '飞吻','发财','帅','雨伞','高铁左车头','车厢','高铁右车头','纸巾','右太极','左太极',
        '献吻','街舞','激动','挥动','跳绳','回头','磕头','转圈','怄火','发抖',
        '跳跳','爆筋','沙发','钱','蜡烛','枪','灯','香蕉','吻','下雨',
        '闹钟','囍','棒棒糖','面条','车','邮件','风车','药丸','奶瓶','灯笼',
        '青蛙','戒指','K歌','熊猫','喝彩','购物','多云','鞭炮','飞机','气球'
    );
    $qqPattern = '/\[qq:(' . implode('|', array_map('preg_quote', $qqEmojiMap)) . ')\]/';
    $html = preg_replace_callback($qqPattern, function($m) use ($basePath) {
        return '<img class="wp-smiley" src="' . $basePath . 'qq/' . urlencode($m[1]) . '.gif" alt="[qq:' . $m[1] . ']" />';
    }, $html);

    // 微博表情: [wb:doge] [wb:aini] 等（新格式，key为显示名，value为文件名）
    $wbEmojiMap = array(
        'doge' => 'doge', 'miao' => 'miao',
        'dog1' => 'dog1', 'dog2' => 'dog2', 'dog3' => 'dog3', 'dog4' => 'dog4',
        'dog5' => 'dog5', 'dog6' => 'dog6', 'dog7' => 'dog7', 'dog8' => 'dog8',
        'dog9' => 'dog9', 'dog10' => 'dog10', 'dog11' => 'dog11', 'dog12' => 'dog12',
        'dog13' => 'dog13', 'dog14' => 'dog14', 'dog15' => 'dog15',
        '二哈' => 'erha', '爱你' => 'aini', '奥特曼' => 'aoteman',
        '拜拜' => 'baibai', '悲伤' => 'beishang', '鄙视' => 'bishi',
        '闭嘴' => 'bizui', '馋嘴' => 'chanzui', '吃惊' => 'chijing',
        '打哈气' => 'dahaqi', '打脸' => 'dalian', '顶' => 'ding',
        '肥皂' => 'feizao', '感冒' => 'ganmao', '鼓掌' => 'guzhang',
        '哈哈' => 'haha', '害羞' => 'haixiu', '呵呵' => 'hehe',
        '黑线' => 'heixian', '哼' => 'heng', '花心' => 'huaxin',
        '挤眼' => 'jiyan', '可爱' => 'keai', '可怜' => 'kelian',
        '哭' => 'ku', '困' => 'kun', '懒得理你' => 'landelini',
        '累' => 'lei', '男孩儿' => 'nanhaier', '怒' => 'nu',
        '怒骂' => 'numa', '女孩儿' => 'nvhaier', '钱' => 'qian',
        '亲亲' => 'qinqin', '傻眼' => 'shayan', '生病' => 'shengbing',
        '神兽' => 'shenshou', '失望' => 'shiwang', '衰' => 'shuai',
        '睡觉' => 'shuijiao', '思考' => 'sikao', '太开心' => 'taikaixin',
        '偷笑' => 'touxiao', '吐' => 'tu', '兔子' => 'tuzi',
        '挖鼻屎' => 'wabishi', '委屈' => 'weiqu', '笑哭' => 'xiaoku',
        '熊猫' => 'xiongmao', '嘻嘻' => 'xixi', '嘘' => 'xu',
        '阴险' => 'yinxian', '疑问' => 'yiwen', '右哼哼' => 'youhengheng',
        '晕' => 'yun', '抓狂' => 'zhuakuang', '猪头' => 'zhutou',
        '最右' => 'zuiyou', '左哼哼' => 'zuohengheng', '给力' => 'geili',
        '互粉' => 'hufen', '囧' => 'jiong', '萌' => 'meng',
        '神马' => 'shenma', 'v5' => 'v5', '囍' => 'xi', '织' => 'zhi'
    );
    $wbKeys = array_keys($wbEmojiMap);
    $wbPattern = '/\[wb:(' . implode('|', array_map('preg_quote', $wbKeys)) . ')\]/';
    $html = preg_replace_callback($wbPattern, function($m) use ($basePath, $wbEmojiMap) {
        $filename = $wbEmojiMap[$m[1]];
        return '<img class="wp-smiley" src="' . $basePath . 'weibo/' . $filename . '.png" alt="[wb:' . $m[1] . ']" />';
    }, $html);

    // 贴吧表情: [tb:呵呵] [tb:哈哈] 等（新格式，避免Markdown #号冲突）
    $tiebaEmojiMap = array(
        '呵呵','哈哈','吐舌','太开心','笑眼','花心','小乖','乖','捂嘴笑','滑稽',
        '你懂的','不高兴','怒','汗','黑线','泪','真棒','喷','惊哭','阴险',
        '鄙视','酷','啊','狂汗','what','疑问','酸爽','呀咩爹','委屈','惊讶',
        '睡觉','笑尿','挖鼻','吐','犀利','小红脸','懒得理','勉强','爱心','心碎',
        '玫瑰','礼物','彩虹','太阳','星星月亮','钱币','茶杯','蛋糕','大拇指','胜利',
        'haha','OK','沙发','手纸','香蕉','便便','药丸','红领巾','蜡烛','音乐','灯泡'
    );
    $tiebaPattern = '/\[tb:(' . implode('|', array_map('preg_quote', $tiebaEmojiMap)) . ')\]/';
    $html = preg_replace_callback($tiebaPattern, function($m) use ($basePath) {
        return '<img class="wp-smiley" src="' . $basePath . 'tieba/' . urlencode($m[1]) . '.png" alt="[tb:' . $m[1] . ']" />';
    }, $html);

    return $html;
}

