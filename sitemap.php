<?php
/**
 * Sitemap 生成器
 * 由 ShuFeiCat 主题提供，动态输出符合 sitemaps.org 协议的 XML 站点地图
 *
 * 可放在网站根目录（https://lwcat.cn/sitemap.php）
 * 也可放在主题目录（https://lwcat.cn/usr/themes/ShuFeiCat/sitemap.php）
 */

// 自适应查找 Typecho 配置文件（支持根目录和主题目录两种部署位置）
if (!defined('__TYPECHO_ROOT_DIR__')) {
    $configPaths = array(
        __DIR__ . '/config.inc.php',                  // 同目录（根目录部署）
        dirname(__DIR__, 3) . '/config.inc.php',      // 上溯3级（主题目录部署）
        rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/config.inc.php', // 文档根目录
    );
    $loaded = false;
    foreach ($configPaths as $path) {
        if (file_exists($path)) {
            $ret = include_once $path;
            if ($ret !== false) {
                $loaded = true;
                break;
            }
        }
    }
    if (!$loaded) {
        http_response_code(503);
        exit('Missing Config File');
    }
}

// 输出 XML 头
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
header('X-Robots-Tag: noindex');

// 初始化 Typecho 数据库和选项
$db = \Typecho\Db::get();
$options = \Widget\Options::alloc();
$siteUrl = $options->siteUrl;
// 独立脚本中 $baseUrl 基于当前脚本路径计算，在主题目录部署时会包含 /usr/themes/xxx/ 前缀
// 此处基于 siteUrl 和 rewrite 设置重新计算正确的 base URL
$baseUrl = rtrim($siteUrl, '/');
if (empty($options->rewrite)) {
    $baseUrl .= '/index.php';
}
$now = date('c');

$urls = array();

// 1. 首页
$urls[] = array(
    'loc' => $siteUrl,
    'lastmod' => $now,
    'changefreq' => 'daily',
    'priority' => '1.0'
);

// 2. 所有已发布文章
try {
    $posts = $db->fetchAll(
        $db->select('cid', 'slug', 'created', 'modified', 'type')
            ->from('table.contents')
            ->where('type = ?', 'post')
            ->where('status = ?', 'publish')
            ->where('created < ?', \Typecho\Date::time())
            ->order('created', \Typecho\Db::SORT_DESC)
    );

    foreach ($posts as $post) {
        $permalink = $baseUrl . '/archives/' . $post['cid'] . '/';
        // 优先使用 slug 路径（更友好）
        if (!empty($post['slug'])) {
            $permalink = $baseUrl . '/archives/' . $post['slug'] . '/';
        }
        $lastmod = date('c', max($post['modified'], $post['created']));
        $urls[] = array(
            'loc' => $permalink,
            'lastmod' => $lastmod,
            'changefreq' => 'weekly',
            'priority' => '0.8'
        );
    }
} catch (\Exception $e) {}

// 3. 所有独立页面
try {
    $pages = $db->fetchAll(
        $db->select('cid', 'slug', 'created', 'modified')
            ->from('table.contents')
            ->where('type = ?', 'page')
            ->where('status = ?', 'publish')
            ->where('created < ?', \Typecho\Date::time())
            ->order('order', \Typecho\Db::SORT_ASC)
    );

    foreach ($pages as $page) {
        $permalink = $baseUrl . '/' . $page['slug'] . '.html';
        $lastmod = date('c', max($page['modified'], $page['created']));
        $urls[] = array(
            'loc' => $permalink,
            'lastmod' => $lastmod,
            'changefreq' => 'monthly',
            'priority' => '0.6'
        );
    }
} catch (\Exception $e) {}

// 4. 所有分类页面
try {
    $categories = $db->fetchAll(
        $db->select('mid', 'slug', 'name', 'count')
            ->from('table.metas')
            ->where('type = ?', 'category')
            ->order('order', \Typecho\Db::SORT_ASC)
    );

    foreach ($categories as $cat) {
        if ($cat['count'] > 0) {
            $permalink = $baseUrl . '/category/' . $cat['slug'] . '/';
            $urls[] = array(
                'loc' => $permalink,
                'changefreq' => 'weekly',
                'priority' => '0.6'
            );
        }
    }
} catch (\Exception $e) {}

// 5. 所有标签页面
try {
    $tags = $db->fetchAll(
        $db->select('mid', 'slug', 'name', 'count')
            ->from('table.metas')
            ->where('type = ?', 'tag')
            ->where('count > 0')
            ->order('count', \Typecho\Db::SORT_DESC)
            ->limit(200) // 限制数量避免过多
    );

    foreach ($tags as $tag) {
        $permalink = $baseUrl . '/tag/' . $tag['slug'] . '/';
        $urls[] = array(
            'loc' => $permalink,
            'changefreq' => 'weekly',
            'priority' => '0.4'
        );
    }
} catch (\Exception $e) {}

// 6. 作者页面
try {
    $authors = $db->fetchAll(
        $db->select('uid', 'name', 'screenName')
            ->from('table.users')
            ->where('group <> ?', 'visitor')
    );

    foreach ($authors as $author) {
        $permalink = $baseUrl . '/author/' . $author['uid'] . '/';
        $urls[] = array(
            'loc' => $permalink,
            'changefreq' => 'weekly',
            'priority' => '0.4'
        );
    }
} catch (\Exception $e) {}

// 输出 XML
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($urls as $url) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
    if (!empty($url['lastmod'])) {
        echo "    <lastmod>" . $url['lastmod'] . "</lastmod>\n";
    }
    if (!empty($url['changefreq'])) {
        echo "    <changefreq>" . $url['changefreq'] . "</changefreq>\n";
    }
    if (!empty($url['priority'])) {
        echo "    <priority>" . $url['priority'] . "</priority>\n";
    }
    echo "  </url>\n";
}

echo '</urlset>' . "\n";
