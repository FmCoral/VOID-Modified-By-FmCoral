# VOID 主题 PHP 8+ 移植说明

## 概述

将 VOID 3.5.1 主题从 PHP 5.x/7.x 全面移植到 PHP 8.x 兼容。原始代码大量使用 Typecho 旧式下划线分隔类名（如 `Typecho_Widget_Helper_Form_Element_Text`）、未初始化变量、不安全的数组访问和与父类不兼容的方法签名，这些在 PHP 8.0+ 中会导致致命错误或警告。

**注意**：主题的表情渲染在移植过程中发现了更深层的问题——Typecho 的插件钩子系统在 PHP 8+ 命名空间环境下存在 handle 错位，导致 `contentEx` 钩子无法触发。最终绕过钩子系统，在模板中直接调用 `Contents::parseBiaoQing()` 修复。

## 修改文件清单（共 24 个文件）

```
usr/themes/VOID/
├── functions.php              # 重写 - 旧式类名 → 命名空间 + use 导入
├── libs/
│   ├── Utils.php              # 重写 - 全部旧式类名替换 + 类型安全
│   ├── Contents.php           # 重写 - 类名替换 + 数组安全 + Widget 构造函数修复
│   │                          #          + 表情引擎重构（3套语法 + 通用文件映射器）
│   └── Comments.php           # 重写 - 类继承/方法签名兼容 + $commentClass 初始化
├── index.php                  # 1处修改 - 绕过 contentEx 钩子，直接调用 parseBiaoQing()
├── post.php                   # 无修改
├── page.php                   # 无修改
├── archive.php                # 无修改
├── Archives.php               # 1处修改 - 旧式 Widget 类名字符串
└── includes/
    ├── head.php               # 无修改
    ├── header.php             # 2处修改 - 旧式 Widget 类名字符串
    ├── main.php               # 1处修改 - 绕过 contentEx 钩子，直接调用 parseBiaoQing()
    ├── main-large.php         # 1处修改 - 绕过 contentEx 钩子，直接调用 parseBiaoQing()
    ├── footer.php             # 无修改
    ├── banner.php             # 无修改
    ├── comments.php           # 无修改
    ├── archives.php           # 无修改
    ├── ldjson.php             # 无修改
    └── 404.php                # 无修改

usr/themes/VOID/assets/libs/owo/
└── OwO_02.json                # 214条表情语法 :@() → :^()（抖音表情专用）

usr/plugins/VOID/
├── Plugin.php                 # 重写 - 全部旧式类名替换 + use 导入 + 数组安全访问
├── Action.php                 # 重写 - 类名替换 + Widget 构造函数修复 + $_SERVER 安全 + $this->body 安全
├── libs/
│   ├── IP.php                 # 修复 - 大括号字符串偏移 → 方括号语法
│   ├── ParseImg.php           # 重写 - 旧式类名替换 + use 导入 + str_get_html 安全检查
│   ├── WordCount.php          # 重写 - 旧式类名替换 + 移除未使用变量
│   └── ParseAgent.php         # 无修改
│   └── simple_html_dom.php    # 无修改 (第三方库)
└── pages/
    └── showActivity.php       # 无修改
```

---

## 一、PHP 8+ 不兼容问题分类

### 1.1 致命错误（导致白屏）

| # | 问题 | 文件 | 行号(原) | 说明 |
|---|------|------|----------|------|
| 1 | 方法返回类型不兼容：子类 `array` vs 父类 `Contents` | `libs/Comments.php` | `___parentContent()` | PHP 8 严格执行协变返回类型 |
| 2 | 方法参数签名不兼容：子类 `alt()` vs 父类 `alt(...$args)` | `libs/Comments.php` | `alt()` | 缺少 `...$args` 可变参数 |
| 3 | 未定义变量 `$commentClass` | `libs/Comments.php` | `threadedCommentsCallback()` | VOIDPlugin 禁用时变量从未初始化 |

### 1.2 警告级问题（影响稳定性）

