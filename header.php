<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta name="renderer" content="webkit">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="<?php echo !empty($this->options->themeColor) ? htmlspecialchars($this->options->themeColor) : '#FF6B6B'; ?>">
    <meta name="format-detection" content="telephone=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge, chrome=1">

    <?php
    // 优先使用用户配置的 Favicon，未配置时回退到主题自带的 favicon.ico
    $faviconUrl = !empty($this->options->faviconUrl)
        ? $this->options->faviconUrl
        : rtrim($this->options->themeUrl, '/') . '/favicon.ico';
    ?>
    <link rel="icon" href="<?php echo htmlspecialchars($faviconUrl); ?>" type="image/x-icon">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($faviconUrl); ?>" type="image/x-icon">

    <?php
    // SEO 优化：根据页面类型输出完整的标题、描述、关键词
    $seoTitle = shufei_get_seo_title();
    $seoDescription = shufei_get_seo_description();
    $seoKeywords = shufei_get_seo_keywords();
    $canonicalUrl = shufei_get_canonical_url();
    $robotsContent = shufei_get_robots_content();

    // 判断当前页面上下文（用于 OG / JSON-LD）
    $archiveObj = shufei_is_post() || shufei_is_page() ? shufei_get_archive() : null;
    $ogType = $archiveObj ? 'article' : 'website';
    $ogImage = shufei_get_seo_og_image($archiveObj);
    $ogImageDimensions = shufei_get_og_image_dimensions($ogImage);
    $siteTitle = $this->options->title;
    $siteUrl = $this->options->siteUrl;
    $prevNext = shufei_get_prev_next_page();
    ?>
    
    <title><?php echo $seoTitle; ?></title>
    
    <!-- 基础 SEO meta 标签 -->
    <meta name="description" content="<?php echo $seoDescription; ?>">
    <meta name="keywords" content="<?php echo $seoKeywords; ?>">
    <meta name="robots" content="<?php echo $robotsContent; ?>">
    <meta name="language" content="zh-CN">
    <meta name="applicable-device" content="pc,mobile">
    <meta name="author" content="<?php echo htmlspecialchars($archiveObj ? $archiveObj->author->screenName : $siteTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl); ?>">
    
    <!-- 分页 prev/next（帮助搜索引擎理解分页关系） -->
    <?php if (!empty($prevNext['prev'])): ?>
    <link rel="prev" href="<?php echo htmlspecialchars($prevNext['prev']); ?>">
    <?php endif; ?>
    <?php if (!empty($prevNext['next'])): ?>
    <link rel="next" href="<?php echo htmlspecialchars($prevNext['next']); ?>">
    <?php endif; ?>
    
    <!-- RSS / Atom Feed 自动发现 -->
    <link rel="alternate" type="application/rss+xml" title="<?php echo htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?> &raquo; RSS 2.0" href="<?php $this->options->feedUrl(); ?>">
    <link rel="alternate" type="application/rss+xml" title="<?php echo htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?> &raquo; 评论 RSS 2.0" href="<?php $this->options->commentsFeedUrl(); ?>">
    <?php if ($archiveObj && shufei_is_post()): ?>
    <link rel="alternate" type="application/rss+xml" title="<?php echo htmlspecialchars($siteTitle, ENT_QUOTES, 'UTF-8'); ?> &raquo; 文章评论 RSS 2.0" href="<?php echo htmlspecialchars($archiveObj->feedUrl); ?>">
    <?php endif; ?>

    <!-- Sitemap 自动发现 -->
    <link rel="sitemap" type="application/xml" href="<?php echo rtrim($this->options->themeUrl, '/') . '/sitemap.php'; ?>">
    
    <!-- DNS Prefetch 优化外部资源加载 -->
    <?php
    // 收集需要 dns-prefetch 的域名
    $dnsPrefetchHosts = array();
    if (!empty($this->options->gravatarMirror)) {
        $gravatarHost = parse_url($this->options->gravatarMirror, PHP_URL_HOST);
        if ($gravatarHost) $dnsPrefetchHosts[] = $gravatarHost;
    }
    if (!empty($this->options->customCdn)) {
        $cdnHost = parse_url($this->options->customCdn, PHP_URL_HOST);
        if ($cdnHost) $dnsPrefetchHosts[] = $cdnHost;
    }
    if (shufei_is_turnstile_enabled()) $dnsPrefetchHosts[] = 'challenges.cloudflare.com';
    if (shufei_is_geetest_enabled()) $dnsPrefetchHosts[] = 'static.geetest.com';
    if (shufei_is_catcaptcha_enabled()) {
        $catcaptchaApiBase = shufei_get_catcaptcha_api_base();
        $catcaptchaHost = parse_url($catcaptchaApiBase, PHP_URL_HOST);
        if ($catcaptchaHost) $dnsPrefetchHosts[] = $catcaptchaHost;
    }
    if (!empty($this->options->weatherEnabled) && $this->options->weatherEnabled === 'on') {
        $dnsPrefetchHosts[] = 'api.lwcat.cn';
    }
    $dnsPrefetchHosts = array_unique($dnsPrefetchHosts);
    foreach ($dnsPrefetchHosts as $host):
    ?>
    <link rel="dns-prefetch" href="//<?php echo htmlspecialchars($host); ?>">
    <?php endforeach; ?>
    
    <!-- Open Graph 协议（Facebook / 微博 / QQ 等） -->
    <meta property="og:type" content="<?php echo $ogType; ?>">
    <meta property="og:title" content="<?php echo $seoTitle; ?>">
    <meta property="og:description" content="<?php echo $seoDescription; ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl); ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($siteTitle); ?>">
    <meta property="og:locale" content="zh_CN">
    <?php if (!empty($ogImage)): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($ogImage); ?>">
    <?php if ($ogImageDimensions): ?>
    <meta property="og:image:width" content="<?php echo $ogImageDimensions['width']; ?>">
    <meta property="og:image:height" content="<?php echo $ogImageDimensions['height']; ?>">
    <meta property="og:image:alt" content="<?php echo htmlspecialchars($archiveObj ? $archiveObj->title : $siteTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php endif; ?>
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="<?php echo !empty($ogImage) ? 'summary_large_image' : 'summary'; ?>">
    <meta name="twitter:title" content="<?php echo $seoTitle; ?>">
    <meta name="twitter:description" content="<?php echo $seoDescription; ?>">
    <?php if (!empty($ogImage)): ?>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($ogImage); ?>">
    <meta name="twitter:image:alt" content="<?php echo htmlspecialchars($archiveObj ? $archiveObj->title : $siteTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    
    <!-- 文章专属 OG meta -->
    <?php if ($archiveObj && $ogType === 'article'):
        $wordCount = shufei_get_word_count($archiveObj);
        $readingTime = shufei_get_reading_time($archiveObj);
    ?>
    <meta property="article:published_time" content="<?php echo date('c', $archiveObj->created); ?>">
    <meta property="article:modified_time" content="<?php echo date('c', $archiveObj->modified); ?>">
    <meta property="article:author" content="<?php echo htmlspecialchars($archiveObj->author->screenName); ?>">
    <meta property="article:word_count" content="<?php echo $wordCount; ?>">
    <meta property="article:section" content="<?php
        $sectionName = '';
        if (!empty($archiveObj->categories)) {
            $cats = $archiveObj->categories;
            if (is_array($cats) && isset($cats[0]['name'])) {
                $sectionName = $cats[0]['name'];
            }
        }
        echo htmlspecialchars($sectionName);
    ?>">
    <?php if (!empty($archiveObj->tags) && is_array($archiveObj->tags)):
        foreach ($archiveObj->tags as $tag): ?>
    <meta property="article:tag" content="<?php echo htmlspecialchars($tag['name']); ?>">
    <?php endforeach; endif; ?>
    <?php endif; ?>
    
    <!-- 搜索引擎站点验证 -->
    <?php
    $verification = !empty($this->options->seoSiteVerification) ? trim($this->options->seoSiteVerification) : '';
    if (!empty($verification)) {
        $lines = preg_split('/\R/', $verification);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            // 已是完整 meta 标签
            if (stripos($line, '<meta') === 0) {
                echo $line . "\n    ";
            } else {
                // 视为 name=value 形式
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    echo '<meta name="' . htmlspecialchars(trim($parts[0])) . '" content="' . htmlspecialchars(trim($parts[1])) . '">' . "\n    ";
                }
            }
        }
    }
    ?>
    
    <!-- JSON-LD 结构化数据 -->
    <?php
    // 面包屑结构化数据（所有页面都可能有）
    $breadcrumbJsonLd = shufei_get_breadcrumbs_jsonld();
    if ($breadcrumbJsonLd): ?>
    <script type="application/ld+json"><?php echo json_encode($breadcrumbJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
    <?php endif;

    if ($archiveObj && $ogType === 'article'):
        $wordCount = shufei_get_word_count($archiveObj);
        $jsonLd = array(
            '@context' => 'https://schema.org',
            '@type'    => 'BlogPosting',
            'headline' => $archiveObj->title,
            'url'      => $archiveObj->permalink,
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id'   => $archiveObj->permalink
            ),
            'datePublished' => date('c', $archiveObj->created),
            'dateModified'  => date('c', $archiveObj->modified),
            'author'  => array(
                '@type' => 'Person',
                'name'  => $archiveObj->author->screenName,
                'url'   => $archiveObj->author->permalink
            ),
            'publisher' => array(
                '@type' => 'Organization',
                'name'  => $siteTitle,
                'url'   => $siteUrl,
                'logo'  => array(
                    '@type' => 'ImageObject',
                    'url'   => !empty($this->options->logoUrl) ? $this->options->logoUrl : rtrim($siteUrl, '/') . '/favicon.ico'
                )
            ),
            'description' => strip_tags($seoDescription),
            'keywords'    => strip_tags($seoKeywords),
            'wordCount'   => $wordCount
        );
        if (!empty($archiveObj->categories) && is_array($archiveObj->categories) && isset($archiveObj->categories[0]['name'])) {
            $jsonLd['articleSection'] = $archiveObj->categories[0]['name'];
        }
        if (!empty($ogImage)) {
            $jsonLd['image'] = array(
                '@type'  => 'ImageObject',
                'url'    => $ogImage,
                'width'  => $ogImageDimensions ? $ogImageDimensions['width'] : 1200,
                'height' => $ogImageDimensions ? $ogImageDimensions['height'] : 630
            );
        }
    ?>
    <script type="application/ld+json"><?php echo json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
    <?php else:
        // 首页/列表页 WebSite 结构化数据 + 站内搜索 Action
        $webJsonLd = array(
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => $siteTitle,
            'url'      => $siteUrl,
            'inLanguage' => 'zh-CN'
        );
        if (!empty($this->options->description)) {
            $webJsonLd['description'] = strip_tags($this->options->description);
        }
        // 站内搜索 SearchAction
        $webJsonLd['potentialAction'] = array(
            '@type'       => 'SearchAction',
            'target'      => array(
                '@type'       => 'EntryPoint',
                'urlTemplate' => rtrim($siteUrl, '/') . '/?s={search_term_string}'
            ),
            'query-input' => 'required name=search_term_string'
        );
        // publisher 信息
        $webJsonLd['publisher'] = array(
            '@type' => 'Organization',
            'name'  => $siteTitle,
            'url'   => $siteUrl,
            'logo'  => array(
                '@type' => 'ImageObject',
                'url'   => !empty($this->options->logoUrl) ? $this->options->logoUrl : rtrim($siteUrl, '/') . '/favicon.ico'
            )
        );
    ?>
    <script type="application/ld+json"><?php echo json_encode($webJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
    <?php endif; ?>
    
    <?php
    // 获取资源加载配置
    $resourceMode = !empty($this->options->resourceMode) ? $this->options->resourceMode : 'local';
    $customCdn = !empty($this->options->customCdn) ? rtrim($this->options->customCdn, '/') : '';
    
    // 获取主题基础URL，确保以斜杠结尾
    $themeUrl = rtrim($this->options->themeUrl, '/') . '/';
    
    // CSS 资源路径配置
    // 是否加载压缩版资源（默认开启）
    $useMin = empty($this->options->minifyAssets) || $this->options->minifyAssets === 'on';
    $normalizeCssFile = $useMin && file_exists(dirname(__FILE__) . '/assets/css/normalize.min.css') ? 'normalize.min.css' : 'normalize.css';
    $styleCssFile = $useMin && file_exists(dirname(__FILE__) . '/assets/css/style.min.css') ? 'style.min.css' : 'style.css';
    $cssUrls = [
        'normalize' => $themeUrl . 'assets/css/' . $normalizeCssFile,
        'style' => $themeUrl . 'assets/css/' . $styleCssFile,
        'fontawesome' => $themeUrl . 'assets/vendor/font-awesome/css/font-awesome.min.css',
        'prism' => $themeUrl . 'assets/vendor/prismjs/themes/prism-tomorrow.min.css',
        'lightbox' => $themeUrl . 'assets/vendor/lightbox3/lightbox3.css',
        'katex' => $themeUrl . 'assets/vendor/katex/katex.min.css',
        'emoji' => $themeUrl . 'assets/vendor/jquery-emoji/css/jquery.emoji.css'
    ];
    
    // 根据配置调整资源路径
    if ($resourceMode === 'cdn') {
        // 使用官方CDN
        $cssUrls['fontawesome'] = 'https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css';
        $cssUrls['prism'] = 'https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism-tomorrow.min.css';
        $cssUrls['lightbox'] = 'https://cdn.jsdelivr.net/npm/lightbox3@1.1.0/dist/lightbox3.css';
        $cssUrls['katex'] = 'https://cdn.jsdelivr.net/npm/katex@0.17.0/dist/katex.min.css';
    } elseif ($resourceMode === 'custom' && $customCdn) {
        // 使用自建CDN
        $cssUrls['normalize'] = $customCdn . '/assets/css/' . $normalizeCssFile;
        $cssUrls['style'] = $customCdn . '/assets/css/' . $styleCssFile;
        $cssUrls['fontawesome'] = $customCdn . '/assets/vendor/font-awesome/css/font-awesome.min.css';
        $cssUrls['prism'] = $customCdn . '/assets/vendor/prismjs/themes/prism-tomorrow.min.css';
        $cssUrls['lightbox'] = $customCdn . '/assets/vendor/lightbox3/lightbox3.css';
        $cssUrls['katex'] = $customCdn . '/assets/vendor/katex/katex.min.css';
        $cssUrls['emoji'] = $customCdn . '/assets/vendor/jquery-emoji/css/jquery.emoji.css';
    }
    // local 模式使用默认的 themeUrl 路径
    
    // 资源版本号：使用文件修改时间，文件更新后自动刷新缓存
    $themeDir = dirname(__FILE__);
    $cssVersion = filemtime($themeDir . '/assets/css/' . $styleCssFile) ?: shufei_get_theme_version();
    ?>
    
    <!-- 本地 CSS -->
    <link rel="stylesheet" href="<?php echo $cssUrls['normalize']; ?>?v=<?php echo $cssVersion; ?>">
    <link rel="stylesheet" href="<?php echo $cssUrls['style']; ?>?v=<?php echo $cssVersion; ?>">
    
    <!-- Font Awesome 图标库 -->
    <link rel="stylesheet" href="<?php echo $cssUrls['fontawesome']; ?>">
    
    <!-- Prism.js 代码高亮样式 - 按需加载（存在代码块时由 JS 动态注入） -->
    
    <!-- Lightbox3 图片灯箱样式 - 按需加载（存在图片时由 JS 动态注入） -->
    
    <!-- KaTeX 数学公式样式 - 按需加载（存在公式时由 JS 动态注入） -->

    <?php if (shufei_is_turnstile_enabled() && !empty(shufei_get_turnstile_site_key())): ?>
    <!-- Cloudflare Turnstile -->
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
    <?php endif; ?>

    <?php if (shufei_is_geetest_enabled() && !empty(shufei_get_geetest_captcha_id())): ?>
    <!-- 极验 Geetest v4 -->
    <script src="https://static.geetest.com/v4/gt4.js" async defer></script>
    <?php endif; ?>

    <?php if (shufei_is_catcaptcha_enabled() && !empty(shufei_get_catcaptcha_site_key())): ?>
    <!-- Cat-Captcha 人机验证 -->
    <link rel="stylesheet" href="<?php echo rtrim(shufei_get_catcaptcha_api_base(), '/'); ?>/public/captcha.css?v=1.0.6">
    <script src="<?php echo rtrim(shufei_get_catcaptcha_api_base(), '/'); ?>/public/captcha.js?v=1.0.6" async defer></script>
    <?php endif; ?>
    
    <script>
    window.themeUrl = '<?php echo rtrim($this->options->themeUrl, '/') . '/'; ?>';
    <?php
    $resourceMode = !empty($this->options->resourceMode) ? $this->options->resourceMode : 'local';
    $customCdn = !empty($this->options->customCdn) ? rtrim($this->options->customCdn, '/') : '';
    $emojiAssetBase = rtrim($this->options->themeUrl, '/') . '/assets/vendor/jquery-emoji';
    if ($resourceMode === 'custom' && $customCdn) {
        $emojiAssetBase = $customCdn . '/assets/vendor/jquery-emoji';
    }
    ?>
    window.emojiAssetBase = '<?php echo $emojiAssetBase; ?>';
    // 按需加载的 CSS 资源地址（仅在页面存在对应内容时由 JS 动态注入）
    window.vendorCssUrls = {
        prism: '<?php echo (empty($this->options->codeHighlightEnabled) || $this->options->codeHighlightEnabled !== "off") ? $cssUrls["prism"] : ""; ?>',
        katex: '<?php echo (!empty($this->options->katexEnabled) && $this->options->katexEnabled === "on") ? $cssUrls["katex"] : ""; ?>',
        lightbox: '<?php echo $cssUrls["lightbox"]; ?>'
    };
    </script>
    
    <?php
    $themeColor = !empty($this->options->themeColor) ? $this->options->themeColor : '#FF6B6B';
    $bgColor = !empty($this->options->bgColor) ? $this->options->bgColor : '#f8f9fc';
    $bgImage = !empty($this->options->bgImage) ? $this->options->bgImage : '';
    $bgGradientEnabled = !empty($this->options->bgGradientEnabled) && $this->options->bgGradientEnabled === 'on';
    $bgGradient = !empty($this->options->bgGradient) ? trim($this->options->bgGradient) : '';
    $bgGradientDark = !empty($this->options->bgGradientDark) ? trim($this->options->bgGradientDark) : '';
    $bgGradientAttachment = !empty($this->options->bgGradientAttachment) && $this->options->bgGradientAttachment === 'scroll' ? 'scroll' : 'fixed';
    // 渐变背景仅在未设置背景图片时生效
    $bgGradientActive = $bgGradientEnabled && $bgGradient && !$bgImage;
    $cardOpacity = isset($this->options->cardOpacity) && $this->options->cardOpacity !== '' ? floatval($this->options->cardOpacity) : 0.7;
    $cardOpacity = max(0, min(1, $cardOpacity));
    $hasCustomStyle = ($themeColor !== '#FF6B6B' || $bgColor !== '#f8f9fc' || $bgImage || $bgGradientActive || $cardOpacity < 1);
    if ($hasCustomStyle):
    ?>
    <style>
    :root {
        <?php if ($themeColor !== '#FF6B6B'): ?>--primary-color: <?php echo htmlspecialchars($themeColor); ?>;
        --primary-hover: color-mix(in srgb, <?php echo htmlspecialchars($themeColor); ?> 80%, #000);<?php endif; ?>
        <?php if ($bgColor !== '#f8f9fc'): ?>--bg-color: <?php echo htmlspecialchars($bgColor); ?>;<?php endif; ?>
        <?php if ($cardOpacity < 1): ?>--card-bg: rgba(255, 255, 255, <?php echo $cardOpacity; ?>);<?php endif; ?>
    }
    <?php if ($bgImage): ?>
    body {
        background-image: url('<?php echo htmlspecialchars($bgImage); ?>');
        background-size: cover;
        background-attachment: fixed;
        background-position: center center;
        background-repeat: no-repeat;
    }
    <?php elseif ($bgGradientActive): ?>
    body {
        background-image: <?php echo htmlspecialchars($bgGradient); ?>;
        background-attachment: <?php echo $bgGradientAttachment; ?>;
        background-size: cover;
        background-position: center center;
        background-repeat: no-repeat;
    }
    <?php if ($bgGradientDark): ?>
    [data-theme="dark"] body {
        background-image: <?php echo htmlspecialchars($bgGradientDark); ?>;
    }
    <?php else: ?>
    [data-theme="dark"] body {
        background-image: none;
    }
    <?php endif; ?>
    <?php endif; ?>
    <?php if ($cardOpacity < 1): ?>
    /* 暗黑模式下覆盖 --card-bg 变量，避免半透明白色背景导致文字看不清 */
    [data-theme="dark"] {
        --card-bg: #1a1a24;
    }
    #header {
        background: rgba(255, 255, 255, <?php echo $cardOpacity; ?>) !important;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
    }
    .left-sidebar {
        background: rgba(255, 255, 255, <?php echo round($cardOpacity * 0.2, 2); ?>) !important;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border-right: 1px solid rgba(255, 255, 255, 0.25) !important;
    }
    .left-sidebar .sidebar-panel {
        background: rgba(255, 255, 255, <?php echo round($cardOpacity * 0.55, 2); ?>) !important;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.35) !important;
    }
    .left-sidebar .sidebar-panel-menu {
        background: rgba(255, 255, 255, <?php echo round($cardOpacity * 0.3, 2); ?>) !important;
    }
    .left-sidebar .widget {
        background: transparent !important;
    }
    .left-sidebar .links-nav-list {
        background: transparent !important;
    }
    .left-sidebar .widget-title {
        background: rgba(255, 255, 255, 0.12) !important;
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
    }
    .left-sidebar .widget-title:hover {
        background: rgba(255, 255, 255, 0.2) !important;
    }
    .left-sidebar .widget:hover {
        background: rgba(255, 255, 255, <?php echo round($cardOpacity * 0.15, 2); ?>) !important;
    }
    .author-card {
        background: transparent !important;
    }
    .sidebar-footer {
        background: transparent !important;
    }
    .post {
        background-color: rgba(255, 255, 255, <?php echo $cardOpacity; ?>) !important;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
    }
    .post-list-classic .post.has-thumbnail {
        background-color: transparent !important;
    }
    .left-sidebar .widget-title,
    .left-sidebar .sidebar-direct-link {
        color: #222;
    }
    .left-sidebar .category-nav-item a,
    .left-sidebar .links-widget .links-nav-item a,
    .left-sidebar .page-nav-widget .page-nav-list li a,
    .left-sidebar .other-widget .widget-list li a,
    .left-sidebar .widget-list li a {
        color: #333;
    }
    .left-sidebar .category-nav-item a i,
    .left-sidebar .links-widget .links-nav-item a i,
    .left-sidebar .page-nav-widget .page-nav-list li a i {
        color: #666;
    }
    .left-sidebar .collapsible-arrow,
    .left-sidebar .category-nav-item a .cat-toggle {
        color: #999;
    }
    .author-signature,
    .author-stat-label {
        color: #666;
    }
    .sidebar-footer {
        color: #888;
    }
    .sidebar-contacts a {
        color: #666;
    }
    .widget {
        background: rgba(255, 255, 255, <?php echo $cardOpacity; ?>) !important;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
    }
    /* 天气卡片已有自身玻璃背景，避免与 widget 半透明白色叠加导致偏白 */
    .weather-widget {
        background: transparent !important;
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
    }
    .weather-loading,
    .weather-error {
        background: transparent !important;
    }
    #footer {
        background: rgba(255, 255, 255, <?php echo $cardOpacity; ?>) !important;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
    }
    .page-navigator a,
    .page-navigator span {
        background: rgba(255, 255, 255, <?php echo $cardOpacity; ?>) !important;
    }
    .page-navigator a:hover {
        background: rgba(255, 255, 255, <?php echo round(min(1, $cardOpacity + 0.08), 2); ?>) !important;
    }
    .page-navigator .current a,
    .page-navigator .current span {
        background: var(--primary-color) !important;
        color: #fff;
    }
    [data-theme="dark"] #header {
        background: rgba(26, 26, 36, <?php echo max($cardOpacity, 0.95); ?>) !important;
    }
    [data-theme="dark"] .left-sidebar {
        background: rgba(26, 26, 36, <?php echo max(round($cardOpacity * 0.2, 2), 0.18); ?>) !important;
        border-right-color: rgba(255, 255, 255, 0.08) !important;
    }
    [data-theme="dark"] .left-sidebar .sidebar-panel {
        background: rgba(26, 26, 36, <?php echo max(round($cardOpacity * 0.55, 2), 0.5); ?>) !important;
        border-color: rgba(255, 255, 255, 0.08) !important;
    }
    [data-theme="dark"] .left-sidebar .sidebar-panel-menu {
        background: rgba(26, 26, 36, <?php echo max(round($cardOpacity * 0.3, 2), 0.28); ?>) !important;
    }
    [data-theme="dark"] .post {
        background-color: #1a1a24 !important;
    }
    [data-theme="dark"] .post-list-classic .post.has-thumbnail {
        background-color: transparent !important;
    }
    [data-theme="dark"] .widget {
        background: #1a1a24 !important;
    }
    [data-theme="dark"] #footer {
        background: #1a1a24 !important;
    }
    [data-theme="dark"] .page-navigator a,
    [data-theme="dark"] .page-navigator span {
        background: #1a1a24 !important;
    }
    [data-theme="dark"] .page-navigator a:hover {
        background: color-mix(in srgb, var(--primary-color) 12%, transparent) !important;
    }
    [data-theme="dark"] .page-navigator .current a,
    [data-theme="dark"] .page-navigator .current span {
        background: var(--primary-color) !important;
        color: #fff;
    }
    [data-theme="dark"] .left-sidebar .links-nav-list {
        background: transparent !important;
    }
    [data-theme="dark"] .left-sidebar .widget-title {
        background: rgba(255, 255, 255, 0.05) !important;
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
    }
    [data-theme="dark"] .left-sidebar .widget-title:hover {
        background: rgba(255, 255, 255, 0.1) !important;
    }
    [data-theme="dark"] .left-sidebar .widget:hover {
        background: rgba(255, 255, 255, 0.03) !important;
    }
    [data-theme="dark"] .author-card {
        background: transparent !important;
    }
    [data-theme="dark"] .sidebar-footer {
        background: transparent !important;
    }
    /* 暗黑模式下覆盖侧边栏文字颜色，避免透明模式内联的亮色样式导致文字看不清 */
    [data-theme="dark"] .left-sidebar .widget-title,
    [data-theme="dark"] .left-sidebar .sidebar-direct-link {
        color: #d4d4d8 !important;
    }
    [data-theme="dark"] .left-sidebar .category-nav-item a,
    [data-theme="dark"] .left-sidebar .links-widget .links-nav-item a,
    [data-theme="dark"] .left-sidebar .page-nav-widget .page-nav-list li a,
    [data-theme="dark"] .left-sidebar .other-widget .widget-list li a,
    [data-theme="dark"] .left-sidebar .widget-list li a {
        color: #b0b0b8 !important;
    }
    [data-theme="dark"] .left-sidebar .category-nav-item a i,
    [data-theme="dark"] .left-sidebar .links-widget .links-nav-item a i,
    [data-theme="dark"] .left-sidebar .page-nav-widget .page-nav-list li a i {
        color: #909098 !important;
    }
    [data-theme="dark"] .left-sidebar .collapsible-arrow {
        color: #909098 !important;
    }
    [data-theme="dark"] .author-signature,
    [data-theme="dark"] .author-stat-label {
        color: #b0b0b8 !important;
    }
    [data-theme="dark"] .sidebar-footer {
        color: #909098 !important;
    }
    [data-theme="dark"] .sidebar-contacts a {
        color: #b0b0b8 !important;
    }
    <?php endif; ?>
    </style>
    <?php endif; ?>

    <?php $this->header(); ?>

    <?php
    // 全站注入 TypechoComment 实现（兼容 PJAX）
    // 原生 TypechoComment 仅在 single 页面由 $this->header() 输出，且 respondId 硬编码，
    // 通过 PJAX 从非文章页进入文章时未定义，或文章间切换时 respondId 失效，导致点击回复触发页面跳转。
    // 此处在 <head> 末尾覆盖，动态查找 respondId，保证全站可用。
    ?>
    <script>
    (function () {
        window.TypechoComment = {
            dom: function (sel) {
                return document.querySelector(sel);
            },
            visiable: function (el, show) {
                if (el) el.style.display = show ? '' : 'none';
            },
            create: function (tag, attr) {
                var el = document.createElement(tag);
                for (var key in attr) {
                    if (Object.prototype.hasOwnProperty.call(attr, key)) {
                        el.setAttribute(key, attr[key]);
                    }
                }
                return el;
            },
            inputParent: function (response, coid) {
                var form = 'form' === response.tagName ? response : response.querySelector('form');
                if (!form) return;
                var input = form.querySelector('input[name=parent]');
                if (null == input && coid) {
                    input = this.create('input', { 'type': 'hidden', 'name': 'parent' });
                    form.appendChild(input);
                }
                if (coid) {
                    input.setAttribute('value', coid);
                } else if (input) {
                    input.parentNode.removeChild(input);
                }
            },
            getChild: function (root, node) {
                var parentNode = node.parentNode;
                if (parentNode === null) return null;
                if (parentNode === root) return node;
                return this.getChild(root, parentNode);
            },
            // 动态定位当前页面的 respond 容器 id（respond-post-XX / respond-page-XX）
            getRespondId: function () {
                var form = document.getElementById('comment-form');
                if (form) {
                    var respondEl = form.closest('[id^="respond-"]');
                    if (respondEl && respondEl.id) return respondEl.id;
                }
                var fallback = document.querySelector('[id^="respond-post-"], [id^="respond-page-"]');
                return fallback ? fallback.id : null;
            },
            reply: function (htmlId, coid, btn) {
                var respondId = this.getRespondId();
                if (!respondId) return true;

                var response = this.dom('#' + respondId);
                if (!response) return true;

                var comment = this.dom('#' + htmlId);
                if (!comment) return true;

                var child = this.getChild(comment, btn);

                this.inputParent(response, coid);

                if (this.dom('#' + respondId + '-holder') === null) {
                    var holder = this.create('div', { 'id': respondId + '-holder' });
                    response.parentNode.insertBefore(holder, response);
                }

                if (child) {
                    comment.insertBefore(response, child.nextSibling);
                } else {
                    comment.appendChild(response);
                }

                this.visiable(this.dom('#cancel-comment-reply-link'), true);

                var textarea = response.querySelector('textarea[name=text]');
                if (null != textarea) {
                    textarea.focus();
                }

                this.refreshTurnstile();
                this.refreshGeetest();
                this.refreshCatCaptcha();

                return false;
            },
            cancelReply: function () {
                var respondId = this.getRespondId();
                if (!respondId) return true;

                var response = this.dom('#' + respondId);
                if (!response) return true;

                var holder = this.dom('#' + respondId + '-holder');

                this.inputParent(response, false);

                if (null === holder) {
                    return true;
                }

                this.visiable(this.dom('#cancel-comment-reply-link'), false);
                holder.parentNode.insertBefore(response, holder);
                this.refreshTurnstile();
                this.refreshGeetest();
                this.refreshCatCaptcha();
                return false;
            },
            // 回复/取消回复移动 DOM 后，重置极验 Geetest v4 验证状态
            refreshGeetest: function () {
                if (window.geetestCaptchaObj) {
                    try { window.geetestCaptchaObj.reset(); } catch (e) {}
                }
                if (typeof window.geetestResult !== 'undefined') {
                    window.geetestResult = null;
                }
            },
            // 回复/取消回复移动 DOM 后，重置 Cat-Captcha 触发框（清空已获取的票据）
            refreshCatCaptcha: function () {
                if (typeof window.catCaptchaBox !== 'undefined' && window.catCaptchaBox) {
                    try { window.catCaptchaBox.reset(); } catch (e) {}
                }
                if (typeof window.catCaptchaTicket !== 'undefined') {
                    window.catCaptchaTicket = null;
                }
                var ticketInput = this.dom('#catcaptcha-ticket');
                if (ticketInput) ticketInput.value = '';
            },
            // 回复/取消回复移动 DOM 后，已渲染的 Turnstile iframe 会失效，需移除后重新渲染
            refreshTurnstile: function () {
                var container = this.dom('#cf-turnstile');
                if (!container) return;

                var widgetId = container.getAttribute('data-turnstile-widget-id');
                if (widgetId && typeof window.turnstile !== 'undefined') {
                    try { window.turnstile.remove(widgetId); } catch (e) {}
                }

                container.innerHTML = '';
                container.removeAttribute('data-turnstile-rendered');
                container.removeAttribute('data-turnstile-widget-id');

                if (typeof window.turnstile !== 'undefined') {
                    try {
                        var newWidgetId = window.turnstile.render('#cf-turnstile');
                        if (newWidgetId) {
                            container.setAttribute('data-turnstile-rendered', 'true');
                            container.setAttribute('data-turnstile-widget-id', newWidgetId);
                        }
                    } catch (e) {}
                }
            }
        };
    })();
    </script>
