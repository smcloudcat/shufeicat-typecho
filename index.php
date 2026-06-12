<?php
/**
 * ShuFeiCat主题 - 主页模板
 *
 * @package ShuFeiCat
 * @author YunCat
 * @version 1.4.0-rc.4
 * @link https://lwcat.cn
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<?php $this->need('sidebar-left.php'); ?>

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
    <div id="ajax-post-list" class="post-list-<?php echo !empty($this->options->postListStyle) ? $this->options->postListStyle : 'classic'; ?>">
    <?php
    // 获取所有置顶文章的CID
    $stickyCids = shufei_get_sticky_cids();

    // 分离置顶文章和普通文章
    $stickyPosts = array();
    $normalPosts = array();

    while ($this->next()) {
        if (in_array($this->cid, $stickyCids)) {
            $stickyPosts[] = clone $this;
        } else {
            $normalPosts[] = clone $this;
        }
    }

    // 在首页第一页时，获取不在当前页的置顶文章
    $isFirstPage = $this->is('index') && intval($this->_currentPage) <= 1;

    if ($isFirstPage && !empty($stickyCids)) {
        // 获取当前页已有的置顶文章CID
        $currentStickyCids = array();
        foreach ($stickyPosts as $sp) {
            $currentStickyCids[] = $sp->cid;
        }

        // 获取不在当前页的置顶文章CID
        $missingCids = array_diff($stickyCids, $currentStickyCids);

        if (!empty($missingCids)) {
            $db = \Typecho\Db::get();
            $query = $db->select('table.contents.*')->from('table.contents')
                ->where('table.contents.status = ?', 'publish')
                ->where('table.contents.created < ?', \Typecho\Date::time())
                ->where('table.contents.type = ?', 'post')
                ->where('table.contents.cid IN ?', array_values($missingCids))
                ->order('table.contents.created', \Typecho\Db::SORT_DESC);

            $stickyFromWidget = \Widget\Contents\From::allocWithAlias('sticky_extra', ['query' => $query]);
            while ($stickyFromWidget->next()) {
                $stickyPosts[] = clone $stickyFromWidget;
            }
        }

        // 合并文章列表：置顶文章在前
        $allPosts = array_merge($stickyPosts, $normalPosts);
    } elseif (!empty($stickyCids) && $this->is('index')) {
        // 非首页第一页，跳过置顶文章（已在第一页显示）
        $allPosts = $normalPosts;
    } else {
        // 非首页（分类、标签等），保持原有逻辑
        $allPosts = array_merge($stickyPosts, $normalPosts);
    }

    $postListStyle = !empty($this->options->postListStyle) ? $this->options->postListStyle : 'classic';
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
                $content = strip_tags($post->content ?? '');
                $content = preg_replace('/\s+/', ' ', trim($content));
                if (mb_strlen($content, 'UTF-8') > 80) {
                    $excerpt = mb_substr($content, 0, 80, 'UTF-8') . '...';
                } else {
                    $excerpt = $content;
                }
            }
        }
        
        $isSticky = in_array($post->cid, $stickyCids);
        $hasThumb = !empty($thumbnail);
        
        include 'post-card.php';
        ?>
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

<?php $this->need('sidebar-right.php'); ?>

<?php $this->need('footer.php'); ?>
