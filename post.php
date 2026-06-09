<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $this->need('header.php'); ?>

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
                    <li>
                        <a href="https://typecho.org" target="_blank">
                            <i class="fa fa-external-link"></i>Typecho
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
            <p>Theme by ShuFeiCat</p>
        </div>
    </div>
</div>

<div class="col-mb-12 col-8" id="main" role="main">
    <?php
    // 获取文章缩略图
    $thumbnail = shufei_get_post_thumbnail($this);
    ?>
    <article class="post post-single <?php echo !empty($thumbnail) ? 'has-thumbnail' : ''; ?>" itemscope itemtype="http://schema.org/BlogPosting">
        <header class="post-header" <?php if (!empty($thumbnail)): ?>style="background-image: url(<?php echo htmlspecialchars($thumbnail); ?>);"<?php endif; ?>>
            <div class="post-header-overlay">
                <h1 class="post-title" itemprop="name headline">
                    <a itemprop="url" href="<?php $this->permalink() ?>"><?php $this->title() ?></a>
                </h1>
                <ul class="post-meta">
                    <li itemprop="author" itemscope itemtype="http://schema.org/Person">
                        <i class="fa fa-user"></i>
                        <?php _e('作者'); ?>: <a itemprop="name" href="<?php $this->author->permalink(); ?>" rel="author"><?php $this->author(); ?></a>
                    </li>
                    <li>
                        <i class="fa fa-clock-o"></i>
                        <time datetime="<?php $this->date('c'); ?>" itemprop="datePublished"><?php $this->date(); ?></time>
                    </li>
                    <li>
                        <i class="fa fa-folder-o"></i>
                        <?php _e('分类'); ?>: <?php $this->category(','); ?>
                    </li>
                    <li itemprop="interactionCount">
                        <i class="fa fa-comments-o"></i>
                        <a itemprop="discussionUrl" href="<?php $this->permalink() ?>#comments"><?php $this->commentsNum(_t('暂无评论'), _t('1 条评论'), _t('%d 条评论')); ?></a>
                    </li>
                    <?php if (!empty($this->options->statsEnabled) && $this->options->statsEnabled === 'on'): ?>
                    <li>
                        <i class="fa fa-eye"></i>
                        <?php _e('阅读'); ?>: <span class="post-views-count" data-cid="<?php echo $this->cid; ?>"><?php echo shufei_get_views($this->cid); ?></span>
                    </li>
                    <li>
                        <i class="fa fa-thumbs-o-up"></i>
                        <?php _e('点赞'); ?>: <span class="post-likes-count" data-cid="<?php echo $this->cid; ?>"><?php echo shufei_get_likes($this->cid); ?></span>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </header>
        
        <div class="post-content" itemprop="articleBody">
            <?php if ($this->hidden): ?>
                <div class="password-protection">
                    <div class="password-lock-icon">
                        <i class="fa fa-lock"></i>
                    </div>
                    <h2 class="password-title">文章已加密~</h2>
                    <p class="password-desc">这是一篇受密码保护的文章，请输入正确的密码来查看全文内容。</p>
                    <?php $this->content(); ?>
                    <p class="password-hint"><i class="fa fa-info-circle"></i> 请联系博主获取访问密码</p>
                </div>
            <?php else: ?>
                <?php echo shufei_render_post_content($this); ?>
            <?php endif; ?>
        </div>
        
        <?php if (count($this->tags) > 0): ?>
        <p itemprop="keywords" class="tags">
            <i class="fa fa-tags"></i>
            <?php _e('标签'); ?>: 
            <?php $this->tags(', ', true, 'none'); ?>
        </p>
        <?php endif; ?>
        
    </article>

    <?php if (!empty($this->options->statsEnabled) && $this->options->statsEnabled === 'on'): ?>
    <div class="post-like-box">
        <div class="like-box-inner">
            <div class="like-box-stats">
                <span class="like-stat-item">
                    <i class="fa fa-eye"></i>
                    <span class="stat-label">阅读</span>
                    <span class="stat-value post-views-count" data-cid="<?php echo $this->cid; ?>"><?php echo shufei_get_views($this->cid); ?></span>
                </span>
                <span class="like-stat-divider">|</span>
                <span class="like-stat-item">
                    <i class="fa fa-thumbs-up"></i>
                    <span class="stat-label">点赞</span>
                    <span class="stat-value post-likes-count" data-cid="<?php echo $this->cid; ?>"><?php echo shufei_get_likes($this->cid); ?></span>
                </span>
            </div>
            <button class="post-like-btn <?php echo shufei_has_liked($this->cid) ? 'liked' : ''; ?>" data-cid="<?php echo $this->cid; ?>" <?php echo shufei_has_liked($this->cid) ? 'disabled' : ''; ?>>
                <i class="fa fa-thumbs-up"></i>
                <span class="like-text"><?php echo shufei_has_liked($this->cid) ? '已点赞' : '点赞'; ?></span>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <ul class="post-near">
        <li>
            <i class="fa fa-angle-left"></i>
            <?php $this->thePrev('%s', _t('没有了')); ?>
        </li>
        <li>
            <?php $this->theNext('%s', _t('没有了')); ?>
            <i class="fa fa-angle-right"></i>
        </li>
    </ul>

    <?php $this->need('comments.php'); ?>
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
