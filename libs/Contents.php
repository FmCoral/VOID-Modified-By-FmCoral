<?php
/**
 * Contents.php
 * 
 * 解析器等内容处理相关
 * 
 * @author      熊猫小A
 * @version     2019-01-15 0.01
 */

Class Contents
{
    /**
     * 根据 cid 返回文章对象
     * 
     * @return Widget_Abstract_Contents
     */
    public static function getPost($cid)
    {
        $db = Typecho_Db::get();
        $post = Widget_Abstract_Contents::alloc();
        $db->fetchRow($post->select()
            ->where("cid = ?", $cid)
            ->limit(1),
            array($post, 'push'));
        return $post;
    }

    /**
     * 根据 cid 返回评论对象
     * 
     * @return Widget_Abstract_Comments
     */
    public static function getComment($coid)
    {
        $db = Typecho_Db::get();
        $comment = Widget_Abstract_Comments::alloc();
        $db->fetchRow($comment->select()
            ->where("coid = ?", $coid)
            ->limit(1),
            array($comment, 'push'));
        return $comment;
    }

    /**
     * 根据 mid 返回 meta 对象
     * 
     * @return Widget_Abstract_Metas
     */
    public static function getMeta($mid)
    {
        $db = Typecho_Db::get();
        $meta = Widget_Abstract_Metas::alloc();
        $db->fetchRow($meta->select()
            ->where("mid = ?", $mid)
            ->limit(1),
            array($meta, 'push'));
        return $meta;
    }

    /**
     * 输出完备的标题
     * 
     * @return void
     */
    public static function title(Widget_Archive $archive)
    {
        $archive->archiveTitle(array(
            'category'  =>  '分类 %s 下的文章',
            'search'    =>  '包含关键字 %s 的文章',
            'tag'       =>  '标签 %s 下的文章',
            'author'    =>  '%s 发布的文章'
        ), '', ' - ');
        Helper::options()->title();
    }

    /**
     * 内容解析点钩子
     * 目录解析移至前端完成
     */
    static public function contentEx($data, $widget, $last)
    {
        $text = empty($last)?$data:$last;
        if ($widget instanceof Widget_Archive) {
            $text = self::parseRuby($text);
            $text = self::parseFancyBox($text, $widget->parameter->__get('type') == 'feed');
            $text = self::parseBiaoQing($text);
            $text = self::parsePhotoSet($text);
            $text = self::parseNotice($text);
            $text = self::parseHeader($text);
        }
        return $text;
    }

    /**
     * 摘要解析点钩子
     */
    static public function excerptEx($data, $widget, $last)
    {
        $text = empty($last)?$data:$last;
        if ($widget instanceof Widget_Archive) {
            $text = self::parseRuby($text);
            $text = self::parseBiaoQing($text);
            $text = self::parseNotice($text);
            // 去除照片集标记
            $text = str_replace('[photos]', '', $text);
            $text = str_replace('[/photos]', '', $text);
        }
        return $text;
    }

    /**
     * 解析文章内 h2 ~ h5 元素
     * 
     * @return string
     */
    static public function parseHeader($content)
    {
        $reg='/\<h([2-6])(.*?)\>(.*?)\<\/h.*?\>/s';
        $new = preg_replace_callback($reg, array('Contents', 'parseHeaderCallback'), $content);
        return $new;
    }

    /**
     * 为内容中的 h2-h6 元素编号
     */
    static private $CurrentTocID = 0;
    static public function parseHeaderCallback($matchs)
    {
        // 增加单独标记，否则冲突
        $id = 'toc_'.(self::$CurrentTocID++);
        return '<h'.$matchs[1].$matchs[2].' id="'.$id.'">'.$matchs[3].'</h'.$matchs[1].'>';
    }

    /**
     * 解析提示块
     * 
     * @return string
     */
    static public function parseNotice($content)
    {
        $reg='/\[notice.*?\](.*?)\[\/notice\]/s';
        $rp='<p class="notice">${1}</p>';
        $new=preg_replace($reg,$rp,$content);
        return $new;
    }

    /**
     * 解析照片集
     *
     * @return string
     */
    static public function parsePhotoSet($content)
    {
        $setting = $GLOBALS['VOIDSetting'];

        // 清除无用 tag
        $reg = '/\[photos(.*?)\/photos\]/s';
        $new = preg_replace_callback($reg, array('Contents', 'parsePhotoSetCallBack'), $content);
        $reg='/<p>\[photos.*?\](.*?)\[\/photos\]<\/p>/s';
        $rp='';

        if($setting['largePhotoSet']) {
            $rp = '<div class="photos large">${1}</div>';
        }
        else {
            $rp = '<div class="photos">${1}</div>';
        }

        $new=preg_replace($reg, $rp, $new);
        return $new;
    }

    /**
     * 解析照片集回调函数
     * 
     * @return string
     */
    private static function parsePhotoSetCallBack($match)
    {
        return '[photos'. str_replace(['<br>', '<p>', '</p>'], '', $match[1]) .'/photos]';
    }

    /**
     * 解析表情
     * 
     * @return string
     */
    static public function parseBiaoQing($content)
    {
        $content = preg_replace_callback('/\:\:\(\s*(呵呵|哈哈|吐舌|太开心|笑眼|花心|小乖|乖|捂嘴笑|滑稽|你懂的|不高兴|怒|汗|黑线|泪|真棒|喷|惊哭|阴险|鄙视|酷|啊|狂汗|what|疑问|酸爽|呀咩爹|委屈|惊讶|睡觉|笑尿|挖鼻|吐|犀利|小红脸|懒得理|勉强|爱心|心碎|玫瑰|礼物|彩虹|太阳|星星月亮|钱币|茶杯|蛋糕|大拇指|胜利|haha|OK|沙发|手纸|香蕉|便便|药丸|红领巾|蜡烛|音乐|灯泡|开心|钱|咦|呼|冷|生气|弱|吐血)\s*\)/is',
            array('Contents', 'parsePaopaoBiaoqingCallback'), $content);
        $content = preg_replace_callback('/\:\@\(\s*(高兴|小怒|脸红|内伤|装大款|赞一个|害羞|汗|吐血倒地|深思|不高兴|无语|亲亲|口水|尴尬|中指|想一想|哭泣|便便|献花|皱眉|傻笑|狂汗|吐|喷水|看不见|鼓掌|阴暗|长草|献黄瓜|邪恶|期待|得意|吐舌|喷血|无所谓|观察|暗地观察|肿包|中枪|大囧|呲牙|抠鼻|不说话|咽气|欢呼|锁眉|蜡烛|坐等|击掌|惊喜|喜极而泣|抽烟|不出所料|愤怒|无奈|黑线|投降|看热闹|扇耳光|小眼睛|中刀)\s*\)/is',
            array('Contents', 'parseAruBiaoqingCallback'), $content);
        $content = preg_replace_callback('/\:\&\(\s*(.*?)\s*\)/is',
            array('Contents', 'parseQuyinBiaoqingCallback'), $content);
            $content = preg_replace_callback('/\:\$\(\s*(.*?)\s*\)/is',
            array('Contents', 'parseBilibiliBiaoqingCallback'), $content);
        $content = preg_replace_callback('/\:\!\(\s*(.*?)\s*\)/is',
            array('Contents', 'parseMihoyoBiaoqingCallback'), $content);
        $content = preg_replace_callback('/\:\@\(\s*(.*?)\s*\)/is',
            array('Contents', 'parseDouyinBiaoqingCallback'), $content);

        return $content;
    }

    /**
     * 泡泡表情回调函数
     * 
     * @return string
     */
    private static function parsePaopaoBiaoqingCallback($match)
    {
        return '<img class="biaoqing" src="/usr/themes/VOID/assets/libs/owo/biaoqing/paopao/'. str_replace('%', '', urlencode($match[1])) . '_2x.png">';
    }

    /**
     * 阿鲁表情回调函数
     * 
     * @return string
     */
    private static function parseAruBiaoqingCallback($match)
    {
        return '<img class="biaoqing" src="/usr/themes/VOID/assets/libs/owo/biaoqing/aru/'. str_replace('%', '', urlencode($match[1])) . '_2x.png">';
    }

    /**
     * 蛆音娘表情回调函数
     * 
     * @return string
     */
    private static function parseQuyinBiaoqingCallback($match)
    {
        return '<img class="biaoqing" src="/usr/themes/VOID/assets/libs/owo/biaoqing/quyin/'. str_replace('%', '', urlencode($match[1])) . '.png">';
    }

    /**
     * 哔哩哔哩表情回调函数
     *
     * @return string
     */
    private static function parseBilibiliBiaoqingCallback($match)
    {
        return '<img class="biaoqing" src="/usr/themes/VOID/assets/libs/owo/biaoqing/2233/'. str_replace('%', '', urlencode($match[1])) . '.png">';
    }

    /**
     * mihoyo表情回调函数
     *
     * @return string
     */
     private static function parseMihoyoBiaoqingCallback($match)
     {
         return '<img class="biaoqing" src="/usr/themes/VOID/assets/libs/owo/biaoqing/mihoyo/'. str_replace('%', '', urlencode($match[1])) . '.png">';
     }

    /**
     * 抖音表情回调函数
     *
     * @return string
     */
    private static function parseDouyinBiaoqingCallback($match)
    {
        $map = array(
        '微笑' => '001_E5BEAEE7AC91.png',
        '色' => '002_E889B2.png',
        '发呆' => '003_E58F91E59186.png',
        '酷拽' => '004_E985B7E68BBD.png',
        '抠鼻' => '005_E68AA0E9BCBB.png',
        '流泪' => '006_E6B581E6B3AA.png',
        '捂脸' => '007_E68D82E884B8.png',
        '发怒' => '008_E58F91E68092.png',
        '呲牙' => '009_E591B2E78999.png',
        '尬笑' => '010_E5B0ACE7AC91.png',
        '害羞' => '011_E5AEB3E7BE9E.png',
        '调皮' => '012_E8B083E79AAE.png',
        '舔屏' => '013_E88894E5B18F.png',
        '看' => '014_E79C8B.png',
        '爱心' => '015_E788B1E5BF83.png',
        '比心' => '016_E6AF94E5BF83.png',
        '赞' => '017_E8B59E.png',
        '鼓掌' => '018_E9BC93E68E8C.png',
        '感谢' => '019_E6849FE8B0A2.png',
        '抱抱你' => '020_E68AB1E68AB1E4BDA0.png',
        '玫瑰' => '021_E78EABE791B0.png',
        '尴尬流汗' => '022_E5B0B4E5B0ACE6B581E6B197.png',
        '戳手手' => '023_E688B3E6898BE6898B.png',
        '星星眼' => '024_E6989FE6989FE79CBC.png',
        '杀马特' => '025_E69D80E9A9ACE789B9.png',
        '黄脸干杯' => '026_E9BB84E884B8E5B9B2E69DAF.png',
        '抱紧自己' => '027_E68AB1E7B4A7E887AAE5B7B1.png',
        '拜拜' => '028_E68B9CE68B9C.png',
        '热化了' => '029_E783ADE58C96E4BA86.png',
        '黄脸祈祷' => '030_E9BB84E884B8E7A588E7A5B7.png',
        '懵' => '031_E687B5.png',
        '举手' => '032_E4B8BEE6898B.png',
        '加功德' => '033_E58AA0E58A9FE5BEB7.png',
        '摊手' => '034_E6918AE6898B.png',
        '无语流汗' => '035_E697A0E8AFADE6B581E6B197.png',
        '续火花吧' => '036_E7BBADE781ABE88AB1E590A7.png',
        '点火' => '037_E782B9E781AB.png',
        '哭哭' => '038_E593ADE593AD.png',
        '吐舌小狗' => '039_E59090E8888CE5B08FE78B97.png',
        '送花' => '040_E98081E88AB1.png',
        '爱心手' => '041_E788B1E5BF83E6898B.png',
        '贴贴' => '042_E8B4B4E8B4B4.png',
        '灵机一动' => '043_E781B5E69CBAE4B880E58AA8.png',
        '耶' => '044_E880B6.png',
        '打脸' => '045_E68993E884B8.png',
        '大笑' => '046_E5A4A7E7AC91.png',
        '机智' => '047_E69CBAE699BA.png',
        '送心' => '048_E98081E5BF83.png',
        '666' => '049_666.png',
        '闭嘴' => '050_E997ADE598B4.png',
        '来看我' => '051_E69DA5E79C8BE68891.png',
        '一起加油' => '052_E4B880E8B5B7E58AA0E6B2B9.png',
        '哈欠' => '053_E59388E6ACA0.png',
        '震惊' => '054_E99C87E6838A.png',
        '晕' => '055_E69995.png',
        '衰' => '056_E8A1B0.png',
        '困' => '057_E59BB0.png',
        '疑问' => '058_E79691E997AE.png',
        '泣不成声' => '059_E6B3A3E4B88DE68890E5A3B0.png',
        '小鼓掌' => '060_E5B08FE9BC93E68E8C.png',
        '大金牙' => '061_E5A4A7E98791E78999.png',
        '偷笑' => '062_E581B7E7AC91.png',
        '石化' => '063_E79FB3E58C96.png',
        '思考' => '064_E6809DE88083.png',
        '吐血' => '065_E59090E8A180.png',
        '可怜' => '066_E58FAFE6809C.png',
        '嘘' => '067_E59898.png',
        '撇嘴' => '068_E69287E598B4.png',
        '笑哭' => '069_E7AC91E593AD.png',
        '奸笑' => '070_E5A5B8E7AC91.png',
        '得意' => '071_E5BE97E6848F.png',
        '憨笑' => '072_E686A8E7AC91.png',
        '坏笑' => '073_E59D8FE7AC91.png',
        '抓狂' => '074_E68A93E78B82.png',
        '泪奔' => '075_E6B3AAE5A594.png',
        '钱' => '076_E992B1.png',
        '恐惧' => '077_E68190E683A7.png',
        '愉快' => '078_E68489E5BFAB.png',
        '快哭了' => '079_E5BFABE593ADE4BA86.png',
        '翻白眼' => '080_E7BFBBE799BDE79CBC.png',
        '互粉' => '081_E4BA92E7B289.png',
        '我想静静' => '082_E68891E683B3E99D99E99D99.png',
        '委屈' => '083_E5A794E5B188.png',
        '鄙视' => '084_E98499E8A786.png',
        '飞吻' => '085_E9A39EE590BB.png',
        '再见' => '086_E5868DE8A781.png',
        '紫薇别走' => '087_E7B4ABE89687E588ABE8B5B0.png',
        '听歌' => '088_E590ACE6AD8C.png',
        '求抱抱' => '089_E6B182E68AB1E68AB1.png',
        '绝望的凝视' => '090_E7BB9DE69C9BE79A84E5879DE8A786.png',
        '不失礼貌的微笑' => '091_E4B88DE5A4B1E7A4BCE8B28CE79A84E5BEAEE7AC91.png',
        '不看' => '092_E4B88DE79C8B.png',
        '裂开' => '093_E8A382E5BC80.png',
        '干饭人' => '094_E5B9B2E9A5ADE4BABA.png',
        '庆祝' => '095_E5BA86E7A59D.png',
        '吐舌' => '096_E59090E8888C.png',
        '呆无辜' => '097_E59186E697A0E8BE9C.png',
        '白眼' => '098_E799BDE79CBC.png',
        '猪头' => '099_E78CAAE5A4B4.png',
        '冷漠' => '100_E586B7E6BCA0.png',
        '暗中观察' => '101_E69A97E4B8ADE8A782E5AF9F.png',
        '二哈' => '102_E4BA8CE59388.png',
        '菜狗' => '103_E88F9CE78B97.png',
        '黑脸' => '104_E9BB91E884B8.png',
        '展开说说' => '105_E5B195E5BC80E8AFB4E8AFB4.png',
        '蜜蜂狗' => '106_E89C9CE89C82E78B97.png',
        '柴犬' => '107_E69FB4E78AAC.png',
        '摸头' => '108_E691B8E5A4B4.png',
        '皱眉' => '109_E79AB1E79C89.png',
        '擦汗' => '110_E693A6E6B197.png',
        '红脸' => '111_E7BAA2E884B8.png',
        '做鬼脸' => '112_E5819AE9ACBCE884B8.png',
        '强' => '113_E5BCBA.png',
        '如花' => '114_E5A682E88AB1.png',
        '吐' => '115_E59090.png',
        '惊喜' => '116_E6838AE5969C.png',
        '敲打' => '117_E695B2E68993.png',
        '奋斗' => '118_E5A58BE69697.png',
        '吐彩虹' => '119_E59090E5BDA9E899B9.png',
        '大哭' => '120_E5A4A7E593AD.png',
        '嘿哈' => '121_E598BFE59388.png',
        '惊恐' => '122_E6838AE68190.png',
        '囧' => '123_E59BA7.png',
        '难过' => '124_E99ABEE8BF87.png',
        '斜眼' => '125_E6969CE79CBC.png',
        '阴险' => '126_E998B4E999A9.png',
        '悠闲' => '127_E682A0E997B2.png',
        '咒骂' => '128_E59292E9AA82.png',
        '吃瓜群众' => '129_E59083E7939CE7BEA4E4BC97.png',
        '绿帽子' => '130_E7BBBFE5B8BDE5AD90.png',
        '敢怒不敢言' => '131_E695A2E68092E4B88DE695A2E8A880.png',
        '求求了' => '132_E6B182E6B182E4BA86.png',
        '眼含热泪' => '133_E79CBCE590ABE783ADE6B3AA.png',
        '叹气' => '134_E58FB9E6B094.png',
        '好开心' => '135_E5A5BDE5BC80E5BF83.png',
        '不是吧' => '136_E4B88DE698AFE590A7.png',
        '鞠躬' => '137_E99EA0E8BAAC.png',
        '躺平' => '138_E8BABAE5B9B3.png',
        '九转大肠' => '139_E4B99DE8BDACE5A4A7E882A0.png',
        '不你不想' => '140_E4B88DE4BDA0E4B88DE683B3.png',
        '一头乱麻' => '141_E4B880E5A4B4E4B9B1E9BABB.png',
        'kisskiss' => '142_kisskiss.png',
        '你不大行' => '143_E4BDA0E4B88DE5A4A7E8A18C.png',
        '噢买尬' => '144_E599A2E4B9B0E5B0AC.png',
        '宕机' => '145_E5AE95E69CBA.png',
        '苦涩' => '146_E88BA6E6B6A9.png',
        '逞强落泪' => '147_E9809EE5BCBAE890BDE6B3AA.png',
        '求机位-黄脸' => '148_E6B182E69CBAE4BD8D-E9BB84E884B8.png',
        '求机位3' => '149_E6B182E69CBAE4BD8D3.png',
        '点赞' => '150_E782B9E8B59E.png',
        '精选' => '151_E7B2BEE98089.png',
        '强壮' => '152_E5BCBAE5A3AE.png',
        '碰拳' => '153_E7A2B0E68BB3.png',
        'OK' => '154_OK.png',
        '击掌' => '155_E587BBE68E8C.png',
        '左上' => '156_E5B7A6E4B88A.png',
        '握手' => '157_E68FA1E6898B.png',
        '抱拳' => '158_E68AB1E68BB3.png',
        '勾引' => '159_E58BBEE5BC95.png',
        '拳头' => '160_E68BB3E5A4B4.png',
        '弱' => '161_E5BCB1.png',
        '胜利' => '162_E8839CE588A9.png',
        '右边' => '163_E58FB3E8BEB9.png',
        '左边' => '164_E5B7A6E8BEB9.png',
        '嘴唇' => '165_E598B4E59487.png',
        '心碎' => '166_E5BF83E7A28E.png',
        '凋谢' => '167_E5878BE8B0A2.png',
        '愤怒' => '168_E684A4E68092.png',
        '垃圾' => '169_E59E83E59CBE.png',
        '啤酒' => '170_E595A4E98592.png',
        '咖啡' => '171_E59296E595A1.png',
        '蛋糕' => '172_E89B8BE7B395.png',
        '礼物' => '173_E7A4BCE789A9.png',
        '撒花' => '174_E69292E88AB1.png',
        '加一' => '175_E58AA0E4B880.png',
        '减一' => '176_E5878FE4B880.png',
        'okk' => '177_okk.png',
        'V5' => '178_V5.png',
        '绝' => '179_E7BB9D.png',
        '给力' => '180_E7BB99E58A9B.png',
        '红包' => '181_E7BAA2E58C85.png',
        '屎' => '182_E5B18E.png',
        '发' => '183_E58F91.png',
        '18禁' => '184_18E7A681.png',
        '炸弹' => '185_E782B8E5BCB9.png',
        '西瓜' => '186_E8A5BFE7939C.png',
        '加鸡腿' => '187_E58AA0E9B8A1E885BF.png',
        '握爪' => '188_E68FA1E788AA.png',
        '太阳' => '189_E5A4AAE998B3.png',
        '月亮' => '190_E69C88E4BAAE.png',
        '给跪了' => '191_E7BB99E8B7AAE4BA86.png',
        '蕉绿' => '192_E89589E7BBBF.png',
        '扎心' => '193_E6898EE5BF83.png',
        '胡瓜' => '194_E883A1E7939C.png',
        '打call' => '195_E68993call.png',
        '栓Q' => '196_E6A093Q.png',
        '雪花' => '197_E99BAAE88AB1.png',
        '圣诞树' => '198_E59CA3E8AF9EE6A091.png',
        '平安果' => '199_E5B9B3E5AE89E69E9C.png',
        '圣诞帽' => '200_E59CA3E8AF9EE5B8BD.png',
        '气球' => '201_E6B094E79083.png',
        '烟花' => '202_E7839FE88AB1.png',
        '福' => '203_E7A68F.png',
        'candy' => '204_candy.png',
        '糖葫芦' => '205_E7B396E891ABE88AA6.png',
        '鞭炮' => '206_E99EADE782AE.png',
        '元宝' => '207_E58583E5AE9D.png',
        '灯笼' => '208_E781AFE7ACBC.png',
        '锦鲤' => '209_E994A6E9B2A4.png',
        '巧克力' => '210_E5B7A7E5858BE58A9B.png',
        '戒指' => '211_E68892E68C87.png',
        '棒棒糖' => '212_E6A392E6A392E7B396.png',
        '纸飞机' => '213_E7BAB8E9A39EE69CBA.png',
        '粽子' => '214_E7B2BDE5AD90.png',
        );
        $name = $match[1];
        if(array_key_exists($name, $map))
            return '<img class="biaoqing" src="/usr/themes/VOID/assets/libs/owo/biaoqing/douyin/'.$map[$name].'">';
        return $match[0];
    }

    /**
     * 解析 fancybox
     * 
     * @return string
     * @param photoMode false: 普通解析，true: RSS(不包裹 a 标签)
     */
    static private $photoMode = false;
    static public function parseFancyBox($content, $photoMode = false)
    {
        $reg = '/<img.*?src="(.*?)".*?alt="(.*?)".*?>/s';
        self::$photoMode = $photoMode;
        $new = preg_replace_callback($reg, array('Contents', 'parseFancyBoxCallback'), $content);
        return $new;
    }

    /**
     * 根据 CDN 类型生成占位图片
     */
    public static function genBluredPlaceholderSrc($src)
    {
        $setting = $GLOBALS['VOIDSetting'];
        $cdn_config = $setting['CDNType'];
        $addons = array(
            "UPYUN" => '!/max/64',
            "QINIU" => '?imageView2/2/w/64/q/75'
        );

        $components = parse_url($src);
        $cdn = '';
        if (array_key_exists($components['host'], $cdn_config)) {
            $cdn = $cdn_config[$components['host']];
        }

        $addon = '';
        if (array_key_exists($cdn, $addons)) {
            $addon = $addons[$cdn];
        }

        return str_replace('#'.parse_url($src)['fragment'], '', $src).$addon;
    }

    /**
     * 解析图片（正常文章）
     * 
     * @return string
     */
    private static function parseFancyBoxCallback($match)
    {
        $setting = $GLOBALS['VOIDSetting'];
        $src_ori = $match[1];
        $src = $src_ori;
        $classList = '';

        // 这里，若图片已获取长宽基础信息，则直接计算后输出
        $attrAddOnA = '';
        $attrAddOnFigure = '';
        $matches;
        if (strpos($src_ori, 'vwid') != false) {
            preg_match("/vwid=(\d{0,5})/i", $src_ori, $matches);
            $width = floatval($matches[1]);
            preg_match("/vhei=(\d{0,5})/i", $src_ori, $matches);
            $height = floatval($matches[1]);

            $ratio = $height / $width * 100;
            $flex_grow = $width * 50 / $height;

            $attrAddOnA = 'style="padding-top: '.$ratio.'%"';
            $attrAddOnFigure = 'class="size-parsed" style="flex-grow: '.$flex_grow.'; width: '.$width.'px"';
        }

        $figcaption = '';
        if ($match[2] != '' && $setting['parseFigcaption'])
            $figcaption = '<figcaption>'.$match[2].'</figcaption>';

        // 普通解析且开启懒加载
        $placeholder = '';
        if(!self::$photoMode && Helper::options()->lazyload == '1') {
            $src = '';
            $classList = 'lazyload';
            if ($setting['bluredLazyload'])
                $placeholder = '<img class="blured-placeholder remove-after" src="'.self::genBluredPlaceholderSrc($src_ori).'">';

            $attrAddOnA .= ' class="lazyload-container" ';
        }

        // 使用浏览器原生的懒加载方法
        if (!self::$photoMode && Helper::options()->lazyload == '1' && $setting['browserLevelLoadingLazy']) {
            $classList .= ' browserlevel-lazy';
            $img = '<img class="'.$classList.'" alt="'.$match[2].'" src="'.$src_ori.'" loading="lazy">';
        } else {
            $img = $placeholder.'<img class="'.$classList.'" alt="'.$match[2].'" data-src="'.$src_ori.'" src="'.$src.'">';
        }

        if (!self::$photoMode) {
            return '<figure '.$attrAddOnFigure.' ><a '.$attrAddOnA.' no-pjax data-fancybox="gallery" data-caption="'.$match[2].'" href="'.$src_ori.'">'.$img.'</a>'.$figcaption.'</figure>';
        } else {
            return '<figure>'.$img.$figcaption.'</figure>';
        }
    }

    /**
     * 解析友情链接
     * 
     * @return string
     */
    static public function markdown($text)
    {
        // 去除换行
        $reg = '/\[links.*?\](.*?)\[\/links\]/s';
        $text = preg_replace_callback($reg, array('Contents', 'parseBoardCallback1'), $text);

        // 向前兼容
        $reg = '/<div class="board-list link-list">(.*?)<\/div>/s';
        $text = preg_replace_callback($reg, array('Contents', 'parseBoardCallback1'), $text);

        $reg = '/\[links.*?\](.*?)\[\/links\]/s';
        $text = preg_replace_callback($reg, array('Contents', 'parseBoardCallback2'), $text);

        if (0 == strpos($text, '<!--markdown-->')) {
            $text = str_replace("```objective-c", "```objectivec", $text);
            $text = str_replace("```c++", "```cpp", $text);
            $text = str_replace("```c#", "```csharp", $text);
            $text = str_replace("```f#", "```fsharp", $text);
            $text = str_replace("```F#", "```Fsharp", $text);
            $text = Markdown::convert($text);
        }

        return $text;
    }

    /**
     * 去除换行
     * 
     * @return string
     */
    static function parseBoardCallback1($matchs)
    {
        $text =  str_replace(array("\r\n", "\r", "\n"), "", $matchs[1]);
        return '[links]'.$text.'[/links]';
    }

    /**
     * 解析友链列表
     * 
     * @return string
     */
    static function parseBoardCallback2($matchs)
    {
        $text = '<div class="board-list link-list">%boards%</div>';

        $reg='/\[(.*?)\]\((.*?)\)\+\((.*?)\)/s';
        $rp = '<a target="_blank" href="${2}" class="board-item link-item"><div class="board-thumb" data-thumb="${3}"></div><div class="board-title">${1}</div></a>';
        $boards = preg_replace($reg,$rp,$matchs[1]);

        return  str_replace('%boards%', $boards, $text);
    }

    /**
     * 解析 ruby
     * 
     * @return string
     */
    static public function parseRuby($string)
    {
        $reg='/\{\{(.*?):(.*?)\}\}/s';
        $rp='<ruby>${1}<rp>(</rp><rt>${2}</rt><rp>)</rp></ruby>';
        $new=preg_replace($reg,$rp,$string);
        return $new;
    }

    /**
     * 最近评论，过滤引用通告，过滤博主评论
     * 
     * @return array
     */
    public static function getRecentComments($num = 10)
    {
        $output = array();

        $db = Typecho_Db::get();
        $rows = $db->fetchAll($db->select()->from('table.comments')->where('table.comments.status = ?', 'approved')
        ->where('type = ?', 'comment')
        ->where('ownerId <> authorId')
        ->order('table.comments.created', Typecho_Db::SORT_DESC)
        ->limit($num));

        foreach ($rows as $row) {
            $comment = self::getComment($row['coid']);
            $output[] = array(
                'permalink' => $comment->permalink,
                'mail' => $row['mail'],
                'author' => $row['author'],
            );
        }

        return $output;
    }

    /**
     * 文章上一篇
     */
    public static function thePrev($archive)
    {
        $db = Typecho_Db::get();
        $content = $db->fetchRow($db->select()->from('table.contents')->where('table.contents.created < ?', $archive->created)
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.type = ?', $archive->type)
            ->where('table.contents.password IS NULL')
            ->order('table.contents.created', Typecho_Db::SORT_DESC)
            ->limit(1));

        if ($content) {
            return self::getPost($content['cid']);    
        } else {
            return null;
        }
    }

    /**
     * 文章下一篇
     */
    public static function theNext($archive)
    {
        $db = Typecho_Db::get();
        $content = $db->fetchRow($db->select()->from('table.contents')->where('table.contents.created > ? AND table.contents.created < ?',
            $archive->created, Helper::options()->gmtTime)
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.type = ?', $archive->type)
            ->where('table.contents.password IS NULL')
            ->order('table.contents.created', Typecho_Db::SORT_ASC)
            ->limit(1));

        if ($content) {
            return self::getPost($content['cid']);    
        } else {
            return null;
        }
    }

    /**
     * 内容归档
     * 
     * @return array
     */
    public static function archives($widget, $excerpt = false)
    {
        $db = Typecho_Db::get();
        $rows = $db->fetchAll($db->select()
                    ->from('table.contents')
                    ->order('table.contents.created', Typecho_Db::SORT_DESC)
                    ->where('table.contents.type = ?', 'post')
                    ->where('table.contents.status = ?', 'publish')
                    ->where('table.contents.created < ?', time()));

        $stat = array();
        foreach ($rows as $row) {
            $row = $widget->filter($row);
            $arr = array(
                'title' => $row['title'],
                'permalink' => $row['permalink']);

            if(Utils::isPluginAvailable('VOID')) {
                $arr['words'] = $row['wordCount'];
            }
            
            if($excerpt){
                $arr['excerpt'] = substr($row['content'], 30);
            }
            $stat[date('Y', $row['created'])][$row['created']] = $arr;
        }
        return $stat;
    }

    /**
     * 文章标签
     * 
     * @return array
     */
    public static function getTags($cid)
    {
        $db = Typecho_Db::get();
        $rows = $db->fetchAll($db->select('mid')
            ->from('table.relationships')
            ->where("cid = ?", $cid));
        
        $metas = array();
        foreach ($rows as $row) {
            $meta = self::getMeta($row['mid']);
            if ($meta->type == 'tag') {
                $meta = array('name' => $meta->name,
                    'permalink' => $meta->permalink);
                $metas[] = $meta;
            }
        }

        return $metas;
    }
}