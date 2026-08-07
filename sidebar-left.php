<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>

<!-- 左侧侧边栏 - 分类和其他 -->
<?php
// ===== 预处理：计算各板块所需数据 =====

// 页面导航排除 CID 计算
$_excludePageCids = array();
$_db = \Typecho\Db::get();

$_gbEnabled = !empty($this->options->guestbookEnabled) && $this->options->guestbookEnabled === 'on';
if ($_gbEnabled) {
    $_gbPageId = isset($this->options->guestbookPageId) ? trim($this->options->guestbookPageId) : '';
    if (!empty($_gbPageId)) {
        $_excludePageCids[] = intval($_gbPageId);
    } else {
        $_gbRow = $_db->fetchRow($_db->select('cid')->from('table.contents')
            ->where('template = ?', 'guestbook.php')
            ->where('status = ?', 'publish')
            ->limit(1));
        if (!empty($_gbRow)) {
            $_excludePageCids[] = intval($_gbRow['cid']);
        }
    }
}

$_ghUsername = !empty($this->options->githubUsername) ? trim($this->options->githubUsername) : '';
if (!empty($_ghUsername)) {
    $_ghRow = $_db->fetchRow($_db->select('cid')->from('table.contents')
        ->where('template = ?', 'github.php')
        ->where('status = ?', 'publish')
        ->limit(1));
    if (!empty($_ghRow)) {
        $_excludePageCids[] = intval($_ghRow['cid']);
    }
}

$_linksPageEnabled = !empty($this->options->linksPageEnabled) && $this->options->linksPageEnabled === 'on';
if ($_linksPageEnabled) {
    $_linksRow = $_db->fetchRow($_db->select('cid')->from('table.contents')
        ->where('template = ?', 'links.php')
        ->where('status = ?', 'publish')
        ->limit(1));
    if (!empty($_linksRow)) {
        $_excludePageCids[] = intval($_linksRow['cid']);
    }
}

// 留言板 URL
$_guestbookUrl = $_gbEnabled ? shufei_get_guestbook_url() : '';

// GitHub 页面 URL
$_githubPageUrl = '';
if (!empty($_ghUsername)) {
    $_ghPageRow = $_db->fetchRow($_db->select('cid', 'slug')->from('table.contents')
        ->where('template = ?', 'github.php')
        ->where('status = ?', 'publish')
        ->limit(1));
    if (!empty($_ghPageRow)) {
        $_githubPageUrl = $this->options->index . '/' . $_ghPageRow['slug'] . '.html';
    }
}

// 友链页面 URL
$_linksPageUrl = $_linksPageEnabled ? shufei_get_links_url() : '';

// 自定义导航
$_customNavItems = shufei_get_custom_nav_items();

// 联系方式
$_opts = $this->options;
$_authorEmail = isset($_opts->authorEmail) ? trim($_opts->authorEmail) : '';
$_authorGithub = isset($_opts->authorGithub) ? trim($_opts->authorGithub) : '';
$_authorQQ = isset($_opts->authorQQ) ? trim($_opts->authorQQ) : '';
$_hasContacts = ($_authorEmail !== '' || $_authorGithub !== '' || $_authorQQ !== '');

// ===== 将各板块 HTML 收集到数组 =====
$_sidebarSections = array();

// 站长信息卡片
ob_start();
?>
<div class="author-card">
    <div class="author-avatar-wrap">
        <img class="author-avatar" src="<?php echo htmlspecialchars(!empty($this->options->authorAvatar) ? $this->options->authorAvatar : 'https://q1.qlogo.cn/g?b=qq&nk=3522934828&s=100'); ?>" alt="<?php echo htmlspecialchars(!empty($this->options->authorName) ? $this->options->authorName : '云猫'); ?>">
    </div>
    <div class="author-name"><?php echo htmlspecialchars(!empty($this->options->authorName) ? $this->options->authorName : '云猫'); ?></div>
    <?php $_authorSig = !empty($this->options->authorSignature) ? trim($this->options->authorSignature) : ''; ?>
    <?php if ($_authorSig !== ''): ?>
    <div class="author-signature"><?php echo htmlspecialchars($_authorSig); ?></div>
    <?php endif; ?>
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
<?php
$_sidebarSections['author'] = ob_get_clean();

// 分类目录
ob_start();
?>
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
                        <i class="fa <?php echo shufei_get_category_icon($categories->slug); ?>"></i>
                        <span><?php $categories->name(); ?></span>
                    </a>
                </li>
            <?php endwhile; ?>
        </ul>
    </div>
