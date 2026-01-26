<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta name="renderer" content="webkit">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title><?php $this->archiveTitle([
            'category' => _t('分类 %s 下的文章'),
            'search'   => _t('包含关键字 %s 的文章'),
            'tag'      => _t('标签 %s 下的文章'),
            'author'   => _t('%s 发布的文章')
        ], '', ' - '); ?><?php $this->options->title(); ?></title>
    
    <meta name="description" content="<?php $this->options->description() ?>">
    
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
        'fontawesome' => 'https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css',
        'prism' => 'https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism-tomorrow.min.css',
        'lightbox' => 'https://cdn.jsdelivr.net/npm/lightbox2@2.11.4/dist/css/lightbox.min.css'
    ];
    
    // 根据配置调整资源路径
    if ($resourceMode === 'cdn') {
        // 使用官方CDN（保持默认）
    } elseif ($resourceMode === 'custom' && $customCdn) {
        // 使用自建CDN
        $cssUrls['normalize'] = $customCdn . '/assets/css/normalize.css';
        $cssUrls['grid'] = $customCdn . '/assets/css/grid.css';
        $cssUrls['style'] = $customCdn . '/assets/css/style.css';
    }
    // local 模式使用默认的 themeUrl 路径
    ?>
    
    <!-- 本地 CSS -->
    <link rel="stylesheet" href="<?php echo $cssUrls['normalize']; ?>">
    <link rel="stylesheet" href="<?php echo $cssUrls['grid']; ?>">
    <link rel="stylesheet" href="<?php echo $cssUrls['style']; ?>">
    
    <!-- Font Awesome 图标库 -->
    <link rel="stylesheet" href="<?php echo $cssUrls['fontawesome']; ?>">
    
    <!-- Prism.js 代码高亮样式 -->
    <link href="<?php echo $cssUrls['prism']; ?>" rel="stylesheet" />
    
    <!-- Lightbox2 图片灯箱样式 -->
    <link href="<?php echo $cssUrls['lightbox']; ?>" rel="stylesheet" />
    
    <?php if (shufei_is_turnstile_enabled() && !empty(shufei_get_turnstile_site_key())): ?>
    <!-- Cloudflare Turnstile -->
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
    
    <?php $this->header(); ?>
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
                <?php if ($this->options->description): ?>
                    <p class="description"><?php $this->options->description() ?></p>
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
        </div>
    </div>
</header><!-- end #header -->

<div id="body">
    <!-- 移动端遮罩层 -->
    <div class="body-shade" id="body-shade"></div>
    
    <div class="container">
        <div class="row">
