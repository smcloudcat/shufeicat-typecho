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
 * 获取数据库适配器类型
 * @return string
 */
function shufei_get_db_adapter_name()
{
    $db = \Typecho\Db::get();
    return $db->getAdapterName();
}

/**
 * 引用列名（兼容 MySQL/PostgreSQL/SQLite）
 * @param string $name
 * @return string
 */
function shufei_quote_column($name)
{
    $db = \Typecho\Db::get();
    return $db->getAdapter()->quoteColumn($name);
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
    $adapterName = shufei_get_db_adapter_name();
    $tableName = $prefix . 'post_stats';
    $quotedTable = shufei_quote_column($tableName);

    try {
        $db->query("SELECT 1 FROM " . $quotedTable . " LIMIT 1");
    } catch (\Exception $e) {
        $isPgsql = ($adapterName === 'Pdo_Pgsql' || $adapterName === 'Pgsql');
        $isSqlite = ($adapterName === 'Pdo_SQLite' || $adapterName === 'SQLite');

        if ($isPgsql) {
            // PostgreSQL 不支持 ENGINE/CHARSET/COMMENT/unsigned/KEY，需要使用兼容语法
            $sql = "CREATE TABLE IF NOT EXISTS " . $quotedTable . " (
                \"cid\" integer NOT NULL,
                \"views\" integer NOT NULL DEFAULT 0,
                \"likes\" integer NOT NULL DEFAULT 0,
                \"updated_at\" integer NOT NULL DEFAULT 0,
                PRIMARY KEY (\"cid\")
            )";
        } elseif ($isSqlite) {
            $sql = "CREATE TABLE IF NOT EXISTS " . $quotedTable . " (
                \"cid\" integer NOT NULL PRIMARY KEY,
                \"views\" integer NOT NULL DEFAULT 0,
                \"likes\" integer NOT NULL DEFAULT 0,
                \"updated_at\" integer NOT NULL DEFAULT 0
            )";
        } else {
            // MySQL / MariaDB
            $sql = "CREATE TABLE IF NOT EXISTS " . $quotedTable . " (
                `cid` int(10) unsigned NOT NULL,
                `views` int(10) unsigned NOT NULL DEFAULT '0',
                `likes` int(10) unsigned NOT NULL DEFAULT '0',
                `updated_at` int(10) unsigned NOT NULL DEFAULT '0',
                PRIMARY KEY (`cid`),
                KEY `idx_views` (`views`),
                KEY `idx_likes` (`likes`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        }
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
 * 使用 Cookie 时间窗口防刷：同一访客对同一文章在指定时间窗口内只计一次浏览
 * @param int $cid 文章ID
 * @return int 新的浏览量
 */
function shufei_add_view($cid)
{
    shufei_ensure_stats_table();

    // Cookie 防刷：同一文章 3600 秒（1小时）内不重复计数
    $cookieKey = 'shufei_viewed_' . $cid;
    if (isset($_COOKIE[$cookieKey])) {
        return shufei_get_views($cid);
    }

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

    // 设置防刷 Cookie，1 小时有效
    setcookie($cookieKey, '1', time() + 3600, '/');

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

/* ==================== 评论点赞功能 ==================== */

/**
 * 确保评论统计表存在
 */
function shufei_ensure_comment_stats_table()
{
    static $checked = false;
    if ($checked) return;

    $db = \Typecho\Db::get();
    $prefix = shufei_get_db_prefix();
    $adapterName = shufei_get_db_adapter_name();
    $tableName = $prefix . 'comment_stats';
    $quotedTable = shufei_quote_column($tableName);

    try {
        $db->query("SELECT 1 FROM " . $quotedTable . " LIMIT 1");
    } catch (\Exception $e) {
        $isPgsql = ($adapterName === 'Pdo_Pgsql' || $adapterName === 'Pgsql');
        $isSqlite = ($adapterName === 'Pdo_SQLite' || $adapterName === 'SQLite');

        if ($isPgsql) {
            $sql = "CREATE TABLE IF NOT EXISTS " . $quotedTable . " (
                \"coid\" integer NOT NULL,
                \"likes\" integer NOT NULL DEFAULT 0,
                \"updated_at\" integer NOT NULL DEFAULT 0,
                PRIMARY KEY (\"coid\")
            )";
        } elseif ($isSqlite) {
            $sql = "CREATE TABLE IF NOT EXISTS " . $quotedTable . " (
                \"coid\" integer NOT NULL PRIMARY KEY,
                \"likes\" integer NOT NULL DEFAULT 0,
                \"updated_at\" integer NOT NULL DEFAULT 0
            )";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS " . $quotedTable . " (
                `coid` int(10) unsigned NOT NULL,
                `likes` int(10) unsigned NOT NULL DEFAULT '0',
                `updated_at` int(10) unsigned NOT NULL DEFAULT '0',
                PRIMARY KEY (`coid`),
                KEY `idx_likes` (`likes`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        }
        $db->query($sql);
    }

    $checked = true;
}

/**
 * 批量获取评论点赞数（带静态缓存）
 * @param int $cid 文章ID，用于缓存键
 * @return array [coid => likes]
 */
function shufei_get_comment_likes_map($cid)
{
    static $maps = array();
    $cid = intval($cid);
    if (isset($maps[$cid])) return $maps[$cid];

    shufei_ensure_comment_stats_table();

    $db = \Typecho\Db::get();
    $prefix = shufei_get_db_prefix();

    // 查询该文章所有评论的点赞数
    $adapterName = shufei_get_db_adapter_name();
    $isPgsql = ($adapterName === 'Pdo_Pgsql' || $adapterName === 'Pgsql');
    $q = $isPgsql ? '"' : '`';

    $sql = "SELECT s.{$q}coid{$q}, s.{$q}likes{$q}
        FROM {$q}{$prefix}comment_stats{$q} s
        INNER JOIN {$q}{$prefix}comments{$q} c ON s.{$q}coid{$q} = c.{$q}coid{$q}
        WHERE c.{$q}cid{$q} = {$cid} AND s.{$q}likes{$q} > 0";

    try {
        $rows = $db->fetchAll($sql);
    } catch (\Exception $e) {
        $rows = array();
    }

    $map = array();
    foreach ($rows as $row) {
        $map[intval($row['coid'])] = intval($row['likes']);
    }

    $maps[$cid] = $map;
    return $map;
}

/**
 * 获取单条评论点赞数
 * @param int $coid 评论ID
 * @return int
 */
function shufei_get_comment_likes($coid)
{
    shufei_ensure_comment_stats_table();

    $db = \Typecho\Db::get();
    $prefix = shufei_get_db_prefix();

    $row = $db->fetchRow($db->select('likes')->from($prefix . 'comment_stats')->where('coid = ?', $coid));
    return $row ? intval($row['likes']) : 0;
}

/**
 * 增加评论点赞数
 * @param int $coid 评论ID
 * @return array 结果数组
 */
function shufei_add_comment_like($coid)
{
    shufei_ensure_comment_stats_table();

    $db = \Typecho\Db::get();
    $prefix = shufei_get_db_prefix();
    $time = time();

    // cookie 防刷
    $cookieKey = 'shufei_comment_liked_' . $coid;
    if (isset($_COOKIE[$cookieKey])) {
        return array('success' => false, 'message' => '您已经点过赞了', 'likes' => shufei_get_comment_likes($coid));
    }

    // 校验评论是否存在
    $comment = $db->fetchRow($db->select('coid', 'status')->from($prefix . 'comments')->where('coid = ?', $coid));
    if (!$comment) {
        return array('success' => false, 'message' => '评论不存在');
    }

    $exists = $db->fetchRow($db->select('coid')->from($prefix . 'comment_stats')->where('coid = ?', $coid));

    if ($exists) {
        $db->query($db->update($prefix . 'comment_stats')
            ->expression('likes', 'likes + 1')
            ->rows(array('updated_at' => $time))
            ->where('coid = ?', $coid));
    } else {
        $db->query($db->insert($prefix . 'comment_stats')
            ->rows(array('coid' => $coid, 'likes' => 1, 'updated_at' => $time)));
    }

    setcookie($cookieKey, '1', time() + 30 * 86400, '/');

    return array('success' => true, 'message' => '点赞成功', 'likes' => shufei_get_comment_likes($coid));
}

/**
 * 检查用户是否已对该评论点赞
 * @param int $coid 评论ID
 * @return bool
 */
function shufei_has_comment_liked($coid)
{
    $cookieKey = 'shufei_comment_liked_' . $coid;
    return isset($_COOKIE[$cookieKey]);
}

/**
 * 获取排行榜文章列表（带文件缓存）
 * @param string $type 排序类型: views(浏览量) | likes(点赞数)
 * @param int $limit 数量限制
 * @return array
 */
function shufei_get_ranking_posts($type = 'views', $limit = 5)
{
    // 文件缓存：5 分钟内直接返回缓存结果，避免频繁查库
    $cacheKey = 'ranking_' . $type . '_' . $limit;
    $cacheDir = __DIR__ . '/../cache';
    $cacheFile = $cacheDir . '/' . md5($cacheKey) . '.cache';

    if (file_exists($cacheFile)) {
        $cacheTime = filemtime($cacheFile);
        if ($cacheTime && (time() - $cacheTime) < 300) {
            // 使用 json_decode 替代 unserialize，避免对象注入风险
            // 旧格式（serialize）缓存 json_decode 会返回 null，自然失效重建
            $cached = @json_decode(@file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }
    }

    shufei_ensure_stats_table();

    $db = \Typecho\Db::get();
    $prefix = shufei_get_db_prefix();
    $adapterName = shufei_get_db_adapter_name();
    $isPgsql = ($adapterName === 'Pdo_Pgsql' || $adapterName === 'Pgsql');
    // PostgreSQL 标识符是双引号，MySQL 是反引号
    $q = $isPgsql ? '"' : '`';

    $orderBy = ($type === 'likes') ? 'likes' : 'views';

    $sql = "SELECT c.cid, c.title, c.slug, c.created, c.type, s.views, s.likes
        FROM {$q}{$prefix}contents{$q} c
        INNER JOIN {$q}{$prefix}post_stats{$q} s ON c.cid = s.cid
        WHERE c.type = 'post' AND c.status = 'publish'
        ORDER BY s.{$orderBy} DESC
        LIMIT {$limit}";

    $posts = $db->fetchAll($sql);

    if (empty($posts)) {
        $sql = "SELECT cid, title, slug, created, type, 0 as views, 0 as likes
            FROM {$q}{$prefix}contents{$q}
            WHERE type = 'post' AND status = 'publish'
            ORDER BY created DESC
            LIMIT {$limit}";
        $posts = $db->fetchAll($sql);
    }

    foreach ($posts as &$post) {
        $date = new \Typecho\Date($post['created']);
        $post['year'] = $date->year;
        $post['month'] = $date->month;
        $post['day'] = $date->day;
        $post['slug'] = urlencode($post['slug']);

        $categories = $db->fetchAll($db->select()->from($prefix . 'metas')
            ->join($prefix . 'relationships', $prefix . 'relationships.mid = ' . $prefix . 'metas.mid')
            ->where($prefix . 'relationships.cid = ?', $post['cid'])
            ->where($prefix . 'metas.type = ?', 'category')
            ->order($prefix . 'metas.order', \Typecho\Db::SORT_ASC));

        if (!empty($categories)) {
            $post['category'] = urlencode($categories[0]['slug']);

            $parentSlugs = [];
            $parentId = $categories[0]['parent'] ?? 0;
            while ($parentId > 0) {
                $parent = $db->fetchRow($db->select('mid', 'slug', 'parent')->from($prefix . 'metas')
                    ->where('mid = ?', $parentId));
                if ($parent) {
                    $parentSlugs[] = urlencode($parent['slug']);
                    $parentId = $parent['parent'];
                } else {
                    break;
                }
            }
            $parentSlugs = array_reverse($parentSlugs);
            $parentSlugs[] = urlencode($categories[0]['slug']);
            $post['directory'] = implode('/', $parentSlugs);
        } else {
            $post['category'] = '';
            $post['directory'] = '';
        }
    }
    unset($post);

    // 写入缓存
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    @file_put_contents($cacheFile, json_encode($posts, JSON_UNESCAPED_UNICODE));

    return $posts;
}
