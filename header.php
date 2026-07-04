<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta name="renderer" content="webkit">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="theme-color" content="<?php echo !empty($this->options->themeColor) ? htmlspecialchars($this->options->themeColor) : '#FF6B6B'; ?>">
    <meta name="format-detection" content="telephone=no">
    <meta http-equiv="X-UA-Compatible" content="IE=edge, chrome=1">

    <?php if (!empty($this->options->faviconUrl)): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($this->options->faviconUrl); ?>" type="image/x-icon">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($this->options->faviconUrl); ?>" type="image/x-icon">
    <?php endif; ?>

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
    $siteTitle = $this->options->title;
    $siteUrl = $this->options->siteUrl;
    ?>
    
    <title><?php echo $seoTitle; ?></title>
    
    <!-- 基础 SEO meta 标签 -->
    <meta name="description" content="<?php echo $seoDescription; ?>">
    <meta name="keywords" content="<?php echo $seoKeywords; ?>">
    <meta name="robots" content="<?php echo $robotsContent; ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl); ?>">
    
    <!-- Open Graph 协议（Facebook / 微博 / QQ 等） -->
    <meta property="og:type" content="<?php echo $ogType; ?>">
    <meta property="og:title" content="<?php echo $seoTitle; ?>">
    <meta property="og:description" content="<?php echo $seoDescription; ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl); ?>">
    <meta property="og:site_name" content="<?php echo htmlspecialchars($siteTitle); ?>">
    <meta property="og:locale" content="zh_CN">
    <?php if (!empty($ogImage)): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($ogImage); ?>">
    <?php endif; ?>
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="<?php echo !empty($ogImage) ? 'summary_large_image' : 'summary'; ?>">
    <meta name="twitter:title" content="<?php echo $seoTitle; ?>">
    <meta name="twitter:description" content="<?php echo $seoDescription; ?>">
    <?php if (!empty($ogImage)): ?>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($ogImage); ?>">
    <?php endif; ?>
    
    <!-- 文章专属 OG meta -->
    <?php if ($archiveObj && $ogType === 'article'): ?>
    <meta property="article:published_time" content="<?php echo date('c', $archiveObj->created); ?>">
    <meta property="article:modified_time" content="<?php echo date('c', $archiveObj->modified); ?>">
    <meta property="article:author" content="<?php echo htmlspecialchars($archiveObj->author->screenName); ?>">
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
    <?php if ($archiveObj && $ogType === 'article'):
        $jsonLd = array(
            '@context' => 'https://schema.org',
            '@type'    => 'BlogPosting',
            'headline' => $archiveObj->title,
            'url'      => $archiveObj->permalink,
            'datePublished' => date('c', $archiveObj->created),
            'dateModified'  => date('c', $archiveObj->modified),
            'author'  => array(
                '@type' => 'Person',
                'name'  => $archiveObj->author->screenName
            ),
            'publisher' => array(
                '@type' => 'Organization',
                'name'  => $siteTitle,
                'url'   => $siteUrl
            ),
            'description' => strip_tags($seoDescription),
            'keywords'    => strip_tags($seoKeywords)
        );
        if (!empty($ogImage)) {
            $jsonLd['image'] = $ogImage;
        }
    ?>
    <script type="application/ld+json"><?php echo json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
    <?php else:
        $webJsonLd = array(
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => $siteTitle,
            'url'      => $siteUrl
        );
        if (!empty($this->options->description)) {
            $webJsonLd['description'] = strip_tags($this->options->description);
        }
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
    $cssUrls = [
        'normalize' => $themeUrl . 'assets/css/normalize.css',
        'grid' => $themeUrl . 'assets/css/grid.css',
        'style' => $themeUrl . 'assets/css/style.css',
        'fontawesome' => $themeUrl . 'assets/vendor/font-awesome/css/font-awesome.min.css',
        'prism' => $themeUrl . 'assets/vendor/prismjs/themes/prism-tomorrow.min.css',
        'lightbox' => $themeUrl . 'assets/vendor/lightbox2/css/lightbox.min.css',
        'katex' => $themeUrl . 'assets/vendor/katex/katex.min.css',
        'emoji' => $themeUrl . 'assets/vendor/jquery-emoji/css/jquery.emoji.css'
    ];
    
    // 根据配置调整资源路径
    if ($resourceMode === 'cdn') {
        // 使用官方CDN
        $cssUrls['fontawesome'] = 'https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css';
        $cssUrls['prism'] = 'https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism-tomorrow.min.css';
        $cssUrls['lightbox'] = 'https://cdn.jsdelivr.net/npm/lightbox2@2.11.4/dist/css/lightbox.min.css';
        $cssUrls['katex'] = 'https://cdn.jsdelivr.net/npm/katex@0.17.0/dist/katex.min.css';
    } elseif ($resourceMode === 'custom' && $customCdn) {
        // 使用自建CDN
        $cssUrls['normalize'] = $customCdn . '/assets/css/normalize.css';
        $cssUrls['grid'] = $customCdn . '/assets/css/grid.css';
        $cssUrls['style'] = $customCdn . '/assets/css/style.css';
        $cssUrls['fontawesome'] = $customCdn . '/assets/vendor/font-awesome/css/font-awesome.min.css';
        $cssUrls['prism'] = $customCdn . '/assets/vendor/prismjs/themes/prism-tomorrow.min.css';
        $cssUrls['lightbox'] = $customCdn . '/assets/vendor/lightbox2/css/lightbox.min.css';
        $cssUrls['katex'] = $customCdn . '/assets/vendor/katex/katex.min.css';
        $cssUrls['emoji'] = $customCdn . '/assets/vendor/jquery-emoji/css/jquery.emoji.css';
    }
    // local 模式使用默认的 themeUrl 路径
    
    // 资源版本号：使用文件修改时间，文件更新后自动刷新缓存
    $themeDir = dirname(__FILE__);
    $cssVersion = filemtime($themeDir . '/assets/css/style.css') ?: shufei_get_theme_version();
    ?>
    
    <!-- 本地 CSS -->
    <link rel="stylesheet" href="<?php echo $cssUrls['normalize']; ?>?v=<?php echo $cssVersion; ?>">
    <link rel="stylesheet" href="<?php echo $cssUrls['grid']; ?>?v=<?php echo $cssVersion; ?>">
    <link rel="stylesheet" href="<?php echo $cssUrls['style']; ?>?v=<?php echo $cssVersion; ?>">
    
    <!-- Font Awesome 图标库 -->
    <link rel="stylesheet" href="<?php echo $cssUrls['fontawesome']; ?>">
    
    <!-- Prism.js 代码高亮样式 -->
    <?php if (empty($this->options->codeHighlightEnabled) || $this->options->codeHighlightEnabled !== 'off'): ?>
    <link href="<?php echo $cssUrls['prism']; ?>" rel="stylesheet" />
    <?php endif; ?>
    
    <!-- Lightbox2 图片灯箱样式 - 文章/页面加载，开启Pjax时全站加载 -->
    <?php if ($this->is('post') || $this->is('page') || (!empty($this->options->pjaxLoad) && $this->options->pjaxLoad === 'on')): ?>
    <link href="<?php echo $cssUrls['lightbox']; ?>" rel="stylesheet" />
    <?php endif; ?>
    
    <!-- KaTeX 数学公式样式 -->
    <?php if (!empty($this->options->katexEnabled) && $this->options->katexEnabled === 'on'): ?>
    <link href="<?php echo $cssUrls['katex']; ?>" rel="stylesheet" />
    <?php endif; ?>

    <?php if (shufei_is_turnstile_enabled() && !empty(shufei_get_turnstile_site_key())): ?>
    <!-- Cloudflare Turnstile -->
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
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
    </script>
    
    <?php
    $themeColor = !empty($this->options->themeColor) ? $this->options->themeColor : '#FF6B6B';
    $bgColor = !empty($this->options->bgColor) ? $this->options->bgColor : '#f8f9fc';
    $bgImage = !empty($this->options->bgImage) ? $this->options->bgImage : '';
    $cardOpacity = isset($this->options->cardOpacity) && $this->options->cardOpacity !== '' ? floatval($this->options->cardOpacity) : 1;
    $cardOpacity = max(0, min(1, $cardOpacity));
    $hasCustomStyle = ($themeColor !== '#FF6B6B' || $bgColor !== '#f8f9fc' || $bgImage || $cardOpacity < 1);
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
    <?php endif; ?>
    <?php if ($cardOpacity < 1): ?>
    #header {
        background: rgba(255, 255, 255, <?php echo $cardOpacity; ?>) !important;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
    }
    .left-sidebar {
        background: rgba(255, 255, 255, <?php echo $cardOpacity; ?>) !important;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
    }
    .left-sidebar .widget {
        background: transparent !important;
    }
    .left-sidebar .links-nav-list {
        background: transparent !important;
    }
    .post {
        background: rgba(255, 255, 255, <?php echo $cardOpacity; ?>) !important;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
    }
    .widget {
        background: rgba(255, 255, 255, <?php echo $cardOpacity; ?>) !important;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
    }
    [data-theme="dark"] #header {
        background: rgba(26, 26, 36, <?php echo $cardOpacity; ?>) !important;
    }
    [data-theme="dark"] .left-sidebar {
        background: rgba(26, 26, 36, <?php echo $cardOpacity; ?>) !important;
    }
    [data-theme="dark"] .post,
    [data-theme="dark"] .widget {
        background: rgba(26, 26, 36, <?php echo $cardOpacity; ?>) !important;
    }
    [data-theme="dark"] .left-sidebar .links-nav-list {
        background: transparent !important;
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
                return false;
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
            
            <div class="site-name">
                <?php if ($this->options->logoUrl): ?>
                    <a id="logo" href="<?php $this->options->siteUrl(); ?>">
                        <img src="<?php $this->options->logoUrl() ?>" alt="<?php $this->options->title() ?>"/>
                        <span><?php $this->options->title() ?></span>
                    </a>
                <?php else: ?>
                    <a id="logo" href="<?php $this->options->siteUrl(); ?>">
                        <i class="fa fa-home"></i>
                        <span><?php $this->options->title() ?></span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        
        <div class="header-right">
            <div class="site-search">
                <form id="search" method="post" action="<?php $this->options->siteUrl(); ?>" role="search">
                    <label for="s" class="sr-only"><?php _e('搜索关键字'); ?></label>
                    <input type="text" id="s" name="s" class="text" placeholder="<?php _e('搜索文章...'); ?>"/>
                    <button type="submit" class="submit">
                        <i class="fa fa-search"></i>
                    </button>
                </form>
            </div>
            <button class="dark-mode-toggle" id="dark-mode-toggle" title="<?php _e('切换夜间模式'); ?>" aria-label="<?php _e('切换夜间模式'); ?>">
                <i class="fa fa-moon-o"></i>
            </button>
        </div>
    </div>
    <script>window.csrfToken = '<?php echo $this->security->getToken($this->request->getRequestUrl()); ?>';</script>
</header><!-- end #header -->

<div id="body">
    <!-- 移动端遮罩层 -->
    <div class="body-shade" id="body-shade"></div>
    
    <div class="container">
        <div class="row">