</section>
<?php
$_sidebarSections['category'] = ob_get_clean();

// 页面导航
ob_start();
?>
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
                <?php if (in_array(intval($pages->cid), $_excludePageCids)) continue; ?>
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
<?php
$_sidebarSections['pages'] = ob_get_clean();

// 留言板入口
if ($_gbEnabled && !empty($_guestbookUrl)):
ob_start();
?>
<section class="widget guestbook-widget">
    <a href="<?php echo htmlspecialchars($_guestbookUrl); ?>" class="widget-title sidebar-direct-link">
        <i class="fa fa-envelope-o"></i><?php _e('留言板'); ?>
    </a>
</section>
<?php
$_sidebarSections['guestbook'] = ob_get_clean();
endif;

// GitHub 项目入口
if (!empty($_ghUsername) && !empty($_githubPageUrl)):
ob_start();
?>
<section class="widget github-widget">
    <a href="<?php echo htmlspecialchars($_githubPageUrl); ?>" class="widget-title sidebar-direct-link">
        <i class="fa fa-github"></i><?php _e('GitHub'); ?>
    </a>
</section>
<?php
$_sidebarSections['github'] = ob_get_clean();
endif;

// 友链页面入口
if ($_linksPageEnabled && !empty($_linksPageUrl)):
ob_start();
?>
<section class="widget linkspage-widget">
    <a href="<?php echo htmlspecialchars($_linksPageUrl); ?>" class="widget-title sidebar-direct-link">
        <i class="fa fa-link"></i><?php _e('友链'); ?>
    </a>
</section>
<?php
$_sidebarSections['linkspage'] = ob_get_clean();
endif;

// 自定义导航
if (!empty($_customNavItems)):
ob_start();
?>
<section class="widget custom-nav-widget collapsible-widget">
    <h3 class="widget-title collapsible-toggle"><i class="fa fa-compass"></i><?php _e('快捷导航'); ?><i class="fa fa-chevron-down collapsible-arrow"></i></h3>
    <div class="collapsible-content">
        <ul class="widget-list custom-nav-list">
            <?php foreach ($_customNavItems as $item): ?>
            <li>
                <a href="<?php echo htmlspecialchars($item['url']); ?>" target="_blank" rel="noopener noreferrer">
                    <i class="fa <?php echo htmlspecialchars($item['icon']); ?>"></i>
                    <span><?php echo htmlspecialchars($item['name']); ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php
$_sidebarSections['customnav'] = ob_get_clean();
endif;

// 友链
$_linksDropdownOn = !isset($this->options->linksDropdownEnabled) || $this->options->linksDropdownEnabled === 'on';
if (!empty($this->options->sidebarBlock) && in_array('ShowLinks', $this->options->sidebarBlock) && !empty($this->options->links) && $_linksDropdownOn):
ob_start();
$_inlineLinks = shufei_parse_links();
?>
<section class="widget links-widget collapsible-widget">
    <h3 class="widget-title collapsible-toggle"><i class="fa fa-link"></i><?php _e('友链'); ?><i class="fa fa-chevron-down collapsible-arrow"></i></h3>
    <div class="collapsible-content">
        <ul class="links-nav-list">
            <?php foreach ($_inlineLinks as $_link): ?>
                <li class="links-nav-item"><a href="<?php echo htmlspecialchars($_link['url']); ?>" target="_blank" rel="noopener noreferrer"><i class="fa fa-external-link"></i><span><?php echo htmlspecialchars($_link['name']); ?></span></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php
$_sidebarSections['links'] = ob_get_clean();
endif;

// 其它
if (!empty($this->options->sidebarBlock) && in_array('ShowOther', $this->options->sidebarBlock)):
ob_start();
?>
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
<?php
$_sidebarSections['other'] = ob_get_clean();
endif;

// 联系方式
if ($_hasContacts):
ob_start();
?>
<div class="sidebar-contacts">
    <?php if ($_authorEmail !== ''): ?>
    <a href="mailto:<?php echo htmlspecialchars($_authorEmail); ?>" title="<?php echo htmlspecialchars($_authorEmail); ?>" target="_blank" rel="noopener noreferrer">
        <i class="fa fa-envelope-o"></i>
    </a>
    <?php endif; ?>
    <?php if ($_authorGithub !== ''): ?>
    <a href="<?php echo htmlspecialchars($_authorGithub); ?>" title="GitHub" target="_blank" rel="noopener noreferrer">
        <i class="fa fa-github"></i>
    </a>
    <?php endif; ?>
    <?php if ($_authorQQ !== ''): ?>
    <a href="http://wpa.qq.com/msgrd?v=3&uin=<?php echo htmlspecialchars($_authorQQ); ?>&site=qq&menu=yes" title="QQ: <?php echo htmlspecialchars($_authorQQ); ?>" target="_blank" rel="noopener noreferrer">
        <i class="fa fa-qq"></i>
    </a>
    <?php endif; ?>