</head>
<body>

<header id="header">
    <div class="header-inner">
        <div class="header-left">
            <!-- 移动端菜单按钮 -->
            <button class="mobile-menu-btn" id="mobile-menu-btn" title="<?php _e('展开菜单'); ?>">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </button>
            
            <?php
            $logoDisplayMode = !empty($this->options->logoDisplayMode) ? $this->options->logoDisplayMode : 'auto';
            // 未填写 LOGO 时，智能模式/仅 LOGO 模式回退为仅显示标题，避免移动端丢失标题
            if (empty($this->options->logoUrl) && in_array($logoDisplayMode, array('auto', 'logo-only'), true)) {
                $logoDisplayMode = 'title-only';
            }
            ?>
            <div class="site-name logo-mode-<?php echo htmlspecialchars($logoDisplayMode); ?>">
                <?php if ($this->options->logoUrl): ?>
                    <a id="logo" href="<?php $this->options->siteUrl(); ?>">
                        <img src="<?php $this->options->logoUrl() ?>" alt="<?php $this->options->title() ?>"/>
                        <span class="site-title-text"><?php $this->options->title() ?></span>
                    </a>
                <?php else: ?>
                    <a id="logo" href="<?php $this->options->siteUrl(); ?>">
                        <i class="fa fa-home"></i>
                        <span class="site-title-text"><?php $this->options->title() ?></span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        
        <div class="header-right">
            <div class="site-search">
                <form id="search" method="get" action="<?php $this->options->siteUrl(); ?>" role="search">
                    <label for="s" class="sr-only"><?php _e('搜索关键字'); ?></label>
                    <input type="text" id="s" name="s" class="text" placeholder="<?php _e('搜索文章...'); ?>"/>
                    <button type="submit" class="submit">
                        <i class="fa fa-search"></i>
                    </button>
                </form>
            </div>
            <div class="header-nav-fav" id="header-nav-fav">
                <button class="nav-fav-btn" title="<?php _e('我的收藏'); ?>" aria-label="<?php _e('我的收藏'); ?>" aria-haspopup="true" aria-expanded="false">
                    <i class="fa fa-heart-o"></i>
                </button>
                <div class="nav-fav-panel" role="menu" aria-label="<?php _e('收藏列表'); ?>">
                    <div class="nav-fav-header"><?php _e('我的收藏'); ?></div>
                    <ul class="fav-list"></ul>
                </div>
            </div>
            <button class="dark-mode-toggle" id="dark-mode-toggle" title="<?php _e('切换夜间模式'); ?>" aria-label="<?php _e('切换夜间模式'); ?>">
                <i class="fa fa-moon-o"></i>
            </button>
        </div>
    </div>
    <?php
    // 生成 CSRF token 并同步写入 session，供 ajax-handler.php 校验
    // 性能优化：写完 token 后立即 session_write_close() 释放文件锁。
    // 否则 PHP 会持有 session 文件独占锁直到请求结束，点赞/加载更多/AI 摘要等
    // 并发 AJAX 会被串行化阻塞。
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $shufeiAjaxToken = $this->security->getToken('shufei_ajax');
    $_SESSION['shufei_ajax_token'] = $shufeiAjaxToken;
    session_write_close();
    ?>
    <script>window.csrfToken = '<?php echo $shufeiAjaxToken; ?>';</script>
</header><!-- end #header -->

<div id="body">
    <!-- 移动端遮罩层 -->
    <div class="body-shade" id="body-shade"></div>
    
    <div class="container">
        <div class="row">