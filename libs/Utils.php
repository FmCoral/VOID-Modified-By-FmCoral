<?php
/**
 * Utils.php
 *
 * 工具类
 *
 * @author      熊猫小A, FmCoral
 * @version     1.2 (PHP 8+ Compatible)
 */

use Typecho\Common;
use Typecho\Cookie;
use Typecho\Db;
use Typecho\Plugin;
use Typecho\Request;
use Typecho\Widget\Helper\Form\Element\Checkbox;
use Typecho\Widget\Helper\Form\Element\Radio;
use Typecho\Widget\Helper\Form\Element\Select;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Textarea;

class Utils
{
    /**
     * 输出相对首页路由，本方法会自适应伪静态
     *
     * @param string $path
     * @return void
     */
    public static function index(string $path): void
    {
        Helper::options()->index($path);
    }

    /**
     * 输出相对首页路径，本方法不处理伪静态，用于静态文件
     *
     * @param string $path
     * @return void
     */
    public static function indexHome(string $path): void
    {
        Helper::options()->siteUrl($path);
    }

    /**
     * 输出相对主题目录路径，用于静态文件
     *
     * @param string $path
     * @return void
     */
    public static function indexTheme(string $path): void
    {
        Helper::options()->themeUrl($path);
    }

    /**
     * 输出头像链接
     *
     * @param string $mail
     * @param int $size
     * @param string $d
     * @return void
     */
    public static function gravatar(string $mail, int $size = 64, string $d = ''): void
    {
        echo Common::gravatarUrl($mail, $size, '', empty($d) ? null : urlencode($d), true);
    }

    /**
     * 判断插件是否可用
     *
     * @param string $name 插件名称
     * @return bool
     */
    public static function isPluginAvailable(string $name): bool
    {
        $plugins = Plugin::export();
        $activatedPlugins = $plugins['activated'] ?? [];
        return is_array($activatedPlugins) && array_key_exists($name, $activatedPlugins);
    }

    /**
     * PJAX判定
     *
     * @return bool
     */
    public static function isPjax(): bool
    {
        return !empty($_SERVER['HTTP_X_PJAX']);
    }

    /**
     * 使用衬线体判定
     *
     * @param array $setting 主题设置
     * @return bool
     */
    public static function isSerif(array $setting): bool
    {
        if (isset($_COOKIE['serif'])) {
            return $_COOKIE['serif'] === '1';
        }
        return !empty($setting['serifincontent']);
    }

    /**
     * 界面大小风格
     * 1: 14px, 2: 16px, 3: 18px, 4: 20px, 5: 22px
     *
     * @param array $setting
     * @return string
     */
    public static function getTextSize(array $setting): string
    {
        if (isset($_COOKIE['textsize'])) {
            return $_COOKIE['textsize'];
        }
        return (string)($setting['defaultFontSize'] ?? 3);
    }

    /**
     * 移动端判定
     *
     * @return bool
     */
    public static function isMobile(): bool
    {
        if (isset($_SERVER['HTTP_X_WAP_PROFILE'])) {
            return true;
        }

        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $clientKeywords = [
                'mobile', 'nokia', 'sony', 'ericsson', 'mot', 'samsung', 'htc', 'sgh',
                'lg', 'sharp', 'sie-', 'philips', 'panasonic', 'alcatel', 'lenovo',
                'iphone', 'ipod', 'blackberry', 'meizu', 'android', 'netfront', 'symbian',
                'ucweb', 'windowsce', 'palm', 'operamini', 'operamobi', 'openwave',
                'nexusone', 'cldc', 'midp', 'wap'
            ];
            $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
            if (preg_match("/(" . implode('|', $clientKeywords) . ")/i", $agent)) {
                return true;
            }
        }