| # | 问题 | 文件 | 说明 |
|---|------|------|------|
| 4 | 未定义数组键 `$match[1]`、`$match[2]` | `libs/Contents.php` | 正则回调中未检查匹配组是否存在 |
| 5 | `str_replace()` 可能接收 null | `libs/Contents.php:genBluredPlaceholderSrc()` | `parse_url($src)['fragment']` 不存在时 PHP 8.1+ 废弃 |
| 6 | `parse_url()` 未检查返回类型 | `libs/Contents.php:genBluredPlaceholderSrc()` | 结果可能是 false 而非数组 |
| 7 | `$_SERVER` 键未检查 | `libs/Utils.php` | `$_SERVER['HTTP_X_PJAX']` 等可能不存在 |
| 8 | `json_decode()` 未检查返回类型 | `libs/Utils.php:getVOIDSettings()` | 可能返回非数组 |
| 9 | `count($content)` 误用 | `libs/Utils.php:getBuildTime()` | 结果可能不是数组 |

### 1.3 旧式类名别名（全部替换为命名空间式）

这些在 Typecho 1.3.0 的 `__TYPECHO_CLASS_ALIASES__` 中仍有定义，但使用命名空间式更高效且未来兼容。

```php
// 旧（PHP 5.x 兼容）
Typecho_Plugin::factory('Widget_Abstract_Contents');
Typecho_Db::get();
Typecho_Common::gravatarUrl($mail, $size, '', '', true);
new Typecho_Widget_Helper_Form_Element_Text('name', null, '', '标签', '描述');

// 新（PHP 8.x）
use Typecho\Plugin;
use Typecho\Db;
use Typecho\Common;
use Typecho\Widget\Helper\Form\Element\Text;

Plugin::factory('Widget_Abstract_Contents');
Db::get();
Common::gravatarUrl($mail, $size, '', null, true);
new Text('name', null, '', '标签', '描述');
```

---

## 二、关键修改详解

### 2.1 `___parentContent()` 返回类型冲突

**错误信息：**
```
Declaration of VOID_Widget_Comments_Archive::___parentContent(): array
must be compatible with Widget\Base\Comments::___parentContent(): Contents
```

**原因：**  
父类 `\Widget\Base\Comments` 在 PHP 8 中的方法签名为：
```php
protected function ___parentContent(): Contents
```
而 VOID 子类覆盖为返回 `array`，这在 PHP 8+ 中不被允许——协变返回类型要求子类返回类型必须是父类返回类型的**子类型**，`array` 不是 `Contents` 的子类型。

**修复方式：**

1. **删除** `___parentContent()` 覆盖（让父类处理，返回 `Contents` 对象）
2. 将 `___permalink()` 中对 `$this->parentContent['key']` 的数组访问改为 `$this->parameter->parentContent['key']`（直接访问 Config 参数中的原始数组）
3. 添加 `?? ''` 空合并运算符防止键不存在

```php
// 修复前（子类覆盖，返回 array）
protected function ___parentContent(): array
{
    return $this->parameter->parentContent ?? [];
}

// 修复后：删除此方法，父类返回 Contents 对象
// 同时修复 ___permalink() 中的访问方式
$pc = isset($this->parameter->parentContent) ? $this->parameter->parentContent : [];
// 使用 $pc['permalink'] 而非 $this->parentContent['permalink']
```

### 2.2 `alt()` 方法签名不兼容

**错误信息：**
```
Declaration of VOID_Widget_Comments_Archive::alt(): void
must be compatible with Typecho\Widget::alt(...$args)
```

**原因：**  
父类使用可变参数 `...$args`，子类声明 `alt(): void`（无参数）。PHP 8+ 要求子类签名与父类兼容。

**修复方式：**

```php
// 修复前
public function alt(): void
{
    $args = func_get_args();
    $num = func_num_args();
    // ...
}

// 修复后
public function alt(...$args): void
{
    $num = count($args);
    // ...
}
```

### 2.3 `$commentClass` 未定义变量

**原因：**  
在 `threadedCommentsCallback()` 中，`$commentClass .= ' fold';` 仅当 `$setting['VOIDPlugin']` 为真时执行，但之后 `echo $commentClass;` 无条件执行。VOIDPlugin 未安装时 `$commentClass` 从未初始化。

