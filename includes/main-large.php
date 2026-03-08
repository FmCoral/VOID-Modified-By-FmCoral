<?php
/**
 * main.php
 * 
 * 内容页面主要区域，PJAX 作用区域
 * 适用于巨大文字、巨幅图片
 * 
 * @author      熊猫小A
 * @version     2019-05-08 0.1
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$setting = $GLOBALS['VOIDSetting'];
?>

<main id="pjax-container" class="main-excerpt">
    <script>document.querySelector('body>header').classList.remove('force-dark')</script>
    <title hidden>
        <?php Contents::title($this); ?>
    </title>
    <?php $this->need('includes/ldjson.php'); ?>

    <style>
        body > footer { display: none; }
        main {display: flex; flex-direction: column; justify-content: center; padding: 17.5vh 0 50px 0;}
    </style>
    <?php if($this->fields->enableMusic == '1'): ?>
    <div class="wrapper container <?php if($setting['indexStyle'] == 1) echo 'narrow'; else echo 'wide'; ?>">
        <div id="player" style="margin: 40px 0;"></div>
    </div>
    <link rel="stylesheet" href="<?php $this->options->themeUrl('assets/APlayer.min.css'); ?>">
    <script src="<?php $this->options->themeUrl('assets/APlayer.min.js'); ?>"></script>
    <script>
    (function(){
        var url = "<?php $this->options->themeUrl('assets/music.json'); ?>";
        var lrcBase = "<?php $this->options->themeUrl('assets/lrc/'); ?>";
        var selectedMusicRaw = "<?php echo $this->fields->musicSelect ? htmlspecialchars($this->fields->musicSelect) : ''; ?>";
        var selectedMusic = selectedMusicRaw === '' ? 'all' : selectedMusicRaw;
        function toArray(list){
            if(Array.isArray(list)) return list;
            var arr = [];
            if(list && typeof list === 'object'){
                for(var k in list){ if(Object.prototype.hasOwnProperty.call(list,k)) { var it=list[k]; it.__key=k; arr.push(it);} }
            }
            return arr;
        }
        try {
            fetch(url).then(function(r){return r.json();}).then(function(list){
                var arr = toArray(list);
                var lrcType = 3;
                for(var i=0;i<arr.length;i++){
                    var item = arr[i];
                    var key = item && item.__key != null ? item.__key : String(i);
                    var raw = item && typeof item.lrc === 'string' ? item.lrc.trim() : '';
                    var isUrl = /^https?:\/\//.test(raw) || /\.lrc(\?.*)?$/.test(raw);
                    if(!isUrl){
                        item.lrc = lrcBase + encodeURIComponent(key) + ".lrc";
                    }
                }
                if (selectedMusic && selectedMusic !== 'all') {
                    var filtered = arr.filter(function(item){ return item.__key === selectedMusic; });
                    if (filtered.length > 0) {
                        arr = filtered;
                    }
                }
                new APlayer({
                    element: document.getElementById('player'),
                    container: document.getElementById('player'),
                    narrow: false,
                    autoplay: false,
                    mutex: true,
                    showlrc: 3,
                    theme: '#b7daff',
                    mode: 'random',
                    preload: 'auto',
                    listmaxheight: '340px',
                    lrcType: lrcType,
                    audio: arr
                });
            }).catch(function(){
                new APlayer({ element: document.getElementById('player'), container: document.getElementById('player'), audio: [] });
            });
        } catch(e){
            new APlayer({ element: document.getElementById('player'), music: [] });
        }
    })();
    </script>
    <?php endif; ?>
    <div class="app-landscape theme-dark">
        <div class="mask" id="bg"><div class="mask"></div></div>
        <div class="container" style="margin-bottom: 2rem">
            <article class="yue">
                <div class="articleBody">
                    <?php $this->content(); ?>
                </div>
            </article>
        </div>
        <script>
            (function(){
                var applyBg = function (url) {
                    document.getElementById('bg').style.backgroundImage = 'url(' + url + ')';
                    document.getElementById('bg').classList.add('loaded');
                }
                var img_bg = new Image();
                var img_bg_url = "<?php echo $this->fields->banner; ?>";
                if(!img_bg.complete) {
                    img_bg.onload = function() {
                        applyBg(img_bg_url);
                    };
                    img_bg.src = img_bg_url;
                }
                else {
                    img_bg.src = img_bg_url;
                    applyBg(img_bg_url);
                }
            })();
        </script>
    </div>
    <!--评论区，可选-->
    <div class="theme-dark" style="width: 100%">
        <?php $this->need('includes/comments.php'); ?>
    </div>
</main>
