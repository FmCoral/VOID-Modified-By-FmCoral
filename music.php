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
        function toArray(list){
            if(Array.isArray(list)) return list;
            var arr = [];
            if(list && typeof list === 'object'){
                for(var k in list){ if(Object.prototype.hasOwnProperty.call(list,k)) arr.push(list[k]); }
            }
            return arr;
        }
        fetch(url).then(function(r){return r.json();}).then(function(list){
            new APlayer({
                element: document.getElementById('player'),
                narrow: false,
                autoplay: false,
                mutex: true,
                showlrc: 3,
                theme: '#b7daff',
                mode: 'random',
                preload: 'auto',
                listmaxheight: '340px',
                music: toArray(list)
            });
        }).catch(function(){
            new APlayer({
                element: document.getElementById('player'),
                music: []
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