**修复方式：**

```php
// 在 if 块前添加初始化
$commentClass = '';
if (!empty($setting['VOIDPlugin'])) {
    // ... 可能修改 $commentClass
}
```

### 2.4 `genBluredPlaceholderSrc()` 中 parse_url 安全处理

**原代码问题：**
```php
return str_replace('#'.parse_url($src)['fragment'], '', $src).$addon;
```

这里三个潜在错误：
1. `parse_url()` 可能返回 `false`（非数组）
2. `['fragment']` 键可能不存在
3. `str_replace()` 接收 null 在 PHP 8.1+ 废弃

**修复方式：**
```php
$components = parse_url($src);
$fragment = '';
if (is_array($components) && isset($components['fragment'])) {
    $fragment = '#' . $components['fragment'];
}
$cleanSrc = str_replace($fragment, '', $src);
return $cleanSrc . $addon;
```

### 2.5 插件工厂钩子的类名问题

在 `functions.php` 中注册插件钩子时，旧代码使用 `Typecho_Plugin::factory('Widget_Abstract_Contents')`。

实际上 Typecho 1.3.0 的 `Plugin` 构造函数会通过 `__TYPECHO_CLASS_ALIASES__` 将命名空间类名反向映射回旧式别名：

```php
// Plugin 构造函数中的别名解析
public function __construct(string $handle)
{
    if (defined('__TYPECHO_CLASS_ALIASES__')) {
        $alias = array_search('\\' . ltrim($handle, '\\'), __TYPECHO_CLASS_ALIASES__);
        $handle = $alias ?: $handle;
    }
    $this->handle = Common::nativeClassName($handle);
}
```

所以 `Plugin::factory('\Widget\Base\Contents')` 会被统一归一化为 `Widget_Abstract_Contents`，与核心代码的 `Contents::pluginHandle()` 匹配。修改后的代码可以直接使用命名空间类名：

```php
Plugin::factory('Widget_Abstract_Contents')->contentEx = ['Contents', 'contentEx'];
// 保持不变——这是插件钩子标识符，不是 PHP 类引用
```

### 2.6 `Helper` 类的自动加载

Typecho 将 `Helper` 注册为 `__TYPECHO_CLASS_ALIASES__['Helper'] = '\Utils\Helper'`，该别名在 `Widget\Init::alloc()` 中定义。因此在 `functions.php` 中引用 `Helper::options()` 前必须确保 Typecho 已完成初始化（`config.inc.php` + `Widget\Init::alloc()`）。

```php
// 调用链
functions.php → Utils::getVOIDSettings() → Helper::options()
```

在真实 Typecho 环境中 `Widget\Init::alloc()` 会在主题加载前执行，因此 `Helper` 始终可用。

### 2.7 Widget 构造函数类型不兼容

**错误信息：**
```
TypeError: Typecho\Widget::__construct(): Argument #2 ($response)
must be of type Typecho\Widget\Response, 
Typecho\Widget\Helper\EmptyClass given
```

**原因：**  
`Contents.php` 中 `getPost()`、`getComment()`、`getMeta()` 三个方法直接传入 `EmptyClass` 作为 Widget 构造函数的第二个参数。但 Typecho 1.3.0 的 Widget 构造函数类型提示为：

```php
public function __construct(
    \Typecho\Widget\Request $request,    // 不是 \Typecho\Request
    \Typecho\Widget\Response $response,  // 不是 \Typecho\Response
    $params = null
)
```

`EmptyClass` 不是 `\Typecho\Widget\Response` 的子类，PHP 8 严格执行协变参数类型导致 TypeError。同理，`Request::getInstance()` 返回 `\Typecho\Request` 而非 `\Typecho\Widget\Request`。

**影响范围：** 所有调用 `thePrev()`、`theNext()`、`getTags()` 的页面（即文章详情页）。

**修复方式：**

