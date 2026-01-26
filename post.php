<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $this->need('header.php'); ?>

<!-- 左侧侧边栏 - 分类和其他 -->
<div class="left-sidebar" id="left-sidebar">
    <div class="sidebar-inner">
        <!-- 分类目录 -->
        <section class="widget category-widget">
            <h3 class="widget-title"><i class="fa fa-navicon"></i><?php _e('分类目录'); ?></h3>
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
        </section>

        <!-- 其它 -->
        <?php if (!empty($this->options->sidebarBlock) && in_array('ShowOther', $this->options->sidebarBlock)): ?>
        <section class="widget other-widget">
            <h3 class="widget-title"><i class="fa fa-cogs"></i><?php _e('其它'); ?></h3>
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
        </section>
        <?php endif; ?>

        <!-- 友链 -->
        <?php if (!empty($this->options->sidebarBlock) && in_array('ShowLinks', $this->options->sidebarBlock) && !empty($this->options->links)): ?>
        <section class="widget">
            <h3 class="widget-title"><i class="fa fa-link"></i><?php _e('友链'); ?></h3>
            <select class="links-select" onchange="if(this.value){window.open(this.value,'_blank');}">
                <option value=""><?php _e('选择友链...'); ?></option>
                <?php
                $links = explode("\n", $this->options->links);
                foreach ($links as $link) {
                    $link = trim($link);
                    if (empty($link)) continue;
                    $parts = explode(',', $link, 2);
                    if (count($parts) == 2) {
                        $name = trim($parts[0]);
                        $url = trim($parts[1]);
                        echo '<option value="' . htmlspecialchars($url) . '">' . htmlspecialchars($name) . '</option>';
                    }
                }
                ?>
            </select>
        </section>
        <?php endif; ?>
    </div>
</div>

<div class="col-mb-12 col-8" id="main" role="main">
    <?php
    // 获取文章缩略图
    $thumbnail = shufei_get_post_thumbnail($this);
    ?>
    <article class="post post-single <?php echo !empty($thumbnail) ? 'has-thumbnail' : ''; ?>" itemscope itemtype="http://schema.org/BlogPosting"
             style="<?php echo !empty($thumbnail) ? 'background-image: url(' . htmlspecialchars($thumbnail) . ');' : ''; ?>">
        <header class="post-header">
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
                    <li>
                        <i class="fa fa-eye"></i>
                        <?php _e('阅读'); ?>: <?php $this->views(); ?>
                    </li>
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
                <?php $this->content(); ?>
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
    <!-- 页面导航 -->
    <section class="widget page-nav-widget">
        <h3 class="widget-title"><i class="fa fa-sitemap"></i><?php _e('页面导航'); ?></h3>
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
    </section>
    
    <!-- 最新文章 -->
    <?php if (!empty($this->options->sidebarBlock) && in_array('ShowRecentPosts', $this->options->sidebarBlock)): ?>
    <section class="widget">
        <h3 class="widget-title"><i class="fa fa-newspaper-o"></i><?php _e('最新文章'); ?></h3>
        <ul class="widget-list">
            <?php \Widget\Contents\Post\Recent::alloc()
                ->parse('<li><a href="{permalink}"><i class="fa fa-angle-right"></i>{title}</a></li>'); ?>
        </ul>
    </section>
    <?php endif; ?>

    <!-- 最近回复 -->
    <?php if (!empty($this->options->sidebarBlock) && in_array('ShowRecentComments', $this->options->sidebarBlock)): ?>
    <section class="widget">
        <h3 class="widget-title"><i class="fa fa-comments-o"></i><?php _e('最近回复'); ?></h3>
        <ul class="widget-list">
            <?php \Widget\Comments\Recent::alloc()->to($comments); ?>
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
    <section class="widget">
        <h3 class="widget-title"><i class="fa fa-calendar"></i><?php _e('归档'); ?></h3>
        <ul class="widget-list">
            <?php \Widget\Contents\Post\Date::alloc('type=month&format=F Y')
                ->parse('<li><a href="{permalink}"><i class="fa fa-calendar-o"></i>{date}</a></li>'); ?>
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