</div>
<?php
$_sidebarSections['contacts'] = ob_get_clean();
endif;

// 底部信息
ob_start();
?>
<div class="sidebar-footer">
    <p>&copy; <?php echo date('Y'); ?> <?php $this->options->title(); ?></p>
    <p>Theme by ShuFeiCat</p>
</div>
<?php
$_sidebarSections['footer'] = ob_get_clean();

// ===== 解析显示顺序并输出 =====
$_defaultLeftOrder = array('author', 'category', 'pages', 'guestbook', 'github', 'linkspage', 'customnav', 'links', 'other', 'contacts', 'footer');
$_orderRaw = isset($this->options->sidebarOrderLeft) ? trim($this->options->sidebarOrderLeft) : '';
$_orderList = !empty($_orderRaw) ? array_map('trim', explode(',', $_orderRaw)) : $_defaultLeftOrder;

// 合并：用户顺序 + 未列出的板块按默认顺序追加
$_outputOrder = array();
$_seen = array();
foreach ($_orderList as $key) {
    $key = strtolower($key);
    if (isset($_sidebarSections[$key]) && !isset($_seen[$key])) {
        $_outputOrder[] = $key;
        $_seen[$key] = true;
    }
}
foreach ($_defaultLeftOrder as $key) {
    if (isset($_sidebarSections[$key]) && !isset($_seen[$key])) {
        $_outputOrder[] = $key;
        $_seen[$key] = true;
    }
}
?>
<div class="left-sidebar" id="left-sidebar">
    <div class="sidebar-inner">
        <?php
        // 将侧边栏板块分成三个盒子：作者 / 菜单按钮 / 其它+底部
        $_panelMap = array(
            'author'    => 'author',
            'category'  => 'menu',
            'pages'     => 'menu',
            'guestbook' => 'menu',
            'github'    => 'menu',
            'linkspage' => 'menu',
            'customnav' => 'menu',
            'links'     => 'menu',
            'other'     => 'menu',
            'contacts'  => 'other',
            'footer'    => 'other'
        );
        $_panels = array('author' => array(), 'menu' => array(), 'other' => array());
        foreach ($_outputOrder as $_key) {
            $_group = isset($_panelMap[$_key]) ? $_panelMap[$_key] : 'other';
            $_panels[$_group][] = $_key;
        }
        ?>
        <?php if (!empty($_panels['author'])): ?>
        <div class="sidebar-panel sidebar-panel-author">
            <?php foreach ($_panels['author'] as $_key): ?>
            <?php echo $_sidebarSections[$_key]; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($_panels['menu'])): ?>
        <div class="sidebar-panel sidebar-panel-menu">
            <?php foreach ($_panels['menu'] as $_key): ?>
            <?php echo $_sidebarSections[$_key]; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($_panels['other'])): ?>
        <div class="sidebar-panel sidebar-panel-other">
            <?php foreach ($_panels['other'] as $_key): ?>
            <?php echo $_sidebarSections[$_key]; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <script>
    (function() {
        try {
            var sidebar = document.getElementById('left-sidebar');
            if (!sidebar) return;
            var toggles = sidebar.querySelectorAll('.collapsible-toggle');
            for (var i = 0; i < toggles.length; i++) {
                var widget = toggles[i].closest('.collapsible-widget');
                if (!widget) continue;
                var classes = widget.className.split(' ');
                var specificClass = '';
                for (var j = 0; j < classes.length; j++) {
                    if (classes[j] !== 'widget' && classes[j] !== 'collapsible-widget' && classes[j] !== 'collapsed') {
                        specificClass = classes[j];
                        break;
                    }
                }
                var key = 'sidebar_state_' + (specificClass || 'unknown');
                var state = localStorage.getItem(key);
                if (state === 'expanded') {
                    widget.classList.remove('collapsed');
                } else {
                    widget.classList.add('collapsed');
                }
            }
        } catch (e) {}
    })();
    </script>
</div>
