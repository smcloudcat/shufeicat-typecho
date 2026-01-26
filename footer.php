<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>

        </div><!-- end .row -->
    </div>
</div><!-- end #body -->

<footer id="footer" role="contentinfo">
    <div class="container">
        <div class="footer-content">
            <div class="footer-powered">
                <?php if ($this->options->footerCopyrightEnabled !== 'off'): ?>
                    <?php
                    // 获取当前年份
                    $currentYear = date('Y');
                    
                    // 输出Typecho驱动信息（固定显示）
                    $typechoText = _t('由 <a href="https://typecho.org" target="_blank">Typecho</a> 强力驱动');
                    echo '<p class="footer-line"><i class="fa fa-bolt"></i> ' . $typechoText . '</p>';
                    
                    // 输出主题信息（固定显示）
                    echo '<p class="footer-line"><i class="fa fa-code"></i> Theme by <a href="https://github.com/smcloudcat/shufeicat-typecho" target="_blank">ShuFeiCat</a></p>';
                    
                    // 输出自定义版权文本（如果设置）
                    if (!empty($this->options->footerCustomText)) {
                        $customText = $this->options->footerCustomText;
                        // 替换变量
                        $customText = str_replace('{year}', $currentYear, $customText);
                        $customText = str_replace('{sitetitle}', $this->options->title, $customText);
                        $customText = str_replace('{siteurl}', $this->options->siteUrl, $customText);
                        echo '<p class="footer-line footer-custom">' . $customText . '</p>';
                    }
                    
                    // 输出网站备案号（如果设置）
                    if (!empty($this->options->footerBeianNumber)) {
                        $beianLink = $this->options->footerBeianLink ?: 'https://beian.miit.gov.cn/';
                        $beianHtml = '<a href="' . $beianLink . '" target="_blank" rel="nofollow">' . 
                                    htmlspecialchars($this->options->footerBeianNumber) . '</a>';
                        echo '<p class="footer-line">' . $beianHtml . '</p>';
                    }
                    
                    // 输出公安备案号（如果设置）
                    if (!empty($this->options->footerGonganNumber)) {
                        echo '<p class="footer-line">' . htmlspecialchars($this->options->footerGonganNumber) . '</p>';
                    }
                    ?>
                <?php else: ?>
                    <!-- 版权信息已关闭 -->
                <?php endif; ?>
            </div>
        </div>
    </div>
</footer><!-- end #footer -->

<!-- 返回顶部按钮 -->
<div id="back-to-top" title="<?php _e('返回顶部'); ?>">
    <i class="fa fa-angle-up"></i>
</div>

<?php $this->footer(); ?>

<?php
// 获取资源加载配置
$resourceMode = !empty($this->options->resourceMode) ? $this->options->resourceMode : 'local';
$customCdn = !empty($this->options->customCdn) ? rtrim($this->options->customCdn, '/') : '';

// 获取主题基础URL，确保以斜杠结尾
$themeUrl = rtrim($this->options->themeUrl, '/') . '/';

// JS 资源路径配置
$jsUrls = [
    'jquery' => 'https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js',
    'main' => $themeUrl . 'assets/js/main.js',
    'ajax' => $themeUrl . 'assets/js/ajax.js',
    'pjax' => $themeUrl . 'assets/js/pjax.js',
    'pjax_lib' => 'https://cdn.jsdelivr.net/npm/pjax@0.2.8/pjax.min.js',
    'prism' => 'https://cdn.jsdelivr.net/npm/prismjs@1.29.0/prism.min.js',
    'prismAutoloader' => 'https://cdn.jsdelivr.net/npm/prismjs@1.29.0/plugins/autoloader/prism-autoloader.min.js',
    'lightbox' => 'https://cdn.jsdelivr.net/npm/lightbox2@2.11.4/dist/js/lightbox.min.js'
];

// 根据配置调整资源路径
if ($resourceMode === 'cdn') {
    // 使用官方CDN（保持默认）
} elseif ($resourceMode === 'custom' && $customCdn) {
    // 使用自建CDN
    $jsUrls['main'] = $customCdn . '/assets/js/main.js';
    $jsUrls['ajax'] = $customCdn . '/assets/js/ajax.js';
    $jsUrls['pjax'] = $customCdn . '/assets/js/pjax.js';
}
// local 模式使用默认的 themeUrl 路径
?>

<!-- jQuery 库 - Lightbox2 依赖 -->
<script src="<?php echo $jsUrls['jquery']; ?>"></script>

<!-- Prism.js 代码高亮脚本 - 使用Autoloader自动加载依赖 -->
<script src="<?php echo $jsUrls['prism']; ?>"></script>
<script src="<?php echo $jsUrls['prismAutoloader']; ?>"></script>

<!-- Lightbox2 图片灯箱脚本 -->
<script src="<?php echo $jsUrls['lightbox']; ?>"></script>

<!-- 主题主脚本 -->
<script src="<?php echo $jsUrls['main']; ?>"></script>

<!-- Pjax加载配置 -->
<script>
window.pjaxEnabled = <?php echo (!empty($this->options->pjaxLoad) && $this->options->pjaxLoad === 'on') ? 'true' : 'false'; ?>;
window.pjaxLoadStyle = '<?php echo !empty($this->options->pjaxLoadStyle) ? $this->options->pjaxLoadStyle : 'progress'; ?>';
window.pjaxTimeout = <?php echo !empty($this->options->pjaxTimeout) ? intval($this->options->pjaxTimeout) : 10000; ?>;
</script>

<!-- Pjax库 -->
<script src="<?php echo $jsUrls['pjax_lib']; ?>"></script>

<!-- Pjax加载脚本 -->
<script src="<?php echo $jsUrls['pjax']; ?>"></script>

</body>
</html>