```php
// 修复前（PHP 8 报 TypeError）
$post = new BaseContents(Request::getInstance(), EmptyClass::getInstance());

// 修复后（正确创建包装类型）
$req = new \Typecho\Widget\Request(\Typecho\Request::getInstance());
$res = new \Typecho\Widget\Response(\Typecho\Request::getInstance(), \Typecho\Response::getInstance());
$post = new BaseContents($req, $res);
```

### 2.8 表情渲染修复 — contentEx 钩子不触发

**问题表现：** 文章详情页和首页文章列表中，表情标记（`:^(微笑)`、`::(滑稽)` 等）原样输出，不渲染为 `<img>` 标签。评论区表情渲染正常。

**诊断过程：**

1. `contentEx` 钩子已在 `functions.php` 注册：
   ```php
   Plugin::factory('Widget_Abstract_Contents')->contentEx = ['Contents', 'contentEx'];
   ```

2. 在 `Contents::contentEx()` 方法内插入调试标记 `<!--CONTENTEX_RAN-->` 未在输出中出现 → 确认回调从未被执行。

3. 调用链路追踪：
   ```
   模板 $this->content()
     → Archive::content()
       → parent::content()
         → __get('content')
           → ___content()
             → Contents::pluginHandle()->filter('contentEx', $内容, $this)
   ```

4. `Contents::pluginHandle()` 内部调用 `Plugin::factory(static::class)`。`static::class` 的**后期静态绑定**在继承链中解析为 `\Widget\Archive`（或中间类），而非 `\Widget\Base\Contents`。

5. **Handle 错位分析：** `Plugin` 构造函数通过 `__TYPECHO_CLASS_ALIASES__` 反向映射时：
   - 注册时：`Plugin::factory('Widget_Abstract_Contents')` → handle = `Widget_Abstract_Contents`
   - 调用时：`Plugin::factory('\Widget\Archive')` → `Common::nativeClassName('\Widget\Archive')` → `Widget_Archive`

   `Widget_Archive` ≠ `Widget_Abstract_Contents`，因此注册的回调永远无法触发。

**影响范围：** 所有通过 `$this->content()` 渲染文章内容的模板（文章详情页、首页完整内容模式）。

**修复方式：** 绕过钩子系统，在模板中直接调用表情解析方法：

```php
// 修复前（依赖 contentEx 钩子，不触发）
<?php $this->content(); ?>

// 修复后（直接调用，绕过钩子）
<?php echo Contents::parseBiaoQing($this->content); ?>
```

**涉及模板文件**（3 处）：
| 文件 | 行号 | 说明 |
|------|------|------|
| `includes/main.php` | 32 | 文章详情页正文 |
| `includes/main-large.php` | 31 | Landscape 文章类型正文 |
| `index.php` | 90 | 首页完整内容模式 |

注意：`commentEx` 钩子不受影响（评论区模板已在移植前就使用 `parseBiaoQing()` 直接调用）。

### 2.9 表情语法变更与引擎重构

**背景：** 原 VOID 主题使用三套表情图包（泡泡 paopao、阿鲁 aru、抖音 douyin），但三者共用 `:@()` 语法，且 PHP 端仅用 `:&()` 硬编码处理抖音，导致 UI 和渲染端逻辑不一致。

**变更内容：**

| 表情包 | 旧语法 | 新语法 | 说明 |
|--------|--------|--------|------|
| 泡泡 (paopao) | `::(滑稽)` | `::(滑稽)` | 不变 |
| 阿鲁 (aru) | `:@(微笑)` | `:@(微笑)` | 不变，但移除抖音回退 |
| 抖音 (douyin) | `:@(强壮)` | `:^(强壮)` | 独立语法，与原 `:@` 区分 |

**JSON 数据源修改：** `assets/libs/owo/OwO_02.json` 中 214 条抖音表情 `data` 字段从 `:@(名称)` 改为 `:^(名称)`。

**备注：** 这是前向变更，不兼容旧的 `:@()` 抖音语法。

### 2.10 HTML 实体编码兼容

