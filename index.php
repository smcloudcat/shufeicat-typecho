<?php
/**
 * ShuFeiCat主题 - 主页模板
 *
 * @package ShuFeiCat
 * @author YunCat
 * @version 1.1.0
 * @link https://lwcat.cn
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

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
        
        <!-- 侧边栏底部信息 -->
        <div class="sidebar-footer" style="padding: 15px; text-align: center; color: #999; font-size: 12px; border-top: 1px solid #eee; margin-top: 10px;">
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
    <div id="ajax-post-list">
    <?php while ($this->next()): ?>
        <?php
        // 获取缩略图
        $thumbnail = shufei_get_post_thumbnail($this);
        
        // 直接处理简介
        $excerpt = '';
        if (!$this->hidden) {
            // 1. 检查自定义字段
            $customExcerpt = $this->fields->excerpt;
            if (!empty($customExcerpt)) {
                $excerpt = $customExcerpt;
            } else {
                // 2. 获取纯文本内容
                $content = strip_tags($this->content);
                $content = preg_replace('/\s+/', ' ', trim($content));
                
                // 3. 截取前10字
                if (mb_strlen($content, 'UTF-8') > 10) {
                    $excerpt = mb_substr($content, 0, 10, 'UTF-8') . '...';
                } else {
                    $excerpt = $content;
                }
            }
        }
        ?>
        <article class="post <?php echo !empty($thumbnail) ? 'has-thumbnail' : ''; ?>"
                 itemscope itemtype="http://schema.org/BlogPosting"
                 style="<?php echo !empty($thumbnail) ? 'background-image: url(' . htmlspecialchars($thumbnail) . ');' : ''; ?>">
            <a href="<?php echo $this->permalink(); ?>" class="post-link"></a>
            
            <div class="post-overlay">
                <header class="post-header">
                    <h2 class="post-title" itemprop="name headline">
                        <a itemprop="url" href="<?php $this->permalink(); ?>"><?php $this->title(); ?></a>
                    </h2>
                </header>
                <div class="post-content post-excerpt" itemprop="articleBody">
                    <?php if ($this->hidden): ?>
                        <p class="excerpt-text"><i class="fa fa-lock"></i> 此文章已加密，请输入密码查看</p>
                    <?php else: ?>
                        <p class="excerpt-text"><?php echo htmlspecialchars($excerpt); ?></p>
                        <div class="post-footer">
                            <ul class="post-meta-inline">
                                <li itemprop="author" itemscope itemtype="http://schema.org/Person">
                                    <a itemprop="name" href="<?php $this->author->permalink(); ?>" rel="author" title="作者"><?php $this->author(); ?></a>
                                </li>
                                <li>
                                    <time datetime="<?php $this->date('c'); ?>" itemprop="datePublished" title="时间"><?php $this->date(); ?></time>
                                </li>
                                <li title="分类"><?php $this->category(','); ?></li>
                                <li itemprop="interactionCount">
                                    <a itemprop="discussionUrl" href="<?php $this->permalink() ?>#comments" title="评论"><?php $this->commentsNum(_t('0'), _t('1'), _t('%d')); ?></a>
                                </li>
                            </ul>
                            <a href="<?php $this->permalink(); ?>" class="read-more">
                                <?php _e('阅读全文 <i class="fa fa-angle-double-right"></i>'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    <?php endwhile; ?>
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
