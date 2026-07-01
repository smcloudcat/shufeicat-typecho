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
                    $typechoText = _t('由 <a href="https://typecho.org" target="_blank" rel="noopener noreferrer">Typecho</a> 强力驱动');
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
                    
                    // 输出备案号信息（ICP + 公安，支持同行/分行显示）
                    $beianNumber = !empty($this->options->footerBeianNumber) ? $this->options->footerBeianNumber : '';
                    $gonganNumber = !empty($this->options->footerGonganNumber) ? $this->options->footerGonganNumber : '';
                    $beianLayout = !empty($this->options->footerBeianLayout) ? $this->options->footerBeianLayout : 'newline';

                    if ($beianNumber || $gonganNumber) {
                        // 构建 ICP 备案号 HTML
                        $icpHtml = '';
                        if ($beianNumber) {
                            $beianLink = $this->options->footerBeianLink ?: 'https://beian.miit.gov.cn/';
                            $icpHtml = '<a href="' . $beianLink . '" target="_blank" rel="nofollow noopener noreferrer">' .
                                       htmlspecialchars($beianNumber) . '</a>';
                        }

                        // 构建公安备案号 HTML（含图标和链接）
                        $gonganHtml = '';
                        if ($gonganNumber) {
                            $gonganLink = $this->options->footerGonganLink ?: 'https://beian.mps.gov.cn/';
                            $gonganIcon = !empty($this->options->footerGonganIcon) ? $this->options->footerGonganIcon : '';
                            // 替换 {themeUrl} 占位符为真实主题URL
                            if ($gonganIcon) {
                                $themeUrl = rtrim($this->options->themeUrl, '/') . '/';
                                $gonganIcon = str_replace('{themeUrl}', $themeUrl, $gonganIcon);
                            }
                            $gonganHtml = '';
                            if ($gonganIcon) {
                                $gonganHtml .= '<img class="footer-beian-icon" src="' . htmlspecialchars($gonganIcon) . '" alt="" width="20" height="20" />';
                            }
                            $gonganHtml .= '<a href="' . $gonganLink . '" target="_blank" rel="noreferrer noopener">' .
                                          htmlspecialchars($gonganNumber) . '</a>';
                        }

                        // 根据布局选项输出
                        if ($beianLayout === 'inline' && $icpHtml && $gonganHtml) {
                            // 同行显示：ICP 与公安备案号在同一行，中间用分隔符
                            echo '<p class="footer-line footer-beian-inline">' . $icpHtml . '<span class="footer-beian-sep"> | </span>' . $gonganHtml . '</p>';
                        } else {
                            // 分行显示
                            if ($icpHtml) {
                                echo '<p class="footer-line">' . $icpHtml . '</p>';
                            }
                            if ($gonganHtml) {
                                echo '<p class="footer-line footer-beian-gongan">' . $gonganHtml . '</p>';
                            }
                        }
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

<!-- 手机端文章目录触发按钮（仅文章页且拥有目录时显示） -->
<div id="mobile-toc-btn" title="<?php _e('文章目录'); ?>">
    <i class="fa fa-list-ul"></i>
</div>

<!-- 手机端文章目录侧边栏（从右侧划出） -->
<div id="mobile-toc-sidebar">
    <div class="mobile-toc-header">
        <h3><i class="fa fa-list"></i> <?php _e('文章目录'); ?></h3>
        <button id="mobile-toc-close" title="<?php _e('关闭'); ?>">
            <i class="fa fa-times"></i>
        </button>
    </div>
    <nav class="mobile-toc-nav" id="mobile-toc-nav"></nav>
</div>

<!-- 手机端文章目录遮罩层 -->
<div id="mobile-toc-shade"></div>

<?php $this->footer(); ?>

<?php
// 获取资源加载配置
$resourceMode = !empty($this->options->resourceMode) ? $this->options->resourceMode : 'local';
$customCdn = !empty($this->options->customCdn) ? rtrim($this->options->customCdn, '/') : '';

// 获取主题基础URL，确保以斜杠结尾
$themeUrl = rtrim($this->options->themeUrl, '/') . '/';

// JS 资源路径配置
// 添加版本号以防止缓存问题：使用文件修改时间，文件更新后自动刷新缓存
$themeDir = dirname(__FILE__);
$mainJsMtime = filemtime($themeDir . '/assets/js/main.js');
$ajaxJsMtime = filemtime($themeDir . '/assets/js/ajax.js');
$pjaxJsMtime = filemtime($themeDir . '/assets/js/pjax.js');
$jsUrls = [
    'jquery' => $themeUrl . 'assets/vendor/jquery/jquery.min.js',
    'main' => $themeUrl . 'assets/js/main.js?v=' . ($mainJsMtime ?: shufei_get_theme_version()),
    'ajax' => $themeUrl . 'assets/js/ajax.js?v=' . ($ajaxJsMtime ?: shufei_get_theme_version()),
    'pjax' => $themeUrl . 'assets/js/pjax.js?v=' . ($pjaxJsMtime ?: shufei_get_theme_version()),
    'pjax_lib' => $themeUrl . 'assets/vendor/pjax/pjax.min.js',
    'prism' => $themeUrl . 'assets/vendor/prismjs/prism.js',
    'prismAutoloader' => $themeUrl . 'assets/vendor/prismjs/plugins/autoloader/prism-autoloader.min.js',
    'lightbox' => $themeUrl . 'assets/vendor/lightbox2/js/lightbox.min.js',
    'mermaid' => $themeUrl . 'assets/vendor/mermaid/mermaid.min.js',
    'echarts' => $themeUrl . 'assets/vendor/echarts/echarts.min.js',
    'katex' => $themeUrl . 'assets/vendor/katex/katex.min.js',
    'katexAutoRender' => $themeUrl . 'assets/vendor/katex/auto-render.min.js',
    'emojiList' => $themeUrl . 'assets/vendor/jquery-emoji/js/emoji.list.js',
    'emoji' => $themeUrl . 'assets/vendor/jquery-emoji/js/jquery.emoji.min.js'
];

// 根据配置调整资源路径
if ($resourceMode === 'cdn') {
    // 使用官方CDN
    $jsUrls['jquery'] = 'https://cdn.jsdelivr.net/npm/jquery@4.0.0/dist/jquery.min.js';
    $jsUrls['pjax_lib'] = 'https://cdn.jsdelivr.net/npm/pjax@0.2.8/pjax.min.js';
    $jsUrls['prism'] = 'https://cdn.jsdelivr.net/npm/prismjs@1.29.0/prism.min.js';
    $jsUrls['prismAutoloader'] = 'https://cdn.jsdelivr.net/npm/prismjs@1.29.0/plugins/autoloader/prism-autoloader.min.js';
    $jsUrls['lightbox'] = 'https://cdn.jsdelivr.net/npm/lightbox2@2.11.4/dist/js/lightbox.min.js';
    $jsUrls['mermaid'] = 'https://cdn.jsdelivr.net/npm/mermaid@10.9.6/dist/mermaid.min.js';
    $jsUrls['echarts'] = 'https://cdn.jsdelivr.net/npm/echarts@6.1.0/dist/echarts.min.js';
    $jsUrls['katex'] = 'https://cdn.jsdelivr.net/npm/katex@0.17.0/dist/katex.min.js';
    $jsUrls['katexAutoRender'] = 'https://cdn.jsdelivr.net/npm/katex@0.17.0/dist/contrib/auto-render.min.js';
} elseif ($resourceMode === 'custom' && $customCdn) {
    // 使用自建CDN
    $jsUrls['jquery'] = $customCdn . '/assets/vendor/jquery/jquery.min.js';
    $jsUrls['main'] = $customCdn . '/assets/js/main.js?v=' . ($mainJsMtime ?: shufei_get_theme_version());
    $jsUrls['ajax'] = $customCdn . '/assets/js/ajax.js?v=' . ($ajaxJsMtime ?: shufei_get_theme_version());
    $jsUrls['pjax'] = $customCdn . '/assets/js/pjax.js?v=' . ($pjaxJsMtime ?: shufei_get_theme_version());
    $jsUrls['pjax_lib'] = $customCdn . '/assets/vendor/pjax/pjax.min.js';
    $jsUrls['prism'] = $customCdn . '/assets/vendor/prismjs/prism.js';
    $jsUrls['prismAutoloader'] = $customCdn . '/assets/vendor/prismjs/plugins/autoloader/prism-autoloader.min.js';
    $jsUrls['lightbox'] = $customCdn . '/assets/vendor/lightbox2/js/lightbox.min.js';
    $jsUrls['mermaid'] = $customCdn . '/assets/vendor/mermaid/mermaid.min.js';
    $jsUrls['echarts'] = $customCdn . '/assets/vendor/echarts/echarts.min.js';
    $jsUrls['katex'] = $customCdn . '/assets/vendor/katex/katex.min.js';
    $jsUrls['katexAutoRender'] = $customCdn . '/assets/vendor/katex/auto-render.min.js';
    $jsUrls['emojiList'] = $customCdn . '/assets/vendor/jquery-emoji/js/emoji.list.js';
    $jsUrls['emoji'] = $customCdn . '/assets/vendor/jquery-emoji/js/jquery.emoji.min.js';
}
// local 模式使用默认的 themeUrl 路径
?>

<!-- jQuery 库 - Lightbox2 依赖，文章/页面加载，开启Pjax时全站加载 -->
<?php if ($this->is('post') || $this->is('page') || (!empty($this->options->pjaxLoad) && $this->options->pjaxLoad === 'on')): ?>
<script src="<?php echo $jsUrls['jquery']; ?>" defer></script>
<?php endif; ?>

<!-- Prism.js 代码高亮脚本 - 使用Autoloader自动加载依赖 -->
<?php if (empty($this->options->codeHighlightEnabled) || $this->options->codeHighlightEnabled !== 'off'): ?>
<script src="<?php echo $jsUrls['prism']; ?>" defer></script>
<script src="<?php echo $jsUrls['prismAutoloader']; ?>" defer></script>
<?php if ($resourceMode !== 'cdn'): ?>
<script>
// 延迟设置 Prism autoloader 路径，确保 Prism 已加载
window.addEventListener('DOMContentLoaded', function() {
    if (window.Prism && Prism.plugins && Prism.plugins.autoloader) {
        Prism.plugins.autoloader.languages_path = '<?php echo (strpos($jsUrls['prismAutoloader'], 'http') === 0) ? dirname($jsUrls['prismAutoloader']) . '/../../components/' : $themeUrl . 'assets/vendor/prismjs/components/'; ?>';
    }
});
</script>
<?php endif; ?>
<?php endif; ?>

<!-- Lightbox2 图片灯箱脚本 - 文章/页面加载，开启Pjax时全站加载 -->
<?php if ($this->is('post') || $this->is('page') || (!empty($this->options->pjaxLoad) && $this->options->pjaxLoad === 'on')): ?>
<script src="<?php echo $jsUrls['lightbox']; ?>" defer></script>
<?php endif; ?>

<!-- Mermaid 图表渲染脚本 -->
<?php if (!empty($this->options->mermaidEnabled) && $this->options->mermaidEnabled === 'on'): ?>
<script src="<?php echo $jsUrls['mermaid']; ?>" async></script>
<?php endif; ?>

<!-- ECharts 图表渲染脚本 -->
<?php if (!empty($this->options->echartsEnabled) && $this->options->echartsEnabled === 'on'): ?>
<script src="<?php echo $jsUrls['echarts']; ?>" async></script>
<?php endif; ?>

<!-- KaTeX 数学公式渲染脚本 -->
<?php if (!empty($this->options->katexEnabled) && $this->options->katexEnabled === 'on'): ?>
<script src="<?php echo $jsUrls['katex']; ?>" async></script>
<script src="<?php echo $jsUrls['katexAutoRender']; ?>" async></script>
<?php endif; ?>

<!-- Pjax加载配置 -->
<script>
window.pjaxEnabled = <?php echo (!empty($this->options->pjaxLoad) && $this->options->pjaxLoad === 'on') ? 'true' : 'false'; ?>;
window.pjaxLoadStyle = '<?php echo !empty($this->options->pjaxLoadStyle) ? $this->options->pjaxLoadStyle : 'progress'; ?>';
window.pjaxTimeout = <?php echo !empty($this->options->pjaxTimeout) ? intval($this->options->pjaxTimeout) : 10000; ?>;
window.codeHighlightEnabled = <?php echo (empty($this->options->codeHighlightEnabled) || $this->options->codeHighlightEnabled !== 'off') ? 'true' : 'false'; ?>;
window.mermaidEnabled = <?php echo (!empty($this->options->mermaidEnabled) && $this->options->mermaidEnabled === 'on') ? 'true' : 'false'; ?>;
window.echartsEnabled = <?php echo (!empty($this->options->echartsEnabled) && $this->options->echartsEnabled === 'on') ? 'true' : 'false'; ?>;
window.katexEnabled = <?php echo (!empty($this->options->katexEnabled) && $this->options->katexEnabled === 'on') ? 'true' : 'false'; ?>;
</script>

<!-- 主题主脚本 -->
<script src="<?php echo $jsUrls['main']; ?>" defer></script>

<!-- Pjax库 - 仅开启Pjax时加载 -->
<?php if (!empty($this->options->pjaxLoad) && $this->options->pjaxLoad === 'on'): ?>
<script src="<?php echo $jsUrls['pjax_lib']; ?>" defer></script>

<!-- Pjax加载脚本 -->
<script src="<?php echo $jsUrls['pjax']; ?>" defer></script>
<?php endif; ?>

</body>
</html>