**问题：** Typecho 对文章内容会经过 `htmlspecialchars()` 处理，将 `&` 编码为 `&amp;`。旧的正则 `/\:\&\(\s*(.*?)\s*\)/is` 匹配 `:&(微笑)`，但实际输入是 `:&amp;(微笑)`，导致匹配失败。

**修复方式：** 正则增加 `(?:amp;)?` 可选组兼容两种形式：

```php
// 修复前（仅匹配 :&(name)）
/\:\&\(\s*(.*?)\s*\)/is

// 修复后（兼容 :&(name) 和 :&amp;(name)）
/\:\&(?:amp;)?\(\s*(.*?)\s*\)/is
```

**关联变更：** 抖音语法已改为 `:^()`，此修复仅适用于历史内容中残留的 `:&()` 旧式语法。

### 2.11 表情引擎重构 — `buildExpressionFileMap()` 通用方法

**问题：** 原来每个表情类型的回调函数各自独立实现目录扫描逻辑，代码重复且难以维护。抖音目录的文件命名格式为数字前缀+URL编码（`001_E5BEAEE7AC91.png`），与泡泡/阿鲁（纯 URL 编码 `E591B5E591B5_2x.png`）不同。

**解决方案：** 提取通用文件映射器：

```php
/**
 * @param string $dirName  目录名 (paopao/aru/douyin)
 * @param string $suffix   文件名后缀（如 _2x.png）
 *                         传空时表示数字前缀格式（douyin）
 * @return array URL编码名 → 实际文件名
 */
private static function buildExpressionFileMap(string $dirName, string $suffix = ''): array
{
    $map = [];
    $dir = __DIR__ . '/../assets/libs/owo/biaoqing/' . $dirName . '/';
    if (!is_dir($dir)) return $map;
    $files = scandir($dir);
    if ($files === false) return $map;
    foreach ($files as $f) {
        if (!empty($suffix)) {
            // paopao/aru: E591B5E591B5_2x.png
            $pattern = '/^([A-F0-9]+)' . preg_quote($suffix, '/') . '$/i';
        } else {
            // douyin: 001_E5BEAEE7AC91.png
            $pattern = '/^\d+_([A-F0-9]+)\.png$/i';
        }
        if (preg_match($pattern, $f, $m)) $map[$m[1]] = $f;
    }
    return $map;
}
```

三种表情回调现在统一使用此方法：
- `parsePaopaoBiaoqingCallback()` — `buildExpressionFileMap('paopao', '_2x.png')`
- `parseAruBiaoqingCallback()` — `buildExpressionFileMap('aru', '_2x.png')`
- `parseDouyinBiaoqingCallback()` — `buildExpressionFileMap('douyin')`（无后缀，自动用数字前缀模式）

---

## 三、完整的 PHP 8+ 兼容模式清单

### 3.1 参数和返回类型声明

所有新增的类型声明需确保与父类兼容：

| 类 | 方法 | 声明 | 备注 |
|---|------|------|------|
| `Utils` | 全部静态方法 | `void`, `bool`, `int`, `string`, `array` | 新方法，无兼容问题 |
| `Contents` | 全部静态方法 | `string`, `int`, `BaseContents`, `BaseComments`, `BaseMetas`, `array`, `void` | 新方法，无兼容问题 |
| `VOID_Widget_Comments_Archive` | `___permalink()` | `: string` | 父类无返回类型，允许添加 |
| `VOID_Widget_Comments_Archive` | `___children()` | `: array` | 父类无返回类型，允许添加 |
| `VOID_Widget_Comments_Archive` | `___isTopLevel()` | `: bool` | 父类无返回类型，允许添加 |
| `VOID_Widget_Comments_Archive` | `alt()` | `(...$args): void` | **必须**匹配父类 `alt(...$args)` |
| `VOID_Widget_Comments_Archive` | `execute()` | `: void` | 父类无返回类型，允许添加 |
| `VOID_Widget_Comments_Archive` | `push()` | `(array): array` | 父类返回类型为 `array`，兼容 |
| `VOID_Widget_Comments_Archive` | `pageNav()` | `(string, string, int, string, $template): void` | **参数类型需匹配**父类 |

