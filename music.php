<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$setting = $GLOBALS['VOIDSetting'];
if (!Utils::isPjax()) {
    $this->need('includes/head.php');
    $this->need('includes/header.php');
}
$musicDb = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'music.json';
if (!file_exists($musicDb)) {
    @file_put_contents($musicDb, json_encode(array()));
}
?>
<main id="pjax-container">
    <title hidden><?php Contents::title($this); ?></title>
    <div class="wrapper container <?php if($setting['indexStyle'] == 1) echo 'narrow'; else echo 'wide'; ?>">
        <div id="player" style="margin: 40px 0;"></div>
    </div>
    <link rel="stylesheet" href="<?php $this->options->themeUrl('assets/APlayer.min.css'); ?>">
    <script src="<?php $this->options->themeUrl('assets/APlayer.min.js'); ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var url = "<?php $this->options->themeUrl('assets/music.json'); ?>";
        var lrcBase = "<?php $this->options->themeUrl('assets/lrc/'); ?>";
        function toArray(list){
            if(Array.isArray(list)) return list;
            var arr = [];
            if(list && typeof list === 'object'){
                for(var k in list){ if(Object.prototype.hasOwnProperty.call(list,k)) { var it=list[k]; it.__key=k; arr.push(it);} }
            }
            return arr;
        }
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
            new APlayer({
                element: document.getElementById('player'),
                container: document.getElementById('player'),
                audio: []
            });
        });
    });
    </script>
</main>
<?php
if (!Utils::isPjax()) {
    $this->need('includes/footer.php');
}
?>
