<?php
/**
 * Contents.php
 *
 * 解析器等内容处理相关
 *
 * @author      熊猫小A, FmCoral
 * @version     1.2 (PHP 8+ Compatible)
 */

use Typecho\Common;
use Typecho\Db;
use Typecho\Plugin;
use Typecho\Request as TypechoRequest;
use Typecho\Response as TypechoResponse;
use Typecho\Widget\Request as WidgetRequest;
use Typecho\Widget\Response as WidgetResponse;
use Utils\Helper;
use Widget\Archive;
use Widget\Base\Contents as BaseContents;
use Widget\Base\Comments as BaseComments;
use Widget\Base\Metas as BaseMetas;

class Contents
{
    /**
     * 当前 TOC ID 计数器
     */
    private static int $CurrentTocID = 0;

    /**
     * 照片模式（RSS 时无 a 标签包裹）
     */
    private static bool $photoMode = false;

    /**
     * 根据 cid 返回文章对象
     *
     * @param int $cid
     * @return BaseContents
     */
    public static function getPost(int $cid): BaseContents
    {
        $db = Db::get();
        $req = new WidgetRequest(TypechoRequest::getInstance());
        $res = new WidgetResponse(TypechoRequest::getInstance(), TypechoResponse::getInstance());
        $post = new BaseContents($req, $res);
        $db->fetchRow(
            $post->select()->where("cid = ?", $cid)->limit(1),
            [$post, 'push']
        );
        return $post;
    }

    /**
     * 根据 cid 返回评论对象
     *
     * @param int $coid
     * @return BaseComments
     */
    public static function getComment(int $coid): BaseComments
    {
        $db = Db::get();
        $req = new WidgetRequest(TypechoRequest::getInstance());
        $res = new WidgetResponse(TypechoRequest::getInstance(), TypechoResponse::getInstance());
        $comment = new BaseComments($req, $res);
        $db->fetchRow(
            $comment->select()->where("coid = ?", $coid)->limit(1),
            [$comment, 'push']
        );
        return $comment;
    }

    /**
     * 根据 mid 返回 meta 对象
     *
     * @param int $mid
     * @return BaseMetas
     */
    public static function getMeta(int $mid): BaseMetas
    {
        $db = Db::get();
        $req = new WidgetRequest(TypechoRequest::getInstance());
        $res = new WidgetResponse(TypechoRequest::getInstance(), TypechoResponse::getInstance());
        $meta = new BaseMetas($req, $res);
        $db->fetchRow(
            $meta->select()->where("mid = ?", $mid)->limit(1),
            [$meta, 'push']
        );
        return $meta;
    }

    /**
     * 输出完备的标题
     *
     * @param Archive $archive
     * @return void
     */
    public static function title(Archive $archive): void
    {
        $archive->archiveTitle([
            'category' => '分类 %s 下的文章',
            'search'   => '包含关键字 %s 的文章',
            'tag'      => '标签 %s 下的文章',
            'author'   => '%s 发布的文章'
        ], '', ' - ');
        Helper::options()->title();
    }