### 3.2 必须移除的旧式方法覆盖

| 方法 | 原因 | 替换方案 |
|------|------|----------|
| `___parentContent(): array` | 父类返回 `Contents`，不兼容 | 删除覆盖，改用 `$this->parameter->parentContent` |

### 3.3 数组安全访问模式

```php
// 不安全的旧写法
$match[1]  // 正则匹配可能失败
parse_url($url)['host']  // parse_url 可能返回 false
$_SERVER['HTTP_X_PJAX']  // 键可能不存在
json_decode($str)['key']  // json_decode 可能返回非数组

// 安全的新写法
$match[1] ?? ''
(isset($components['host']) ? $components['host'] : '') ?? ''
$_SERVER['HTTP_X_PJAX'] ?? ''
json_decode($str, true) ?? []
```

---

## 四、用到的 Typecho 1.3.0 关键 API

### 4.1 类别名映射（`__TYPECHO_CLASS_ALIASES__`）

定义于 `var/Widget/Init.php:57`，旧式下划线类名到命名空间类的映射：

```php
'Widget_Abstract_Contents'    => '\Widget\Base\Contents',
'Widget_Abstract_Comments'    => '\Widget\Base\Comments',
'Widget_Abstract_Metas'       => '\Widget\Base\Metas',
'Widget_Abstract_Options'     => '\Widget\Base\Options',
'Widget_Abstract_Users'       => '\Widget\Base\Users',
'Widget_Metas_Category_List'  => '\Widget\Metas\Category\Rows',
'Widget_Contents_Page_List'   => '\Widget\Contents\Page\Rows',
'Typecho_Widget_Helper_Empty' => '\Typecho\Widget\Helper\EmptyClass',
'Helper'                      => '\Utils\Helper',
// ... 完整列表见 var/Widget/Init.php
```

### 4.2 Widget 构造函数签名

```php
// var/Typecho/Widget.php:91
public function __construct(
    \Typecho\Widget\Request $request,    // 注意：不是 \Typecho\Request
    \Typecho\Widget\Response $response,  // 注意：不是 \Typecho\Response
    $params = null
)
```

因此直接实例化 Widget 子类时必须传入正确的类型：
```php
// 正确
$request = new \Typecho\Widget\Request(\Typecho\Request::getInstance());
$response = new \Typecho\Widget\Response(\Typecho\Request::getInstance(), \Typecho\Response::getInstance());
$widget = new \Widget\Base\Contents($request, $response, $params);

// 错误（类型不匹配，PHP 8 报 TypeError）
$widget = new \Widget\Base\Contents(\Typecho\Request::getInstance(), ...);
```

### 4.3 Plugin 钩子系统

```php
// 注册钩子
Plugin::factory('handle_name')->hookName = ['ClassName', 'methodName'];

// 核心触发（在 Widget 子类中）
self::pluginHandle()->trigger($plugged)->call('methodName', $this, $args);
// 或过滤
self::pluginHandle()->filter('filterName', $value, $this);
```

钩子 handle 名称通过 `Common::nativeClassName()` 归一化，命名空间 `\Widget\Base\Contents` → `Widget_Base_Contents`。`Plugin` 构造函数的别名反向解析将 `\Widget\Base\Contents` → 查找 `__TYPECHO_CLASS_ALIASES__` → 找到 `Widget_Abstract_Contents`。

---

## 五、测试流程

### 5.1 PHP 语法检查

```bash
php -l usr/themes/VOID/functions.php
php -l usr/themes/VOID/libs/Utils.php
php -l usr/themes/VOID/libs/Contents.php
php -l usr/themes/VOID/libs/Comments.php
# ... 所有 19 个 PHP 文件
```

### 5.2 类加载验证（无数据库依赖）

