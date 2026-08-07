<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 模板辅助函数（archive 判断、缩略图、导航、友链等）
 * 从 functions.php 分层迁移
 */

/**
 * 随机缩略图逻辑
 */
function shufei_get_random_thumbnail()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $thumbnailSource = isset($options->thumbnailSource) ? $options->thumbnailSource : 'local';
    
    if ($thumbnailSource === 'remote') {
        $remoteImages = isset($options->remoteImages) ? $options->remoteImages : '';
        if (!empty($remoteImages)) {
            $imageList = preg_split('/\R/', trim($remoteImages));
            if (!empty($imageList)) {
                return $imageList[array_rand($imageList)];
            }
        }
    }
    
    $siteUrl = isset($options->siteUrl) ? $options->siteUrl : '';
    $themeUrl = \Typecho\Common::url('/usr/themes/ShuFeiCat/', $siteUrl);
    $defaultImages = array('default-1.svg', 'default-2.svg', 'default-3.svg', 'default-4.svg', 'default-5.svg');
    return $themeUrl . 'image/' . $defaultImages[array_rand($defaultImages)];
}

/**
 * 获取所有置顶文章的CID
 *
 * @return array 置顶文章CID数组
 */
function shufei_get_sticky_cids()
{
    $db = \Typecho\Db::get();
    $rows = $db->fetchAll($db->select('cid')->from('table.fields')
        ->where('name = ?', 'sticky')
        ->where('str_value = ?', '1'));

    $cids = [];
    foreach ($rows as $row) {
        $cids[] = $row['cid'];
    }
    return $cids;
}

/**
 * 获取文章缩略图
 *
 * @param object $post 文章对象
 * @return string 缩略图URL
 */
function shufei_get_post_thumbnail($post)
{
    // 1. 首先检查文章自定义字段中的缩略图
    $thumbnail = $post->fields->thumbnail;
    if (!empty($thumbnail)) {
        return $thumbnail;
    }
    
    // 2. 从文章内容中提取第一张图片（支持HTML img标签）
    $content = $post->content ?? '';
    preg_match_all('/<img.*?src=["\'](.*?)["\']/', $content, $matches);
    if (!empty($matches[1])) {
        return $matches[1][0];
    }
    
    // 3. 从文章原始文本中提取第一张图片（支持Markdown格式 ![alt](url)）
    if (!empty($post->text)) {
        // 先去掉 Typecho 的 <!--markdown--> 前缀
        $rawText = preg_replace('/^<!--markdown-->/', '', $post->text);
        // 剔除行内代码块，避免匹配到示例语法如 `![desc](url)` 中的 url
        $cleanText = preg_replace('/`[^`]*`/', '', $rawText);
        preg_match_all('/!\[.*?\]\(([^)]+)\)/', $cleanText, $mdMatches);
        if (!empty($mdMatches[1][0])) {
            return $mdMatches[1][0];
        }
    }
    
    // 4. 使用随机缩略图
    return shufei_get_random_thumbnail();
}

/**
 * 获取Gravatar头像URL
 * 
 * @param string $email 邮箱地址
 * @param int $size 头像尺寸
 * @return string 头像URL
 */
function shufei_get_gravatar_url($email, $size = 80)
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $gravatarSource = isset($options->gravatarSource) ? $options->gravatarSource : 'cat';
    
    // 根据配置选择镜像源
    switch ($gravatarSource) {
        case 'loli':
            $baseUrl = 'https://gravatar.loli.net/avatar/';
            break;
        case 'weavatar':
            $baseUrl = 'https://weavatar.com/avatar/';
            break;
        case 'cravatar':
            $baseUrl = 'https://cravatar.cn/avatar/';
            break;
        case 'official':
            $baseUrl = 'https://secure.gravatar.com/avatar/';
            break;
        case 'cat':
        default:
            $baseUrl = 'https://gravatar.luoli.click/avatar/';
            break;
    }
    
    $hash = md5(strtolower(trim($email)));
    return $baseUrl . $hash . '?s=' . $size . '&d=identicon&r=g';
}

/**
 * 获取当前 Archive widget（用于检测当前页面类型与获取内容）
 *
 * @return \Typecho\Widget\Archive|null
 */
function shufei_get_archive()
{
    static $archive = null;
    if ($archive === null) {
        try {
            $archive = \Typecho\Widget::widget('Widget_Archive');
        } catch (\Exception $e) {
            $archive = false;
        }
    }
    return $archive ?: null;
}

/**
 * 当前页面是否为文章页
 */
function shufei_is_post()
{
    $a = shufei_get_archive();
    return $a && method_exists($a, 'is') && $a->is('post');
}

/**
 * 当前页面是否为独立页面
 */
function shufei_is_page()
{
    $a = shufei_get_archive();
    return $a && method_exists($a, 'is') && $a->is('page');
}

/**
 * 当前页面是否为分类页
 */
function shufei_is_category()
{
    $a = shufei_get_archive();
    return $a && method_exists($a, 'is') && $a->is('category');
}

/**
 * 当前页面是否为标签页
 */
function shufei_is_tag()
{
    $a = shufei_get_archive();
    return $a && method_exists($a, 'is') && $a->is('tag');
}

/**
 * 当前页面是否为搜索结果页
 */
function shufei_is_search()
{
    $a = shufei_get_archive();
    return $a && method_exists($a, 'is') && $a->is('search');
}

/**
 * 当前页面是否为作者归档页
 */
function shufei_is_author()
{
    $a = shufei_get_archive();
    return $a && method_exists($a, 'is') && $a->is('author');
}

