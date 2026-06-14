<?php
/**
 * Ajax加载处理文件
 * 用于处理文章列表的ajax加载请求和AI API检测
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// AI API检测接口
if ($action === 'ai_api_check') {
    header('Content-Type: application/json');
    require_once dirname(__FILE__) . '/core/ai-moderation.php';

    $moderation = new AiModeration();
    $result = $moderation->checkApiHealth();

    echo json_encode($result);
    exit;
}

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) {
    $page = 1;
}


$category = isset($_GET['category']) ? $_GET['category'] : null;

$db = \Typecho\Db::get();

$query = $db->select()->from('table.contents')
    ->where('table.contents.status = ?', 'publish')
    ->where('table.contents.created < ?', \Typecho\Date::time())
    ->where('table.contents.type = ?', 'post');

if ($category) {
    $query = $db->select()->from('table.contents')
        ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
        ->where('table.contents.status = ?', 'publish')
        ->where('table.contents.created < ?', \Typecho\Date::time())
        ->where('table.contents.type = ?', 'post')
        ->where('table.relationships.mid = ?', $category);
}

$countQuery = $db->select(['COUNT(table.contents.cid)' => 'count'])->from('table.contents');
$countQuery->where('table.contents.status = ?', 'publish');
$countQuery->where('table.contents.created < ?', \Typecho\Date::time());
$countQuery->where('table.contents.type = ?', 'post');

if ($category) {
    $countQuery->join('table.relationships', 'table.contents.cid = table.relationships.cid');
    $countQuery->where('table.relationships.mid = ?', $category);
}

$total = $db->fetchObject($countQuery)->count;

$pageSize = \Widget\Options::alloc()->pageSize;

$totalPages = ceil($total / $pageSize);

if ($page > $totalPages) {
    echo json_encode([
        'html' => '',
        'hasMore' => false,
        'page' => $page,
        'totalPages' => $totalPages
    ]);
    exit;
}

$archive = \Widget\Archive::alloc([
    'page' => $page,
    'pageSize' => $pageSize,
    'type' => 'index'
]);

if ($category) {
    $archive = \Widget\Archive::alloc([
        'page' => $page,
        'pageSize' => $pageSize,
        'type' => 'category',
        'mid' => $category
    ]);
}

// 获取所有置顶文章的CID
$stickyCids = shufei_get_sticky_cids();

ob_start();

if ($archive->have()) {
    // 分离置顶文章和普通文章
    $stickyPosts = array();
    $normalPosts = array();

    while ($archive->next()) {
        if (in_array($archive->cid, $stickyCids)) {
            $stickyPosts[] = clone $archive;
        } else {
            $normalPosts[] = clone $archive;
        }
    }

    // 在首页第一页时，获取不在当前页的置顶文章
    $isFirstPage = ($page <= 1) && empty($category);

    if ($isFirstPage && !empty($stickyCids)) {
        // 获取当前页已有的置顶文章CID
        $currentStickyCids = array();
        foreach ($stickyPosts as $sp) {
            $currentStickyCids[] = $sp->cid;
        }

        // 获取不在当前页的置顶文章CID
        $missingCids = array_diff($stickyCids, $currentStickyCids);

        if (!empty($missingCids)) {
            $query = $db->select('table.contents.*')->from('table.contents')
                ->where('table.contents.status = ?', 'publish')
                ->where('table.contents.created < ?', \Typecho\Date::time())
                ->where('table.contents.type = ?', 'post')
                ->where('table.contents.cid IN ?', array_values($missingCids))
                ->order('table.contents.created', \Typecho\Db::SORT_DESC);

            $stickyFromWidget = \Widget\Contents\From::allocWithAlias('sticky_ajax_extra', ['query' => $query]);
            while ($stickyFromWidget->next()) {
                $stickyPosts[] = clone $stickyFromWidget;
            }
        }

        // 合并文章列表：置顶文章在前
        $allPosts = array_merge($stickyPosts, $normalPosts);
    } elseif (!empty($stickyCids) && empty($category)) {
        // 非首页第一页，跳过置顶文章（已在第一页显示）
        $allPosts = $normalPosts;
    } else {
        // 分类页等，保持原有逻辑
        $allPosts = array_merge($stickyPosts, $normalPosts);
    }

    $options = \Typecho\Widget::widget('Widget_Options');
    $postListStyle = !empty($options->postListStyle) ? $options->postListStyle : 'classic';

    foreach ($allPosts as $post) {
        $thumbnail = shufei_get_post_thumbnail($post);
        $excerpt = '';
        if (!$post->hidden) {
            $customExcerpt = $post->fields->excerpt;
            if (!empty($customExcerpt)) {
                $excerpt = $customExcerpt;
            } else {
                $content = strip_tags($post->content ?? '');
                // 去掉数学公式占位符和标签
                $content = preg_replace('/<!--MATH\d+-->/', '', $content);
                $content = preg_replace('/<span class="math-tex"[^>]*>.*?<\/span>/', '', $content);
                $content = preg_replace('/\s+/', ' ', trim($content));
                if (mb_strlen($content, 'UTF-8') > 80) {
                    $excerpt = mb_substr($content, 0, 80, 'UTF-8') . '...';
                } else {
                    $excerpt = $content;
                }
            }
        }
        $hasThumb = !empty($thumbnail);
        $isSticky = in_array($post->cid, $stickyCids);

        include dirname(__FILE__) . '/post-card.php';
    }
} else {
    ?>
    <article class="post">
        <div class="post-content text-center" style="padding: 60px 20px;">
            <i class="fa fa-inbox" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></i>
            <h2 class="post-title"><?php _e('没有找到内容'); ?></h2>
            <p style="color: #999; margin-top: 15px;"><?php _e('抱歉,您访问的内容不存在或已被删除'); ?></p>
        </div>
    </article>
    <?php
}

$html = ob_get_clean();

// 获取分页导航
ob_start();
$archive->pageNav('<i class="fa fa-angle-left"></i> ' . _t('上一页'), _t('下一页') . ' <i class="fa fa-angle-right"></i>');
$pageNav = ob_get_clean();

header('Content-Type: application/json');

echo json_encode([
    'html' => $html,
    'pageNav' => $pageNav,
    'hasMore' => $page < $totalPages,
    'page' => $page,
    'totalPages' => $totalPages
]);
