<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>

<!-- 右侧边栏 -->
<?php
// ===== 将各板块 HTML 收集到数组 =====
$_rightSections = array();

// 天气卡片
if (!empty($this->options->weatherEnabled) && $this->options->weatherEnabled === 'on'):
ob_start();
?>
<section class="widget weather-widget" id="weather-widget">
    <div class="weather-card" id="weather-card">
        <div class="weather-loading">
            <i class="fa fa-spinner fa-pulse"></i>
            <span>正在获取天气信息…</span>
        </div>
    </div>
</section>
<?php
$_rightSections['weather'] = ob_get_clean();
endif;

// 文章目录（仅文章页显示，由JS动态填充）
if ($this->is('post')):
ob_start();
?>
<section class="widget toc-widget" id="toc-widget" style="display:none;">
    <h3 class="widget-title"><i class="fa fa-list"></i><?php _e('文章目录'); ?></h3>
    <nav class="toc-nav" id="toc-nav"></nav>
</section>
<?php
$_rightSections['toc'] = ob_get_clean();
endif;

// 最新文章
if (!empty($this->options->sidebarBlock) && in_array('ShowRecentPosts', $this->options->sidebarBlock)):
ob_start();
?>
<section class="widget">
    <h3 class="widget-title"><i class="fa fa-newspaper-o"></i><?php _e('最新文章'); ?></h3>
    <ul class="widget-list">
        <?php \Widget\Contents\Post\Recent::alloc('pageSize=5')
            ->parse('<li><a href="{permalink}"><i class="fa fa-angle-right"></i>{title}</a></li>'); ?>
    </ul>
</section>
<?php
$_rightSections['recent'] = ob_get_clean();
endif;

// 最近回复
if (!empty($this->options->sidebarBlock) && in_array('ShowRecentComments', $this->options->sidebarBlock)):
ob_start();
?>
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
<?php
$_rightSections['comments'] = ob_get_clean();
endif;

// 归档
if (!empty($this->options->sidebarBlock) && in_array('ShowArchive', $this->options->sidebarBlock)):
ob_start();
?>
<section class="widget archive-widget">
    <h3 class="widget-title"><i class="fa fa-calendar"></i><?php _e('归档'); ?></h3>
    <ul class="widget-list archive-list">
        <?php \Widget\Contents\Post\Date::alloc('type=month&format=F Y')
            ->parse('<li><a href="{permalink}"><i class="fa fa-calendar-o"></i>{date}</a></li>'); ?>
    </ul>
</section>
<?php
$_rightSections['archive'] = ob_get_clean();
endif;

// 文章排行榜
if (!empty($this->options->rankingEnabled) && $this->options->rankingEnabled === 'on'):
ob_start();
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
<?php
$_rightSections['ranking'] = ob_get_clean();
endif;

// 站点统计
ob_start();
?>
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
<?php
$_rightSections['stats'] = ob_get_clean();

// ===== 解析显示顺序并输出 =====
$_defaultRightOrder = array('weather', 'toc', 'recent', 'comments', 'archive', 'ranking', 'stats');
$_orderRaw = isset($this->options->sidebarOrderRight) ? trim($this->options->sidebarOrderRight) : '';
$_orderList = !empty($_orderRaw) ? array_map('trim', explode(',', $_orderRaw)) : $_defaultRightOrder;

$_outputOrder = array();
$_seen = array();
foreach ($_orderList as $key) {
    $key = strtolower($key);
    if (isset($_rightSections[$key]) && !isset($_seen[$key])) {
        $_outputOrder[] = $key;
        $_seen[$key] = true;
    }
}
foreach ($_defaultRightOrder as $key) {
    if (isset($_rightSections[$key]) && !isset($_seen[$key])) {
        $_outputOrder[] = $key;
        $_seen[$key] = true;
    }
}
?>
<div class="right-sidebar" id="secondary" role="complementary">
    <?php foreach ($_outputOrder as $_key): ?>
    <?php echo $_rightSections[$_key]; ?>
    <?php endforeach; ?>
</div><!-- end #secondary -->