        if (isset($_SERVER['HTTP_ACCEPT'])) {
            $accept = $_SERVER['HTTP_ACCEPT'];
            if (
                (strpos($accept, 'vnd.wap.wml') !== false)
                && (strpos($accept, 'text/html') === false
                    || strpos($accept, 'vnd.wap.wml') < strpos($accept, 'text/html'))
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * iOS 判定
     *
     * @return bool
     */
    public static function isIosSafari(): bool
    {
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return strpos($agent, 'iPhone') !== false || strpos($agent, 'iPad') !== false;
    }

    /**
     * 编辑界面添加Button
     *
     * @return void
     */
    public static function addButton(): void
    {
        echo '<script src="';
        self::indexTheme('/assets/libs/owo/owo_02.js');
        echo '"></script>';

        echo '<script src="';
        self::indexTheme('/assets/editor-d6bdd77f4b.js');
        echo '"></script>';

        echo '<link rel="stylesheet" href="';
        self::indexTheme('/assets/libs/owo/owo.min.css');
        echo '" />';

        echo '<style>
            #custom-field textarea,#custom-field input{width:100%}
            .OwO span{background:none!important;width:unset!important;height:unset!important}
            .OwO .OwO-body .OwO-items{
                -webkit-overflow-scrolling: touch;
                overflow-x: hidden;
            }
            .OwO .OwO-body .OwO-items-image .OwO-item{
                max-width:-moz-calc(20% - 10px);
                max-width:-webkit-calc(20% - 10px);
                max-width:calc(20% - 10px)
            }
            @media screen and (max-width:767px){
                .comment-info-input{flex-direction:column;}
                .comment-info-input input{max-width:100%;margin-top:5px}
                #comments .comment-author .avatar{
                    width: 2.5rem;
                    height: 2.5rem;
                }
            }
            @media screen and (max-width:760px){
                .OwO .OwO-body .OwO-items-image .OwO-item{
                    max-width:-moz-calc(25% - 10px);
                    max-width:-webkit-calc(25% - 10px);
                    max-width:calc(25% - 10px)
                }
            }
            .wmd-button-row{height:unset}
        </style>';
    }

    /**
     * 判定内容是否过时
     *
     * @param Widget_Archive $archive
     * @return array
     */
    public static function isOutdated($archive): array
    {
        $created = isset($archive->created) ? round((time() - $archive->created) / 3600 / 24) : 0;
        $updated = isset($archive->modified) ? round((time() - $archive->modified) / 3600 / 24) : 0;

        // Check if outdated notice is disabled via custom field
        try {
            $enableField = $archive->fields->enableOutdatedNotice;
            if ($enableField === '0' || $enableField === 0) {
                return [
                    "is" => false,
                    "created" => $created,
                    "updated" => $updated
                ];
            }
        } catch (\Throwable $e) {
            // No custom field set, use default
        }

        // Custom threshold: outdatedDays (camelCase, old VOID) or outdated_days
        $threshold = 90; // default
        try {
            $fieldVal = $archive->fields->outdatedDays;
            if ($fieldVal !== '' && $fieldVal !== null) {
                $threshold = intval($fieldVal);
            } else {
                $fieldVal = $archive->fields->outdated_days;
                if ($fieldVal !== '' && $fieldVal !== null) {
                    $threshold = intval($fieldVal);
                }
            }
        } catch (\Throwable $e) {
            // No custom field set, use default
        }

        return [
            "is" => $threshold > 0 && $created > $threshold,
            "created" => $created,
            "updated" => $updated
        ];
    }

    /**
     * 输出建站时间（最早一篇文章的写作时间）
     *
     * @return void
     */
    public static function getBuildTime(): void
    {
        $db = Db::get();
        $content = $db->fetchRow($db->select()->from('table.contents')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.password IS NULL')
            ->order('table.contents.created', Db::SORT_ASC)
            ->limit(1));

        if (!empty($content) && isset($content['created'])) {
            echo date('Y-m-d\TH:i', $content['created']);
        } else {
            echo date('Y-m-d\TH:i');
        }
    }

    /**
     * 已发布文章数量
     *
     * @return int
     */
    public static function getPostNum(): int
    {
        $db = Db::get();
        $result = $db->fetchObject($db->select(array('COUNT(cid)' => 'num'))
            ->from('table.contents')
            ->where('table.contents.type = ?', 'post')
            ->where('table.contents.status = ?', 'publish'));
        return $result->num ?? 0;
    }

    /**
     * 分类数量
     *
     * @return int
     */
    public static function getCatNum(): int
    {
        $db = Db::get();
        $result = $db->fetchObject($db->select(array('COUNT(mid)' => 'num'))
            ->from('table.metas')
            ->where('table.metas.type = ?', 'category'));
        return $result->num ?? 0;
    }

    /**
     * 标签数量
     *
     * @return int
     */
    public static function getTagNum(): int
    {
        $db = Db::get();
        $result = $db->fetchObject($db->select(array('COUNT(mid)' => 'num'))
            ->from('table.metas')
            ->where('table.metas.type = ?', 'tag'));
        return $result->num ?? 0;
    }

    /**
     * 存在 VOID 插件且满足要求
     *
     * @param float $req 所需版本
     * @return bool
     */
    public static function hasVOIDPlugin(float $req): bool
    {
        if (self::isPluginAvailable('VOID')) {
            $versionHave = \VOID_Plugin::$VERSION ?? 0;
            return $versionHave >= $req;
        }
        return false;
    }

    /**
     * 超高级设置
     *
     * @return array
     */
    public static function getVOIDSettings(): array
    {
        $options = Helper::options();

        // 主题设置
        $themeSetting = [
            'defaultBanner' => '',
            'enableMath' => false,
            'head' => '',
            'footer' => '',
            'serifincontent' => false,
            'pjax' => false,
            'pjaxreload' => '',
            'indexStyle' => 0,
            'lazyload' => false,
            'indexBannerTitle' => '',
            'indexBannerSubtitle' => '',
            'serviceworker' => '',
            'colorScheme' => 0, // 0: 自动，1: 日间，2: 夜间
            'reward' => ''
        ];

        foreach (array_keys($themeSetting) as $key) {
            $value = $options->{$key} ?? null;
            if (!empty($value)) {
                $themeSetting[$key] = $value;
            }
        }

        // 类型变换
        $themeSetting['enableMath'] = (bool)$themeSetting['enableMath'];
        $themeSetting['lazyload'] = (bool)$themeSetting['lazyload'];
        $themeSetting['colorScheme'] = (int)$themeSetting['colorScheme'];
        $themeSetting['pjax'] = (bool)$themeSetting['pjax'];
        $themeSetting['serifincontent'] = (bool)$themeSetting['serifincontent'];
        $themeSetting['indexStyle'] = (int)$themeSetting['indexStyle'];

        // 高级设置
        $advanceSetting = [
            'nav' => [],
            'name' => '',
            'brandFont' => [
                'src' => '',
                'style' => 'normal',
                'weight' => 'normal'
            ],
            'desktopBannerHeight' => '',
            'mobileBannerHeight' => '',
            'twitterId' => '',
            'weiboId' => '',
            'headerMode' => 1,
            'defaultFontSize' => 3,
            'useFiraCodeFont' => false,
            'followSystemColorScheme' => false,
            'largePhotoSet' => true,
            'macStyleCodeBlock' => true,
            'lineNumbers' => true,
            'parseFigcaption' => true,
            'darkModeTime' => [
                'start' => 22.0,
                'end' => 7.0
            ],
            'link' => [],
            'commentFoldThreshold' => [5, 1.5],
            'commentNotification' => '',
            'bluredLazyload' => false,
            'CDNType' => []
        ];

        $advanceRaw = $options->advance ?? '';
        if (!empty($advanceRaw)) {
            $settings = json_decode($advanceRaw, true);
            if (is_array($settings)) {
                foreach ($settings as $key => $value) {
                    $advanceSetting[$key] = $value;
                }
            }
        }

        if (self::isMobile() && array_key_exists('headerModeMobile', $advanceSetting)) {
            $advanceSetting['headerMode'] = $advanceSetting['headerModeMobile'];
        }

        $output = array_merge($themeSetting, $advanceSetting);
        $output['VOIDPlugin'] = self::hasVOIDPlugin($GLOBALS['VOIDPluginREQ'] ?? 0);

        return $output;
    }
}
