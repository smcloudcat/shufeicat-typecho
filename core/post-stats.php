<?php
/**
 * 文章统计功能核心文件
 * 包含点赞、浏览量、排行榜等功能
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 获取数据库前缀
 * @return string
 */
function shufei_get_db_prefix()
{
    $db = \Typecho\Db::get();
    return $db->getPrefix();
}

/**
 * 确保文章统计表存在
 */
function shufei_ensure_stats_table()
{
    static $checked = false;
    if ($checked) return;
    
    $db = \Typecho\Db::get();
    $prefix = shufei_get_db_prefix();
    $tableName = $prefix . 'post_stats';
    
    try {
        $db->query("SELECT 1 FROM `{$tableName}` LIMIT 1");
    } catch (\Exception $e) {
        $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
            `cid` int(10) unsigned NOT NULL COMMENT '文章ID',
            `views` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '浏览量',
            `likes` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '点赞数',
            `updated_at` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
            PRIMARY KEY (`cid`),
            KEY `idx_views` (`views`),
            KEY `idx_likes` (`likes`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文章统计表'";
        $db->query($sql);
    }
    
    $checked = true;
}

/**
 * 获取文章浏览量
 * @param int $cid 文章ID
 * @return int
 */
function shufei_get_views($cid)
{
    shufei_ensure_stats_table();
    
    $db = \Typecho\Db::get();
    $prefix = shufei_get_db_prefix();
    
    $row = $db->fetchRow($db->select('views')->from($prefix . 'post_stats')->where('cid = ?', $cid));
    return $row ? intval($row['views']) : 0;
}

/**
 * 获取文章点赞数
 * @param int $cid 文章ID
 * @return int
 */
function shufei_get_likes($cid)
{
    shufei_ensure_stats_table();
    
    $db = \Typecho\Db::get();
    $prefix = shufei_get_db_prefix();
    
    $row = $db->fetchRow($db->select('likes')->from($prefix . 'post_stats')->where('cid = ?', $cid));
    return $row ? intval($row['likes']) : 0;
}

/**
 * 增加文章浏览量
 * @param int $cid 文章ID
 * @return int 新的浏览量
 */
function shufei_add_view($cid)
{
    shufei_ensure_stats_table();
    
    $db = \Typecho\Db::get();
    $prefix = shufei_get_db_prefix();
    $time = time();
    
    // 检查记录是否存在
    $exists = $db->fetchRow($db->select('cid')->from($prefix . 'post_stats')->where('cid = ?', $cid));
    
    if ($exists) {
        $db->query($db->update($prefix . 'post_stats')
            ->expression('views', 'views + 1')
            ->rows(array('updated_at' => $time))
            ->where('cid = ?', $cid));
    } else {
        $db->query($db->insert($prefix . 'post_stats')
            ->rows(array('cid' => $cid, 'views' => 1, 'likes' => 0, 'updated_at' => $time)));
    }
    
    return shufei_get_views($cid);
}

/**
 * 增加文章点赞数
 * @param int $cid 文章ID
 * @return array 结果数组
 */
function shufei_add_like($cid)
{
    shufei_ensure_stats_table();
    
    $db = \Typecho\Db::get();
    $prefix = shufei_get_db_prefix();
    $time = time();
    
    // 使用cookie防止重复点赞
    $cookieKey = 'shufei_liked_' . $cid;
    if (isset($_COOKIE[$cookieKey])) {
        return array('success' => false, 'message' => '您已经点过赞了', 'likes' => shufei_get_likes($cid));
    }
    
    // 检查记录是否存在
    $exists = $db->fetchRow($db->select('cid')->from($prefix . 'post_stats')->where('cid = ?', $cid));
    
    if ($exists) {
        $db->query($db->update($prefix . 'post_stats')
            ->expression('likes', 'likes + 1')
            ->rows(array('updated_at' => $time))
            ->where('cid = ?', $cid));
    } else {
        $db->query($db->insert($prefix . 'post_stats')
            ->rows(array('cid' => $cid, 'views' => 0, 'likes' => 1, 'updated_at' => $time)));
    }
    
    // 设置cookie，30天内不能重复点赞
    setcookie($cookieKey, '1', time() + 30 * 86400, '/');
    
    return array('success' => true, 'message' => '点赞成功', 'likes' => shufei_get_likes($cid));
}

/**
 * 检查用户是否已点赞
 * @param int $cid 文章ID
 * @return bool
 */
function shufei_has_liked($cid)
{
    $cookieKey = 'shufei_liked_' . $cid;
    return isset($_COOKIE[$cookieKey]);
}

/**
 * 获取排行榜文章列表
 * @param string $type 排序类型: views(浏览量) | likes(点赞数)
 * @param int $limit 数量限制
 * @return array
 */
function shufei_get_ranking_posts($type = 'views', $limit = 5)
{
    shufei_ensure_stats_table();
    
    $db = \Typecho\Db::get();
    $prefix = shufei_get_db_prefix();
    
    $orderBy = ($type === 'likes') ? 'likes' : 'views';
    
    $sql = "SELECT c.cid, c.title, c.slug, c.created, s.views, s.likes 
        FROM `{$prefix}contents` c
        INNER JOIN `{$prefix}post_stats` s ON c.cid = s.cid
        WHERE c.type = 'post' AND c.status = 'publish'
        ORDER BY s.{$orderBy} DESC
        LIMIT {$limit}";
    
    $posts = $db->fetchAll($sql);
    
    // 如果没有统计数据，返回最新的文章
    if (empty($posts)) {
        $sql = "SELECT cid, title, slug, created, 0 as views, 0 as likes 
            FROM `{$prefix}contents`
            WHERE type = 'post' AND status = 'publish'
            ORDER BY created DESC
            LIMIT {$limit}";
        $posts = $db->fetchAll($sql);
    }
    
    return $posts;
}
