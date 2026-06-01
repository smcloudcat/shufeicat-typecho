<?php
/**
 * ShuFeiCat主题 - 主页模板
 *
 * @package ShuFeiCat
 * @author YunCat
 * @version 1.3.1
 * @link https://lwcat.cn
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<!-- 左侧侧边栏 - 分类和其他 -->
<div class="left-sidebar" id="left-sidebar">
    <div class="sidebar-inner">
        <!-- 站长信息卡片 -->
        <div class="author-card">
            <div class="author-avatar-wrap">
                <img class="author-avatar" src="<?php echo !empty($this->options->authorAvatar) ? $this->options->authorAvatar : 'https://q1.qlogo.cn/g?b=qq&nk=3522934828&s=100'; ?>" alt="<?php echo !empty($this->options->authorName) ? $this->options->authorName : '云猫'; ?>">
            </div>
            <div class="author-name"><?php echo !empty($this->options->authorName) ? $this->options->authorName : '云猫'; ?></div>
            <div class="author-signature"><?php echo !empty($this->options->authorSignature) ? $this->options->authorSignature : 'Hello,world'; ?></div>
            <div class="author-stats">
                <?php $stat = \Widget\Stat::alloc(); ?>
                <div class="author-stat-item">
                    <span class="author-stat-num"><?php echo $stat->publishedPostsNum; ?></span>
                    <span class="author-stat-label">文章</span>
                </div>
                <div class="author-stat-item">
                    <span class="author-stat-num"><?php echo $stat->categoriesNum; ?></span>
                    <span class="author-stat-label">分类</span>
                </div>
                <div class="author-stat-item">
                    <span class="author-stat-num"><?php echo $stat->tagsNum; ?></span>
                    <span class="author-stat-label">标签</span>
                </div>
            </div>
        </div>

        <!-- 分类目录 -->
        <section class="widget category-widget collapsible-widget">
            <h3 class="widget-title collapsible-toggle"><i class="fa fa-navicon"></i><?php _e('分类目录'); ?><i class="fa fa-chevron-down collapsible-arrow"></i></h3>
            <div class="collapsible-content">
                <ul class="category-nav-list">
                    <?php
                    $categories = \Widget\Metas\Category\Rows::alloc();
                    while ($categories->next()):
                    ?>
                        <li class="category-nav-item <?php if($this->is('category', $categories->slug)): ?>active<?php endif; ?>">
                            <a href="<?php $categories->permalink(); ?>">
                                <i class="fa fa-folder-open-o"></i>
                                <span><?php $categories->name(); ?></span>
                            </a>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </div>
        </section>

        <!-- 页面导航 -->
        <section class="widget page-nav-widget collapsible-widget">
            <h3 class="widget-title collapsible-toggle"><i class="fa fa-sitemap"></i><?php _e('页面导航'); ?><i class="fa fa-chevron-down collapsible-arrow"></i></h3>
            <div class="collapsible-content">
                <ul class="widget-list page-nav-list">
                    <li>
                        <a href="<?php $this->options->siteUrl(); ?>" <?php if ($this->is('index')): ?>class="current"<?php endif; ?>>
                            <i class="fa fa-home"></i>
                            <span><?php _e('首页'); ?></span>
                        </a>
                    </li>
                    <?php \Widget\Contents\Page\Rows::alloc()->to($pages); ?>
                    <?php while ($pages->next()): ?>
                        <li>
                            <a href="<?php $pages->permalink(); ?>" <?php if ($this->is('page', $pages->slug)): ?>class="current"<?php endif; ?>>
                                <i class="fa fa-file-text-o"></i>
                                <span><?php $pages->title(); ?></span>
                            </a>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </div>
        </section>

        <!-- 友链 -->
        <?php if (!empty($this->options->sidebarBlock) && in_array('ShowLinks', $this->options->sidebarBlock) && !empty($this->options->links)): ?>
        <section class="widget links-widget collapsible-widget">
            <h3 class="widget-title collapsible-toggle"><i class="fa fa-link"></i><?php _e('友链'); ?><i class="fa fa-chevron-down collapsible-arrow"></i></h3>
            <div class="collapsible-content">
                <ul class="links-nav-list">
                    <?php
                    $links = explode("\n", $this->options->links);
                    foreach ($links as $link) {
                        $link = trim($link);
                        if (empty($link)) continue;
                        $parts = explode(',', $link, 2);
                        if (count($parts) == 2) {
                            $name = trim($parts[0]);
                            $url = trim($parts[1]);
                            echo '<li class="links-nav-item"><a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener"><i class="fa fa-external-link"></i><span>' . htmlspecialchars($name) . '</span></a></li>';
                        }
                    }
                    ?>
                </ul>
            </div>
        </section>
        <?php endif; ?>

        <!-- 其它 -->
        <?php if (!empty($this->options->sidebarBlock) && in_array('ShowOther', $this->options->sidebarBlock)): ?>
        <section class="widget other-widget collapsible-widget">
            <h3 class="widget-title collapsible-toggle"><i class="fa fa-cogs"></i><?php _e('其它'); ?><i class="fa fa-chevron-down collapsible-arrow"></i></h3>
            <div class="collapsible-content">
                <ul class="widget-list">
                    <?php if ($this->user->hasLogin()): ?>
                        <li>
                            <a href="<?php $this->options->adminUrl(); ?>">
                                <i class="fa fa-dashboard"></i><?php _e('进入后台'); ?> (<?php $this->user->screenName(); ?>)
                            </a>
                        </li>
                        <li>
                            <a href="<?php $this->options->logoutUrl(); ?>">
                                <i class="fa fa-sign-out"></i><?php _e('退出'); ?>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="last">
                            <a href="<?php $this->options->adminUrl('login.php'); ?>">
                                <i class="fa fa-sign-in"></i><?php _e('登录'); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li>
                        <a href="<?php $this->options->feedUrl(); ?>">
                            <i class="fa fa-rss"></i><?php _e('文章 RSS'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php $this->options->commentsFeedUrl(); ?>">
                            <i class="fa fa-rss-square"></i><?php _e('评论 RSS'); ?>
                        </a>
                    </li>
                </ul>
            </div>
        </section>
        <?php endif; ?>
        
        <!-- 联系方式 -->
        <?php
        $_opts = \Typecho\Widget::widget('Widget_Options');
        $authorEmail = isset($_opts->authorEmail) ? trim($_opts->authorEmail) : '';
        $authorGithub = isset($_opts->authorGithub) ? trim($_opts->authorGithub) : '';
        $authorQQ = isset($_opts->authorQQ) ? trim($_opts->authorQQ) : '';
        $hasContacts = ($authorEmail !== '' || $authorGithub !== '' || $authorQQ !== '');
        if ($hasContacts):
        ?>
        <div class="sidebar-contacts">
            <?php if ($authorEmail !== ''): ?>
            <a href="mailto:<?php echo htmlspecialchars($authorEmail); ?>" title="<?php echo htmlspecialchars($authorEmail); ?>" target="_blank" rel="noopener">
                <i class="fa fa-envelope-o"></i>
            </a>
            <?php endif; ?>
            <?php if ($authorGithub !== ''): ?>
            <a href="<?php echo htmlspecialchars($authorGithub); ?>" title="GitHub" target="_blank" rel="noopener">
                <i class="fa fa-github"></i>
            </a>
            <?php endif; ?>
            <?php if ($authorQQ !== ''): ?>
            <a href="http://wpa.qq.com/msgrd?v=3&uin=<?php echo htmlspecialchars($authorQQ); ?>&site=qq&menu=yes" title="QQ: <?php echo htmlspecialchars($authorQQ); ?>" target="_blank" rel="noopener">
                <i class="fa fa-qq"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- 侧边栏底部信息 -->
        <div class="sidebar-footer">
            <p>© <?php echo date('Y'); ?> <?php $this->options->title(); ?></p>
            <p style="margin-top: 5px; font-size: 11px;">Theme by ShuFeiCat</p>
        </div>
    </div>
</div>

<!-- 主内容区域 -->
<div class="col-mb-12 col-8" id="main" role="main">
    <?php if (!($this->is('index')) && !($this->is('post'))): ?>
    <div class="archive-title">
        <i class="fa fa-folder-open-o"></i>
        <?php $this->archiveTitle([
            'category' => _t('分类 %s 下的文章'),
            'search'   => _t('包含关键字 %s 的文章'),
            'tag'      => _t('标签 %s 下的文章'),
            'author'   => _t('%s 发布的文章')
        ], '', ''); ?>
    </div>
    <?php endif; ?>
    
    <?php if ($this->have()): ?>
    <div id="ajax-post-list" class="post-list-<?php echo !empty($this->options->postListStyle) ? $this->options->postListStyle : 'card'; ?>">
    <?php
    // 分离置顶文章和普通文章
    $stickyPosts = array();
    $normalPosts = array();
    
    while ($this->next()) {
        if ($this->fields->sticky == '1') {
            $stickyPosts[] = clone $this;
        } else {
            $normalPosts[] = clone $this;
        }
    }
    
    // 合并文章列表：置顶文章在前
    $allPosts = array_merge($stickyPosts, $normalPosts);
    ?>
    <?php foreach ($allPosts as $post): ?>
        <?php
        $thumbnail = shufei_get_post_thumbnail($post);
        
        $excerpt = '';
        if (!$post->hidden) {
            $customExcerpt = $post->fields->excerpt;
            if (!empty($customExcerpt)) {
                $excerpt = $customExcerpt;
            } else {
                $content = strip_tags($post->content);
                $content = preg_replace('/\s+/', ' ', trim($content));
                if (mb_strlen($content, 'UTF-8') > 80) {
                    $excerpt = mb_substr($content, 0, 80, 'UTF-8') . '...';
                } else {
                    $excerpt = $content;
                }
            }
        }
        
        $isSticky = ($post->fields->sticky == '1');
        $hasThumb = !empty($thumbnail);
        $postListStyle = !empty($this->options->postListStyle) ? $this->options->postListStyle : 'card';
        ?>
        
        <?php if ($postListStyle === 'classic'): ?>
        <article class="post <?php echo $hasThumb ? 'has-thumbnail' : ''; ?> <?php echo $isSticky ? 'post-sticky' : ''; ?>"
                 itemscope itemtype="http://schema.org/BlogPosting"
                 style="<?php echo $hasThumb ? 'background-image: url(' . htmlspecialchars($thumbnail) . ');' : ''; ?>">
            <a href="<?php echo $post->permalink(); ?>" class="post-link"></a>
            
            <?php if ($isSticky): ?>
            <div class="sticky-badge">
                <i class="fa fa-thumb-tack"></i>
                <span>置顶</span>
            </div>
            <?php endif; ?>
            
            <div class="post-overlay">
                <header class="post-header">
                    <h2 class="post-title" itemprop="name headline">
                        <a itemprop="url" href="<?php $post->permalink(); ?>"><?php $post->title(); ?></a>
                    </h2>
                </header>
                <div class="post-content post-excerpt" itemprop="articleBody">
                    <?php if ($post->hidden): ?>
                        <p class="excerpt-text"><i class="fa fa-lock"></i> 此文章已加密，请输入密码查看</p>
                    <?php else: ?>
                        <p class="excerpt-text"><?php echo htmlspecialchars($excerpt); ?></p>
                    <?php endif; ?>
                    <div class="post-footer">
                        <ul class="post-meta-inline">
                            <li itemprop="author" itemscope itemtype="http://schema.org/Person">
                                <a itemprop="name" href="<?php $post->author->permalink(); ?>" rel="author"><?php $post->author(); ?></a>
                            </li>
                            <li>
                                <time datetime="<?php $post->date('c'); ?>" itemprop="datePublished"><?php $post->date(); ?></time>
                            </li>
                            <li><?php $post->category(','); ?></li>
                            <li itemprop="interactionCount">
                                <a itemprop="discussionUrl" href="<?php $post->permalink() ?>#comments"><?php $post->commentsNum(_t('0'), _t('1'), _t('%d')); ?></a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </article>
        
        <?php else: ?>
        <article class="post <?php echo $hasThumb ? 'has-thumbnail' : 'no-thumbnail'; ?> <?php echo $isSticky ? 'post-sticky' : ''; ?>"
                 itemscope itemtype="http://schema.org/BlogPosting">
            <a href="<?php echo $post->permalink(); ?>" class="post-link"></a>
            
            <?php if ($isSticky): ?>
            <div class="sticky-badge">
                <i class="fa fa-thumb-tack"></i>
                <span>置顶</span>
            </div>
            <?php endif; ?>
            
            <?php if ($hasThumb): ?>
            <div class="post-thumbnail-side">
                <a href="<?php $post->permalink(); ?>" class="thumbnail-link-side" title="<?php $post->title(); ?>">
                    <img src="<?php echo htmlspecialchars($thumbnail); ?>" alt="<?php $post->title(); ?>" class="thumbnail-img-side" />
                </a>
            </div>
            <?php endif; ?>
            
            <div class="post-body">
                <header class="post-header">
                    <h2 class="post-title" itemprop="name headline">
                        <a itemprop="url" href="<?php $post->permalink(); ?>"><?php $post->title(); ?></a>
                    </h2>
                    <ul class="post-meta-top">
                        <li><i class="fa fa-user"></i> <a href="<?php $post->author->permalink(); ?>" rel="author"><?php $post->author(); ?></a></li>
                        <li><i class="fa fa-calendar"></i> <time datetime="<?php $post->date('c'); ?>" itemprop="datePublished"><?php $post->date(); ?></time></li>
                        <li><i class="fa fa-folder-o"></i> <?php $post->category(','); ?></li>
                    </ul>
                </header>
                <div class="post-excerpt" itemprop="articleBody">
                    <?php if ($post->hidden): ?>
                        <p class="excerpt-text"><i class="fa fa-lock"></i> 此文章已加密，请输入密码查看</p>
                    <?php else: ?>
                        <p class="excerpt-text"><?php echo htmlspecialchars($excerpt); ?></p>
                    <?php endif; ?>
                </div>
                <div class="post-footer">
                    <ul class="post-meta-inline">
                        <?php if (!$post->hidden): ?>
                        <li itemprop="interactionCount">
                            <a itemprop="discussionUrl" href="<?php $post->permalink() ?>#comments" title="评论"><i class="fa fa-comment-o"></i> <?php $post->commentsNum(_t('0'), _t('1'), _t('%d')); ?></a>
                        </li>
                        <?php endif; ?>
                    </ul>
                    <a href="<?php $post->permalink(); ?>" class="read-more">
                        <?php _e('阅读全文 <i class="fa fa-angle-double-right"></i>'); ?>
                    </a>
                </div>
            </div>
        </article>
        <?php endif; ?>
    <?php endforeach; ?>
    </div>
    <?php else: ?>
        <article class="post">
            <div class="post-content text-center" style="padding: 60px 20px;">
                <i class="fa fa-inbox" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></i>
                <h2 class="post-title"><?php _e('没有找到内容'); ?></h2>
                <p style="color: #999; margin-top: 15px;"><?php _e('抱歉,您访问的内容不存在或已被删除'); ?></p>
            </div>
        </article>
    <?php endif; ?>

    <nav class="page-navigator" id="ajax-page-nav">
        <?php $this->pageNav('<i class="fa fa-angle-left"></i> ' . _t('上一页'), _t('下一页') . ' <i class="fa fa-angle-right"></i>', 2); ?>
    </nav>
</div><!-- end #main-->

<!-- 右侧边栏 -->
<div class="right-sidebar" id="secondary" role="complementary">
    <!-- 最新文章 -->
    <?php if (!empty($this->options->sidebarBlock) && in_array('ShowRecentPosts', $this->options->sidebarBlock)): ?>
    <section class="widget">
        <h3 class="widget-title"><i class="fa fa-newspaper-o"></i><?php _e('最新文章'); ?></h3>
        <ul class="widget-list">
            <?php \Widget\Contents\Post\Recent::alloc('pageSize=5')
                ->parse('<li><a href="{permalink}"><i class="fa fa-angle-right"></i>{title}</a></li>'); ?>
        </ul>
    </section>
    <?php endif; ?>

    <!-- 最近回复 -->
    <?php if (!empty($this->options->sidebarBlock) && in_array('ShowRecentComments', $this->options->sidebarBlock)): ?>
    <section class="widget">
        <h3 class="widget-title"><i class="fa fa-comments-o"></i><?php _e('最近回复'); ?></h3>
        <ul class="widget-list">
            <?php \Widget\Comments\Recent::alloc('pageSize=5')->to($comments); ?>
            <?php while ($comments->next()): ?>
                <li>
                    <a href="<?php $comments->permalink(); ?>">
                        <i class="fa fa-comment-o"></i>
                        <span class="comment-author-name"><?php $comments->author(false); ?></span>:
                        <?php $comments->excerpt(20, '...'); ?>
                    </a>
                </li>
            <?php endwhile; ?>
        </ul>
    </section>
    <?php endif; ?>

    <!-- 归档 -->
    <?php if (!empty($this->options->sidebarBlock) && in_array('ShowArchive', $this->options->sidebarBlock)): ?>
    <section class="widget archive-widget">
        <h3 class="widget-title"><i class="fa fa-calendar"></i><?php _e('归档'); ?></h3>
        <ul class="widget-list archive-list">
            <?php \Widget\Contents\Post\Date::alloc('type=month&format=F Y')
                ->parse('<li><a href="{permalink}"><i class="fa fa-calendar-o"></i>{date}</a></li>'); ?>
        </ul>
    </section>
    <?php endif; ?>
    
    <!-- 文章排行榜 -->
    <?php if (!empty($this->options->rankingEnabled) && $this->options->rankingEnabled === 'on'): ?>
    <?php
    $rankingType = !empty($this->options->rankingType) ? $this->options->rankingType : 'views';
    $rankingLimit = !empty($this->options->rankingLimit) ? intval($this->options->rankingLimit) : 5;
    $rankingPosts = shufei_get_ranking_posts($rankingType, $rankingLimit);
    $rankingTitle = ($rankingType === 'likes') ? '点赞排行榜' : '阅读排行榜';
    $rankingIcon = ($rankingType === 'likes') ? 'fa-thumbs-up' : 'fa-fire';
    ?>
    <section class="widget ranking-widget">
        <h3 class="widget-title"><i class="fa <?php echo $rankingIcon; ?>"></i><?php _e($rankingTitle); ?></h3>
        <ul class="widget-list ranking-list">
            <?php foreach ($rankingPosts as $index => $post): ?>
            <li class="ranking-item">
                <a href="<?php echo \Typecho\Router::url('post', $post, $this->options->index); ?>">
                    <span class="ranking-num ranking-num-<?php echo $index + 1; ?>"><?php echo $index + 1; ?></span>
                    <span class="ranking-title"><?php echo htmlspecialchars($post['title']); ?></span>
                    <span class="ranking-count">
                        <?php if ($rankingType === 'likes'): ?>
                        <i class="fa fa-thumbs-up"></i> <?php echo $post['likes']; ?>
                        <?php else: ?>
                        <i class="fa fa-eye"></i> <?php echo $post['views']; ?>
                        <?php endif; ?>
                    </span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>
    
    <!-- 站点统计 -->
    <section class="widget">
        <h3 class="widget-title"><i class="fa fa-bar-chart"></i><?php _e('站点统计'); ?></h3>
        <ul class="widget-list">
            <li>
                <i class="fa fa-file-text"></i><?php _e('文章'); ?>: <?php $stat = \Widget\Stat::alloc(); echo $stat->publishedPostsNum; ?>
            </li>
            <li>
                <i class="fa fa-comments"></i><?php _e('评论'); ?>: <?php echo $stat->publishedCommentsNum; ?>
            </li>
            <li>
                <i class="fa fa-folder"></i><?php _e('分类'); ?>: <?php echo $stat->categoriesNum; ?>
            </li>
            <li>
                <i class="fa fa-tags"></i><?php _e('标签'); ?>: <?php echo $stat->tagsNum; ?>
            </li>
        </ul>
    </section>
</div><!-- end #secondary -->

<?php $this->need('footer.php'); ?>