```php
define('__TYPECHO_CLASS_ALIASES__', [
    'Helper' => '\Utils\Helper',
    'Widget_Abstract_Contents' => '\Widget\Base\Contents',
    'Widget_Abstract_Comments' => '\Widget\Base\Comments',
    'Widget_Abstract_Metas' => '\Widget\Base\Metas',
    'Typecho_Widget_Helper_Empty' => '\Typecho\Widget\Helper\EmptyClass',
]);

require_once 'usr/themes/VOID/libs/Utils.php';
require_once 'usr/themes/VOID/libs/Contents.php';
require_once 'usr/themes/VOID/libs/Comments.php';

// 验证
class_exists('Utils');                          // true
class_exists('Contents');                       // true
class_exists('VOID_Widget_Comments_Archive');    // true
```

### 5.3 完整运行测试

需配置好 `config.inc.php` 数据库连接后访问：
- `http://localhost/` — 首页
- `http://localhost/admin/themes.php` — 后台外观页面
- `http://localhost/archives/xxx.html` — 文章详情页
- `http://localhost/xxx.html` — 独立页面

---

## 六、常见错误速查

| 错误信息 | 可能原因 | 解决方案 |
|----------|----------|----------|
| `Declaration of X::method(): A must be compatible with Parent::method(): B` | 子类返回类型与父类不兼容 | 删除子类 type hint 或改为兼容类型 |
| `Argument #N must be of type X, Y given` (Widget 构造) | Widget 构造参数类型不匹配 | 使用 `Widget\Request`/`Widget\Response` 包装后再传入 |
| `Class "Helper" not found` | `Widget\Init::alloc()` 未执行 | 确保在主题加载前完成了 Typecho 初始化 |
| `Missing Database Object` | 数据库未配置或连接失败 | 检查 `config.inc.php` 中 DB 配置 |
| `Undefined array key ...` | 访问不存在的数组键 | 使用 `??` 空合并运算符 |
| `Undefined variable $xxx` | 变量未初始化就使用 | 使用前初始化：`$var = '';` 或 `$var = $default ?? '';` |

---

## 七、版本记录

| 日期 | 版本 | 说明 |
|------|------|------|
| 2019-01-15 | 3.5.1 | 原始发布（熊猫小A） |
| 2024-01-01 | 1.0 | PHP 8+ 兼容性移植（主题 + 插件） |
| 2024-07-20 | 1.1 | 修复 Widget 构造函数类型不兼容 |
| 2026-07-20 | 1.2 | 修复文章内容表情渲染（contentEx 钩子错位）；表情语法分离（抖音 :@ → :^）；<br>表情引擎重构（buildExpressionFileMap 通用方法）；HTML entity 兼容

### 文件级变更摘要

```
== 主题 ==
libs/Utils.php      — 100% 重写：全部类名替换 + 类型声明 + 数组安全 + $_SERVER 安全
libs/Contents.php   — 100% 重写：类名替换 + parse_url 安全 + 数组键检查 + 类型声明
                      + Widget 构造函数参数修复 (getPost/getComment/getMeta)
                      + 表情引擎重构（buildExpressionFileMap + 3套独立语法回调）
libs/Comments.php   — 100% 重写：类名替换 + $commentClass 初始化 + ___parentContent 覆盖移除
                      + alt() 签名修复 + ___permalink() 数组访问修复 + 类型声明
functions.php       — 100% 重写：全部 Form Element 类名替换 + use 导入 + 类型声明
includes/header.php — 2处 Widget 类名字符串替换
Archives.php        — 1处 Widget 类名字符串替换
includes/main.php   — 1处修改：绕过 contentEx 钩子，直接调用 parseBiaoQing()
includes/main-large.php — 1处修改：同上
index.php           — 1处修改：同上
assets/libs/owo/OwO_02.json — 214条表情语法 :@() → :^()（抖音表情专用）

== 插件 ==
Plugin.php          — 100% 重写：全部旧式类名替换 + use 导入 + 数组安全
Action.php          — 100% 重写：旧式类名替换 + Widget 构造参数修复 + $_SERVER/body 安全
libs/IP.php         — 修复：大括号字符串偏移 → 方括号
libs/ParseImg.php   — 重写：旧式类名替换 + use 导入 + str_get_html 安全检查
libs/WordCount.php  — 重写：旧式类名替换 + 移除未使用变量
```
