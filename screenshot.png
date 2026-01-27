<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<div class="col-mb-12 col-offset-1 col-3 kit-hidden-tb" id="secondary" role="complementary">
    <!-- 分类侧边栏 -->
    <section class="widget category-widget">
        <h3 class="widget-title"><i class="fa fa-navicon"></i><?php _e('分类目录'); ?></h3>
        <ul class="category-nav-list">
            <?php if (!empty($this->options->sidebarBlock) && in_array('ShowCategory', $this->options->sidebarBlock)): ?>
                <?php \Widget\Metas\Category\Rows::alloc()->listCategories('wrapClass=widget-list&itemClass=category-nav-item'); ?>
            <?php else: ?>
                <!-- 默认显示所有分类 -->
                <?php
                $categories = \Widget\Metas\Category\Rows::alloc();
                while ($categories->next()):
                ?>
                    <li class="category-nav-item">
                        <a href="<?php $categories->permalink(); ?>">
                            <i class="fa fa-folder-open-o"></i>
                            <span><?php $categories->name(); ?></span>
                            <span class="category-count"><?php $categories->count(); ?></span>
                        </a>
                    </li>
                <?php endwhile; ?>
            <?php endif; ?>
        </ul>
    </section>

    <?php if (!empty($this->options->sidebarBlock) && in_array('ShowRecentPosts', $this->options->sidebarBlock)): ?>
        <section class="widget">
            <h3 class="widget-title"><i class="fa fa-newspaper-o"></i><?php _e('最新文章'); ?></h3>
            <ul class="widget-list">
                <?php \Widget\Contents\Post\Recent::alloc()
                    ->parse('<li><a href="{permalink}"><i class="fa fa-angle-right"></i>{title}</a></li>'); ?>
            </ul>
        </section>
    <?php endif; ?>

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

    <?php if (!empty($this->options->sidebarBlock) && in_array('ShowArchive', $this->options->sidebarBlock)): ?>
        <section class="widget">
            <h3 class="widget-title"><i class="fa fa-calendar"></i><?php _e('归档'); ?></h3>
            <ul class="widget-list">
                <?php \Widget\Contents\Post\Date::alloc('type=month&format=F Y')
                    ->parse('<li><a href="{permalink}"><i class="fa fa-calendar-o"></i>{date}</a></li>'); ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if (!empty($this->options->sidebarBlock) && in_array('ShowOther', $this->options->sidebarBlock)): ?>
        <section class="widget">
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
</div><!-- end #sidebar -->
