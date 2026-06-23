<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>

<!-- 右侧边栏 -->
<div class="right-sidebar" id="secondary" role="complementary">
    <!-- 文章目录（仅文章页显示，由JS动态填充） -->
    <?php if ($this->is('post')): ?>
    <section class="widget toc-widget" id="toc-widget" style="display:none;">
        <h3 class="widget-title"><i class="fa fa-list"></i><?php _e('文章目录'); ?></h3>
        <nav class="toc-nav" id="toc-nav"></nav>
    </section>
    <?php endif; ?>

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