/**
 * 当前页面是否为日期归档页
 */
function shufei_is_archive()
{
    $a = shufei_get_archive();
    return $a && method_exists($a, 'is') && $a->is('archive');
}

/**
 * 当前页面是否为 404
 */
function shufei_is_404()
{
    // 模板为 404.php 时确定为 404 页面
    $template = \Typecho\Widget::widget('Widget_Options')->template;
    if ($template === '404.php') {
        return true;
    }
    // 否则通过排除法：不是任何已知页面类型时视为 404
    return !shufei_is_post() && !shufei_is_page() && !shufei_is_category()
        && !shufei_is_tag() && !shufei_is_search() && !shufei_is_author()
        && !shufei_is_archive();
}

/**
 * 获取分类目录的自定义图标
 * 通过分类缩略名匹配后台配置的图标
 *
 * @param string $slug 分类缩略名
 * @return string Font Awesome 图标类名
 */
function shufei_get_category_icon($slug)
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $categoryIcons = isset($options->categoryIcons) ? $options->categoryIcons : '';

    if (!empty($categoryIcons)) {
        $lines = preg_split('/\R/', trim($categoryIcons));
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $parts = explode('|', $line, 2);
            if (count($parts) == 2) {
                $iconSlug = trim($parts[0]);
                $iconClass = trim($parts[1]);
                if ($iconSlug === $slug) {
                    return $iconClass;
                }
            }
        }
    }

    return 'fa-folder-open-o';
}

/**
 * 获取自定义导航项
 *
 * @return array 导航项数组，每项包含 icon, name, url
 */
function shufei_get_custom_nav_items()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $customNavItems = isset($options->customNavItems) ? $options->customNavItems : '';
    $items = array();

    if (!empty($customNavItems)) {
        $lines = preg_split('/\R/', trim($customNavItems));
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $parts = explode('|', $line, 3);
            if (count($parts) >= 2) {
                $items[] = array(
                    'icon' => !empty($parts[0]) ? trim($parts[0]) : 'fa-link',
                    'name' => trim($parts[1]),
                    'url'  => isset($parts[2]) ? trim($parts[2]) : '#'
                );
            }
        }
    }

    return $items;
}

/**
 * 获取留言板页面链接
 *
 * @return string 留言板页面URL，未找到返回空字符串
 */
function shufei_get_guestbook_url()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $db = \Typecho\Db::get();

    // 优先使用配置的页面ID
    $pageId = isset($options->guestbookPageId) ? trim($options->guestbookPageId) : '';
    if (!empty($pageId)) {
        $row = $db->fetchRow($db->select('cid', 'slug')->from('table.contents')
            ->where('cid = ?', intval($pageId))
            ->where('status = ?', 'publish'));
        if ($row) {
            return $options->index . '/' . $row['slug'] . '.html';
        }
    }

    // 自动查找使用留言板模板的页面
    $rows = $db->fetchAll($db->select('cid', 'slug')->from('table.contents')
        ->where('template = ?', 'guestbook.php')
        ->where('status = ?', 'publish')
        ->limit(1));

    if (!empty($rows)) {
        return $options->index . '/' . $rows[0]['slug'] . '.html';
    }

    return '';
}

/**
 * 获取友链独立页面URL
 * 优先使用配置的页面ID，其次自动查找使用 links.php 模板的页面
 *
 * @return string 友链页面URL，未找到返回空字符串
 */
function shufei_get_links_url()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $db = \Typecho\Db::get();

    // 优先使用配置的页面ID
    $pageId = isset($options->linksPageId) ? trim($options->linksPageId) : '';
    if (!empty($pageId)) {
        $row = $db->fetchRow($db->select('cid', 'slug')->from('table.contents')
            ->where('cid = ?', intval($pageId))
            ->where('status = ?', 'publish'));
        if ($row) {
            return $options->index . '/' . $row['slug'] . '.html';
        }
    }

    // 自动查找使用友链页面模板的页面
    $rows = $db->fetchAll($db->select('cid', 'slug')->from('table.contents')
        ->where('template = ?', 'links.php')
        ->where('status = ?', 'publish')
        ->limit(1));

    if (!empty($rows)) {
        return $options->index . '/' . $rows[0]['slug'] . '.html';
    }

    return '';
}

/**
 * 解析友链配置，返回结构化的友链数组
 * 支持两种格式：
 *   基本格式（逗号）：名称,链接地址
 *   完整格式（竖线）：名称|链接地址|描述|头像地址
 * 描述和头像为可选项
 *
 * @return array 友链数组，每个元素包含 name, url, description, avatar
 */
function shufei_parse_links()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $linksRaw = isset($options->links) ? trim($options->links) : '';
    if (empty($linksRaw)) {
        return array();
    }

    $result = array();
    $lines = explode("\n", $linksRaw);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }

        // 优先使用竖线分隔（完整格式），其次使用逗号分隔（基本格式）
        if (strpos($line, '|') !== false) {
            $parts = array_map('trim', explode('|', $line, 4));
        } else {
            $parts = array_map('trim', explode(',', $line, 4));
        }

        if (count($parts) < 2 || empty($parts[0]) || empty($parts[1])) {
            continue;
        }

        $result[] = array(
            'name'        => $parts[0],
            'url'         => $parts[1],
            'description' => isset($parts[2]) ? $parts[2] : '',
            'avatar'      => isset($parts[3]) ? $parts[3] : ''
        );
    }

    return $result;
}

