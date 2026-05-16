<?php
/**
 * Photos
 *
 * @package custom
 *
 * @author      FmCoral
 * @version     2026-04-06 0.1
 *
*/

if (!defined('__TYPECHO_ROOT_DIR__'))
    exit;
$setting = $GLOBALS['VOIDSetting'];
$musicDb = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'music.json';
if (!file_exists($musicDb)) {
    @file_put_contents($musicDb, json_encode(array()));
}

if (!Utils::isPjax()) {
    $this->need('includes/head.php');
    $this->need('includes/header.php');
}
?>

<main id="pjax-container">

    <title hidden>
        <?php Contents::title($this); ?>
    </title>

    <?php
    $this->need('includes/ldjson.php');
    $this->need('includes/banner.php');
    ?>
    
    <style>
        @media screen and (min-width: 1200px) {
            .wrapper.container {
                width: 70%;
                max-width: none;
            }
        }
    </style>
    
    <div class="wrapper container">
        <div class="contents-wrap"> <!--start .contents-wrap-->
            <?php if($this->fields->enableMusic == '1'): ?>
            <div id="player" style="margin: 40px 0;"></div>
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
            <section id="post" class="float-up">
                <article class="post yue">
                    <div class="articleBody" class="full">
                        <?php $this->content(); ?>
                    </div>
                </article>
            </section>
        </div> <!--end .contents-wrap-->
        <!--目录，可选-->
        <?php if($this->fields->showTOC == '1'): ?>
            <div class="toc-mask" onclick="TOC.close();"></div>
            <div aria-label="文章目录" class="TOC"></div>
            <style>
            #toggle-toc { display: block; }
            </style>
        <?php endif;?>
    </div>
    <!--评论区，可选-->
    <?php $this->need('includes/comments.php'); ?>

</main>

<!-- footer -->
<?php
if (!Utils::isPjax()) {
    $this->need('includes/footer.php');
}
?>
