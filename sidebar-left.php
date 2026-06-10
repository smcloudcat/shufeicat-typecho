<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>

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
                            echo '<li class="links-nav-item"><a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener noreferrer"><i class="fa fa-external-link"></i><span>' . htmlspecialchars($name) . '</span></a></li>';
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
                        <a href="https://typecho.org" target="_blank" rel="noopener noreferrer">
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
            <a href="mailto:<?php echo htmlspecialchars($authorEmail); ?>" title="<?php echo htmlspecialchars($authorEmail); ?>" target="_blank" rel="noopener noreferrer">
                <i class="fa fa-envelope-o"></i>
            </a>
            <?php endif; ?>
            <?php if ($authorGithub !== ''): ?>
            <a href="<?php echo htmlspecialchars($authorGithub); ?>" title="GitHub" target="_blank" rel="noopener noreferrer">
                <i class="fa fa-github"></i>
            </a>
            <?php endif; ?>
            <?php if ($authorQQ !== ''): ?>
            <a href="http://wpa.qq.com/msgrd?v=3&uin=<?php echo htmlspecialchars($authorQQ); ?>&site=qq&menu=yes" title="QQ: <?php echo htmlspecialchars($authorQQ); ?>" target="_blank" rel="noopener noreferrer">
                <i class="fa fa-qq"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- 侧边栏底部信息 -->
        <div class="sidebar-footer">
            <p>&copy; <?php echo date('Y'); ?> <?php $this->options->title(); ?></p>
            <p>Theme by ShuFeiCat</p>
        </div>
    </div>
</div>