    /**
     * 内容解析点钩子
     * Typecho filter 传参：(content, widget, content)
     *
     * @param mixed $data
     * @param mixed $widget
     * @param mixed $last
     * @return string
     */
    public static function contentEx($data, $widget, $last): string
    {
        $text = empty($last) ? (string)$data : (string)$last;
        if ($widget instanceof Archive) {
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
     * Typecho filter 传参：(content, widget, content)
     *
     * @param mixed $data
     * @param mixed $widget
     * @param mixed $last
     * @return string
     */
    public static function excerptEx($data, $widget, $last): string
    {
        $text = empty($last) ? (string)$data : (string)$last;
        if ($widget instanceof Archive) {
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
     * @param string $content
     * @return string
     */
    public static function parseHeader(string $content): string
    {
        $reg = '/\<h([2-6])(.*?)\>(.*?)\<\/h.*?\>/s';
        return preg_replace_callback($reg, ['Contents', 'parseHeaderCallback'], $content);
    }

    /**
     * 为内容中的 h2-h6 元素编号
     *
     * @param array $matchs
     * @return string
     */
    public static function parseHeaderCallback(array $matchs): string
    {
        $id = 'toc_' . (self::$CurrentTocID++);
        $tag = $matchs[1] ?? '2';
        $attrs = $matchs[2] ?? '';
        $content = $matchs[3] ?? '';
        return '<h' . $tag . $attrs . ' id="' . $id . '">' . $content . '</h' . $tag . '>';
    }

    /**
     * 解析提示块
     *
     * @param string $content
     * @return string
     */
    public static function parseNotice(string $content): string
    {
        $reg = '/\[notice.*?\](.*?)\[\/notice\]/s';
        return preg_replace($reg, '<p class="notice">${1}</p>', $content);
    }

    /**
     * 解析照片集
     *
     * @param string $content
     * @return string
     */
    public static function parsePhotoSet(string $content): string
    {
        $setting = $GLOBALS['VOIDSetting'] ?? [];

        // 清除无用 tag
        $reg = '/\[photos(.*?)\/photos\]/s';
        $new = preg_replace_callback($reg, ['Contents', 'parsePhotoSetCallBack'], $content);

        $reg = '/<p>\[photos.*?\](.*?)\[\/photos\]<\/p>/s';
        if (!empty($setting['largePhotoSet'])) {
            $rp = '<div class="photos large">${1}</div>';
        } else {
            $rp = '<div class="photos">${1}</div>';
        }

        return preg_replace($reg, $rp, $new);
    }

    /**
     * 解析照片集回调函数
     *
     * @param array $match
     * @return string
     */
    private static function parsePhotoSetCallBack(array $match): string
    {
        $content = $match[1] ?? '';
        return '[photos' . str_replace(['<br>', '<p>', '</p>'], '', $content) . '/photos]';
    }

    /**
     * 解析表情
     *
     * @param string $content
     * @return string
     */
    public static function parseBiaoQing(string $content): string
    {
        $content = preg_replace_callback(
            '/\:\:\(\s*(呵呵|哈哈|吐舌|太开心|笑眼|花心|小乖|乖|捂嘴笑|滑稽|你懂的|不高兴|怒|汗|黑线|泪|真棒|喷|惊哭|阴险|鄙视|酷|啊|狂汗|what|疑问|酸爽|呀咩爹|委屈|惊讶|睡觉|笑尿|挖鼻|吐|犀利|小红脸|懒得理|勉强|爱心|心碎|玫瑰|礼物|彩虹|太阳|星星月亮|钱币|茶杯|蛋糕|大拇指|胜利|haha|OK|沙发|手纸|香蕉|便便|药丸|红领巾|蜡烛|音乐|灯泡|开心|钱|咦|呼|冷|生气|弱|吐血|吃瓜|吃翔|惊恐|啾咪)\s*\)/is',
            ['Contents', 'parsePaopaoBiaoqingCallback'],
            $content
        );
        $content = preg_replace_callback(
            '/\:\^\(\s*(.*?)\s*\)/is',
            ['Contents', 'parseDouyinBiaoqingCallback'],
            $content
        );
        $content = preg_replace_callback(
            '/\:\@\(\s*(.*?)\s*\)/is',
            ['Contents', 'parseAruBiaoqingCallback'],
            $content
        );

        return $content;
    }

    /**
     * 通用表情文件映射构建
     *
     * @param string $dirName 目录名 (paopao/aru/douyin)
     * @param string $suffix 文件名后缀 (如 _2x.png)
     * @return array URL编码名 → 实际文件名
     */
    private static function buildExpressionFileMap(string $dirName, string $suffix = ''): array
    {
        $map = [];
        $dir = __DIR__ . '/../assets/libs/owo/biaoqing/' . $dirName . '/';
        if (!is_dir($dir)) {
            return $map;
        }
        $files = scandir($dir);
        if ($files === false) {
            return $map;
        }
        foreach ($files as $f) {
            if (!empty($suffix)) {
                // paopao/aru: URL编码名 + 固定后缀，如 E591B5E591B5_2x.png
                $pattern = '/^([A-F0-9]+)' . preg_quote($suffix, '/') . '$/i';
            } else {
                // douyin: 数字前缀_URL编码名.png，如 001_E5BEAEE7AC91.png
                $pattern = '/^\d+_([A-F0-9]+)\.png$/i';
            }
            if (preg_match($pattern, $f, $m)) {
                $map[$m[1]] = $f;
            }
        }
        return $map;
    }

    /**
     * 泡泡表情回调函数
     *
     * @param array $match
     * @return string
     */
    private static function parsePaopaoBiaoqingCallback(array $match): string
    {
        $name = $match[1] ?? '呵呵';
        $key = str_replace('%', '', urlencode($name));

        static $fileMap = null;
        if ($fileMap === null) {
            $fileMap = self::buildExpressionFileMap('paopao', '_2x.png');
        }

        $filename = $fileMap[$key] ?? '';
        if ($filename === '') {
            return $match[0];
        }

        return '<img class="biaoqing" src="/usr/themes/VOID/assets/libs/owo/biaoqing/paopao/'
            . $filename . '">';
    }

    /**
     * 阿鲁表情回调函数
     * 匹配 :@(expression) 语法，仅查 aru 目录
     *
     * @param array $match
     * @return string
     */
    private static function parseAruBiaoqingCallback(array $match): string
    {
        $name = $match[1] ?? '';
        $key = str_replace('%', '', urlencode($name));
        if ($key === '') {
            return $match[0] ?? '';
        }

        static $aruMap = null;
        if ($aruMap === null) {
            $aruMap = self::buildExpressionFileMap('aru', '_2x.png');
        }
        if (isset($aruMap[$key])) {
            return '<img class="biaoqing" src="/usr/themes/VOID/assets/libs/owo/biaoqing/aru/'
                . $aruMap[$key] . '">';
        }

        return $match[0];
    }

    /**
     * 抖音表情回调函数
     *
     * @param array $match
     * @return string
     */
    private static function parseDouyinBiaoqingCallback(array $match): string
    {
        $name = $match[1] ?? '';
        $key = str_replace('%', '', urlencode($name));
        if ($key === '') {
            return $match[0] ?? '';
        }

        static $fileMap = null;
        if ($fileMap === null) {
            $fileMap = self::buildExpressionFileMap('douyin');
        }

        $filename = $fileMap[$key] ?? '';
        if ($filename === '') {
            return $match[0];
        }

        return '<img class="biaoqing" src="/usr/themes/VOID/assets/libs/owo/biaoqing/douyin/'
            . $filename . '">';
    }

    /**
     * 扫描目录，建立 URL 编码名 → 实际文件名映射
     * 解析 fancybox
     *
     * @param string $content
     * @param bool $photoMode false: 普通解析，true: RSS(不包裹 a 标签)
     * @return string
     */
    public static function parseFancyBox(string $content, bool $photoMode = false): string
    {
        $reg = '/<img.*?src="(.*?)".*?alt="(.*?)".*?>/s';
        self::$photoMode = $photoMode;
        return preg_replace_callback($reg, ['Contents', 'parseFancyBoxCallback'], $content);
    }

    /**
     * 根据 CDN 类型生成占位图片
     *
     * @param string $src 原始图片地址
     * @return string
     */
    public static function genBluredPlaceholderSrc(string $src): string
    {
        $setting = $GLOBALS['VOIDSetting'] ?? [];
        $cdnConfig = $setting['CDNType'] ?? [];

        $addons = [
            "UPYUN" => '!/max/64',
            "QINIU" => '?imageView2/2/w/64/q/75'
        ];

        $components = parse_url($src);
        $cdn = '';
        if (is_array($components) && isset($components['host'])
            && is_array($cdnConfig) && array_key_exists($components['host'], $cdnConfig)) {
            $cdn = $cdnConfig[$components['host']];
        }

        $addon = '';
        if (is_array($addons) && $cdn !== '' && array_key_exists($cdn, $addons)) {
            $addon = $addons[$cdn];
        }

        // 安全处理 fragment
        $fragment = '';
        if (is_array($components) && isset($components['fragment'])) {
            $fragment = '#' . $components['fragment'];
        }
        $cleanSrc = str_replace($fragment, '', $src);

        return $cleanSrc . $addon;
    }

    /**
     * 解析图片（正常文章）
     *
     * @param array $match
     * @return string
     */
    private static function parseFancyBoxCallback(array $match): string
    {
        $setting = $GLOBALS['VOIDSetting'] ?? [];
        $srcOri = $match[1] ?? '';
        $alt = $match[2] ?? '';
        $src = $srcOri;
        $classList = '';

        // 这里，若图片已获取长宽基础信息，则直接计算后输出
        $attrAddOnA = '';
        $attrAddOnFigure = '';

        if (strpos($srcOri, 'vwid') !== false) {
            preg_match("/vwid=(\d{0,5})/i", $srcOri, $widthMatches);
            preg_match("/vhei=(\d{0,5})/i", $srcOri, $heightMatches);

            $width = isset($widthMatches[1]) ? (float)$widthMatches[1] : 0;
            $height = isset($heightMatches[1]) ? (float)$heightMatches[1] : 0;

            if ($width > 0 && $height > 0) {
                $ratio = $height / $width * 100;
                $flexGrow = $width * 50 / $height;

                $attrAddOnA = 'style="padding-top: ' . $ratio . '%"';
                $attrAddOnFigure = 'class="size-parsed" style="flex-grow: ' . $flexGrow . '; width: ' . $width . 'px"';
            }
        }

        $figcaption = '';
        if ($alt !== '' && !empty($setting['parseFigcaption'])) {
            $figcaption = '<figcaption>' . $alt . '</figcaption>';
        }

        // 普通解析且开启懒加载
        $placeholder = '';
        $lazyloadEnabled = (Helper::options()->lazyload ?? '0') === '1';
        if (!self::$photoMode && $lazyloadEnabled) {
            $src = '';
            $classList = 'lazyload';
            if (!empty($setting['bluredLazyload'])) {
                $placeholder = '<img class="blured-placeholder remove-after" src="'
                    . self::genBluredPlaceholderSrc($srcOri) . '">';
            }
            $attrAddOnA .= ' class="lazyload-container" ';
        }

        $img = $placeholder . '<img class="' . $classList . '" alt="' . $alt
            . '" data-src="' . $srcOri . '" src="' . $src . '">';

        if (!self::$photoMode) {
            return '<figure ' . $attrAddOnFigure . '><a ' . $attrAddOnA
                . ' no-pjax data-fancybox="gallery" data-caption="' . $alt . '" href="' . $srcOri . '">'
                . $img . '</a>' . $figcaption . '</figure>';
        } else {
            return '<figure>' . $img . $figcaption . '</figure>';
        }
    }

    /**
     * 解析友情链接
     *
     * @param string $text
     * @return string
     */
    public static function markdown(string $text): string
    {
        // 去除换行
        $reg = '/\[links.*?\](.*?)\[\/links\]/s';
        $text = preg_replace_callback($reg, ['Contents', 'parseBoardCallback1'], $text);

        // 向前兼容
        $reg = '/<div class="board-list link-list">(.*?)<\/div>/s';
        $text = preg_replace_callback($reg, ['Contents', 'parseBoardCallback1'], $text);

        $reg = '/\[links.*?\](.*?)\[\/links\]/s';
        $text = preg_replace_callback($reg, ['Contents', 'parseBoardCallback2'], $text);

        if (strpos($text, '<!--markdown-->') === 0) {
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
     * @param array $matchs
     * @return string
     */
    public static function parseBoardCallback1(array $matchs): string
    {
        $text = str_replace(["\r\n", "\r", "\n"], "", $matchs[1] ?? '');
        return '[links]' . $text . '[/links]';
    }

    /**
     * 解析友链列表
     *
     * @param array $matchs
     * @return string
     */
    public static function parseBoardCallback2(array $matchs): string
    {
        $text = '<div class="board-list link-list">%boards%</div>';

        $reg = '/\[(.*?)\]\((.*?)\)\+\((.*?)\)/s';
        $rp = '<a target="_blank" href="${2}" class="board-item link-item">'
            . '<div class="board-thumb" data-thumb="${3}"></div>'
            . '<div class="board-title">${1}</div></a>';
        $boards = preg_replace($reg, $rp, $matchs[1] ?? '');

        return str_replace('%boards%', $boards, $text);
    }

    /**
     * 解析 ruby
     *
     * @param string $string
     * @return string
     */
    public static function parseRuby(string $string): string
    {
        $reg = '/\{\{(.*?):(.*?)\}\}/s';
        $rp = '<ruby>${1}<rp>(</rp><rt>${2}</rt><rp>)</rp></ruby>';
        return preg_replace($reg, $rp, $string);
    }

    /**
     * 最近评论，过滤引用通告，过滤博主评论
     *
     * @param int $num
     * @return array
     */
    public static function getRecentComments(int $num = 10): array
    {
        $output = [];

        $db = Db::get();
        $rows = $db->fetchAll(
            $db->select()->from('table.comments')
                ->where('table.comments.status = ?', 'approved')
                ->where('type = ?', 'comment')
                ->where('ownerId <> authorId')
                ->order('table.comments.created', Db::SORT_DESC)
                ->limit($num)
        );

        foreach ($rows as $row) {
            $comment = self::getComment($row['coid']);
            $output[] = [
                'permalink' => $comment->permalink,
                'mail' => $row['mail'],
                'author' => $row['author'],
            ];
        }

        return $output;
    }

    /**
     * 文章上一篇
     *
     * @param Archive $archive
     * @return BaseContents|null
     */
    public static function thePrev($archive): ?BaseContents
    {
        $db = Db::get();
        $content = $db->fetchRow(
            $db->select()->from('table.contents')
                ->where('table.contents.created < ?', $archive->created)
                ->where('table.contents.status = ?', 'publish')
                ->where('table.contents.type = ?', $archive->type)
                ->where('table.contents.password IS NULL')
                ->order('table.contents.created', Db::SORT_DESC)
                ->limit(1)
        );

        if ($content) {
            return self::getPost((int)$content['cid']);
        }
        return null;
    }

    /**
     * 文章下一篇
     *
     * @param Archive $archive
     * @return BaseContents|null
     */
    public static function theNext($archive): ?BaseContents
    {
        $db = Db::get();
        $content = $db->fetchRow(
            $db->select()->from('table.contents')
                ->where('table.contents.created > ? AND table.contents.created < ?',
                    $archive->created, Helper::options()->gmtTime)
                ->where('table.contents.status = ?', 'publish')
                ->where('table.contents.type = ?', $archive->type)
                ->where('table.contents.password IS NULL')
                ->order('table.contents.created', Db::SORT_ASC)
                ->limit(1)
        );

        if ($content) {
            return self::getPost((int)$content['cid']);
        }
        return null;
    }

    /**
     * 内容归档
     *
     * @param Archive $widget
     * @param bool $excerpt
     * @return array
     */
    public static function archives($widget, bool $excerpt = false): array
    {
        $db = Db::get();
        $rows = $db->fetchAll(
            $db->select()
                ->from('table.contents')
                ->order('table.contents.created', Db::SORT_DESC)
                ->where('table.contents.type = ?', 'post')
                ->where('table.contents.status = ?', 'publish')
                ->where('table.contents.created < ?', time())
        );

        $stat = [];
        foreach ($rows as $row) {
            $row = $widget->filter($row);
            $arr = [
                'title' => $row['title'],
                'permalink' => $row['permalink']
            ];

            if (Utils::isPluginAvailable('VOID')) {
                $arr['words'] = $row['wordCount'] ?? 0;
            }

            if ($excerpt) {
                $arr['excerpt'] = isset($row['content']) ? substr($row['content'], 0, 30) : '';
            }
            $created = $row['created'] ?? 0;
            $stat[date('Y', $created)][$created] = $arr;
        }
        return $stat;
    }

    /**
     * 文章标签
     *
     * @param int $cid
     * @return array
     */
    public static function getTags(int $cid): array
    {
        $db = Db::get();
        $rows = $db->fetchAll(
            $db->select('mid')
                ->from('table.relationships')
                ->where("cid = ?", $cid)
        );

        $metas = [];
        foreach ($rows as $row) {
            $meta = self::getMeta((int)$row['mid']);
            if ($meta->type === 'tag') {
                $metas[] = [
                    'name' => $meta->name,
                    'permalink' => $meta->permalink
                ];
            }
        }

        return $metas;
    }
}
