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

ob_start();

if ($archive->have()) {
    while ($archive->next()) {
        ?>
        <article class="post" itemscope itemtype="http://schema.org/BlogPosting">
            <header class="post-header">
                <?php 
                ?>
                <h2 class="post-title" itemprop="name headline">
                    <a itemprop="url" href="<?php $archive->permalink(); ?>"><?php $archive->title(); ?></a>
                </h2>
                <ul class="post-meta">
                    <li itemprop="author" itemscope itemtype="http://schema.org/Person">
                        <?php _e('作者'); ?>: <a itemprop="name" href="<?php $archive->author->permalink(); ?>" rel="author"><?php $archive->author(); ?></a>
                    </li>
                    <li><?php _e('时间'); ?>:
                        <time datetime="<?php $archive->date('c'); ?>" itemprop="datePublished"><?php $archive->date(); ?></time>
                    </li>
                    <li><?php _e('分类'); ?>: <?php $archive->category(','); ?></li>
                    <li itemprop="interactionCount">
                        <a itemprop="discussionUrl" href="<?php $archive->permalink(); ?>#comments"><?php $archive->commentsNum(_t('暂无评论'), _t('1 条评论'), _t('%d 条评论')); ?></a>
                    </li>
                </ul>
            </header>
            <div class="post-content" itemprop="articleBody">
                <?php $archive->content(_t('阅读全文 <i class="fa fa-angle-double-right"></i>')); ?>
            </div>
        </article>
        <?php
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
