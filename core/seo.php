<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * SEO 优化（标题/描述/关键词/canonical/面包屑等）
 * 从 functions.php 分层迁移
 */

/**
 * 净化 URL，仅允许 http/https 协议，防止 CSS/JS 注入
 * 用于在 style 属性或 src 属性中输出用户可控的 URL
 *
 * @param string $url 原始 URL
 * @return string 净化后的 URL，若协议不允许则返回空字符串
 */
function shufei_sanitize_url($url)
{
    if (empty($url)) return '';
    $url = trim($url);
    // 仅允许 http:// 和 https:// 协议；相对路径（以 / 或 ./ 开头）也允许
    if (preg_match('#^https?://#i', $url) || preg_match('#^(\./|/)#', $url)) {
        // 移除可能用于 CSS 注入的字符：() ; 以及控制字符
        $url = preg_replace('/[\x00-\x1F\x7F-\x9F\(\);]/', '', $url);
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
    return '';
}

/**
 * 截取并清理文本作为 SEO 描述
 *
 * @param string $text 原始文本
 * @param int $length 截取长度
 * @return string
 */
function shufei_seo_trim($text, $length = 120)
{
    if ($text === null) {
        $text = '';
    }
    $text = strip_tags($text);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    if (mb_strlen($text, 'UTF-8') > $length) {
        $text = mb_substr($text, 0, $length, 'UTF-8') . '...';
    }
    return htmlspecialchars($text);
}

/**
 * 获取当前页面的 SEO 关键词
 *
 * 优先级：文章自定义关键词 > 文章标签 > 全站 SEO 关键词 > 站点标题
 *
 * @return string
 */
function shufei_get_seo_keywords()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $keywords = '';

    if (shufei_is_post() || shufei_is_page()) {
        $post = shufei_get_archive();
        if ($post && !empty($post->fields->keywords)) {
            $keywords = $post->fields->keywords;
        } elseif ($post && !empty($post->tags) && is_array($post->tags)) {
            $tagNames = array();
            foreach ($post->tags as $tag) {
                if (isset($tag['name'])) {
                    $tagNames[] = $tag['name'];
                }
            }
            $keywords = implode(',', $tagNames);
        }
    }

    if (empty($keywords) && !empty($options->seoKeywords)) {
        $keywords = $options->seoKeywords;
    }

    if (empty($keywords)) {
        $keywords = $options->title ?? '';
    }

    return htmlspecialchars(trim((string)$keywords, ' ,'));
}

/**
 * 获取当前页面的 SEO 描述
 *
 * 优先级：文章自定义 excerpt > 文章内容截取 > 全站 SEO 描述 > Typecho 站点描述
 *
 * @return string
 */
function shufei_get_seo_description()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $description = '';

    if (shufei_is_post()) {
        $post = shufei_get_archive();
        if ($post && !empty($post->fields->excerpt)) {
            $description = shufei_seo_trim($post->fields->excerpt, 160);
        } elseif ($post && !empty($post->content)) {
            $description = shufei_seo_trim($post->content, 160);
        }
    } elseif (shufei_is_page()) {
        $page = shufei_get_archive();
        if ($page && !empty($page->content)) {
            $description = shufei_seo_trim($page->content, 160);
        }
    }

    if (empty($description) && !empty($options->seoDescription)) {
        $description = shufei_seo_trim($options->seoDescription, 160);
    }

    if (empty($description) && !empty($options->description)) {
        $description = shufei_seo_trim($options->description, 160);
    }

    if (empty($description)) {
        $description = htmlspecialchars($options->title ?? '', ENT_QUOTES, 'UTF-8');
    }

    return $description;
}

/**
 * 获取当前页面的 SEO 标题
 *
 * @return string
 */
function shufei_get_seo_title()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $archive = shufei_get_archive();
    $siteTitle = htmlspecialchars($options->title ?? '', ENT_QUOTES, 'UTF-8');

    if (shufei_is_post() && $archive) {
        return htmlspecialchars($archive->title ?? '', ENT_QUOTES, 'UTF-8') . ' - ' . $siteTitle;
    }

    if (shufei_is_page() && $archive) {
        return htmlspecialchars($archive->title ?? '', ENT_QUOTES, 'UTF-8') . ' - ' . $siteTitle;
    }

    if (shufei_is_category() && $archive) {
        $archiveTitle = $archive->archiveTitle ?? '';
        if (empty($archiveTitle)) {
            $archiveTitle = _t('分类');
        }
        return htmlspecialchars($archiveTitle, ENT_QUOTES, 'UTF-8') . ' - ' . $siteTitle;
    }

    if (shufei_is_tag() && $archive) {
        $archiveTitle = $archive->archiveTitle ?? '';
        if (empty($archiveTitle)) {
            $archiveTitle = _t('标签');
        }
        return htmlspecialchars($archiveTitle, ENT_QUOTES, 'UTF-8') . ' - ' . $siteTitle;
    }

    if (shufei_is_search()) {
        $s = isset($_GET['s']) ? htmlspecialchars(trim($_GET['s']), ENT_QUOTES, 'UTF-8') : '';
        if (empty($s) && $archive && !empty($archive->archiveTitle)) {
            $s = htmlspecialchars($archive->archiveTitle, ENT_QUOTES, 'UTF-8');
        }
        return sprintf(_t('包含关键字 %s 的文章'), $s) . ' - ' . $siteTitle;
    }

    if (shufei_is_author() && $archive) {
        $name = !empty($archive->screenName) ? $archive->screenName : (!empty($archive->name) ? $archive->name : '');
        return sprintf(_t('%s 发布的文章'), htmlspecialchars($name, ENT_QUOTES, 'UTF-8')) . ' - ' . $siteTitle;
    }

    if (shufei_is_archive()) {
        return _t('文章归档') . ' - ' . $siteTitle;
    }

    return $siteTitle;
}

/**
 * 获取社交分享图（OG / Twitter Card）
 *
 * @param object|null $archive 当前内容对象（文章/页面）
 * @return string
 */
function shufei_get_seo_og_image($archive = null)
{
    $options = \Typecho\Widget::widget('Widget_Options');

    if ($archive && !empty($archive->fields->thumbnail)) {
        return $archive->fields->thumbnail;
    }

    if ($archive && !empty($archive->content)) {
        preg_match_all('/<img.*?src=["\'](.*?)["\']/', $archive->content, $matches);
        if (!empty($matches[1][0])) {
            return $matches[1][0];
        }
    }

    if (!empty($options->seoOgImage)) {
        return $options->seoOgImage;
    }

    return '';
}

/**
 * 获取当前页面的 canonical URL
 *
 * 覆盖范围：文章/页面/分类/标签/作者/归档/首页分页
 * 分页页面使用各自完整 URL，不被统一指向首页
 *
 * @return string
 */
function shufei_get_canonical_url()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $archive = shufei_get_archive();

    // 文章/独立页面：使用 permalink
    if (shufei_is_post() || shufei_is_page()) {
        if ($archive) {
            try {
                $permalink = $archive->permalink;
                if (!empty($permalink)) {
                    return $permalink;
                }
            } catch (\Throwable $e) {}
        }
    }

    // 列表型页面（分类/标签/作者/归档）：使用 getArchiveUrl() 避免无文章时 permalink 触发 TypeError
    if ($archive && method_exists($archive, 'getArchiveUrl') && !shufei_is_404() && !shufei_is_search()) {
        try {
            $archiveUrl = $archive->getArchiveUrl();
            if (!empty($archiveUrl)) {
                // 如果有分页，追加页码参数
                $currentPage = isset($archive->_currentPage) ? intval($archive->_currentPage) : 1;
                if ($currentPage > 1) {
                    $archiveUrl = rtrim($archiveUrl, '/') . '/page/' . $currentPage . '/';
                }
                return $archiveUrl;
            }
        } catch (\Throwable $e) {}
    }

    // 兜底使用站点首页
    return $options->siteUrl;
}

/**
 * 获取分页页面的 prev/next URL（用于 SEO link rel）
 *
 * @return array ['prev' => url|null, 'next' => url|null]
 */
function shufei_get_prev_next_page()
{
    $result = array('prev' => null, 'next' => null);
    $archive = shufei_get_archive();
    if (!$archive) return $result;

    // 仅在列表型页面（首页/分类/标签/作者/归档）生效
    if (shufei_is_post() || shufei_is_page() || shufei_is_404() || shufei_is_search()) {
        return $result;
    }

    try {
        $currentPage = isset($archive->_currentPage) ? intval($archive->_currentPage) : 1;
        $totalPage = 0;
        if (method_exists($archive, 'getTotal') && method_exists($archive, 'parameter') && $archive->parameter) {
            $pageSize = intval($archive->parameter->pageSize);
            if ($pageSize > 0) {
                $totalPage = ceil(intval($archive->getTotal()) / $pageSize);
            }
        }
        if ($totalPage <= 1) return $result;

        // 生成上一页 URL
        if ($currentPage > 1) {
            $result['prev'] = shufei_build_page_url($archive, $currentPage - 1);
        }
        // 生成下一页 URL
        if ($currentPage < $totalPage) {
            $result['next'] = shufei_build_page_url($archive, $currentPage + 1);
        }
    } catch (\Throwable $e) {}

    return $result;
}

/**
 * 根据当前 archive 上下文构建指定页码的 URL
 *
 * @param object $archive
 * @param int $page
 * @return string
 */
function shufei_build_page_url($archive, $page)
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $siteUrl = $options->siteUrl;

    try {
        // 列表型页面：优先使用 getArchiveUrl()，避免无文章时 permalink 触发 TypeError
        if (method_exists($archive, 'getArchiveUrl')) {
            $currentUrl = $archive->getArchiveUrl();
        } else {
            $currentUrl = $archive->permalink;
        }
        if (empty($currentUrl)) {
            return $siteUrl;
        }
        $currentPage = isset($archive->_currentPage) ? intval($archive->_currentPage) : 1;

        // 常见模式 1: /page/N/
        if (preg_match('#/page/' . $currentPage . '/?$#', $currentUrl)) {
            return preg_replace('#/page/' . $currentPage . '/?$#', '/page/' . $page . '/', $currentUrl);
        }
        // 常见模式 2: ?page=N（query string）
        if (preg_match('#[?&]page=' . $currentPage . '($|&)#', $currentUrl)) {
            return preg_replace('#([?&]page=)' . $currentPage . '#', '${1}' . $page, $currentUrl);
        }
        // 第一页通常无页码标记，向下翻页时追加 /page/N/
        if ($currentPage === 1) {
            // 去掉 query string 后追加 /page/N/
            $base = preg_replace('#\?.*$#', '', $currentUrl);
            $base = rtrim($base, '/') . '/page/' . $page . '/';
            return $base;
        }
    } catch (\Throwable $e) {}

    return $siteUrl;
}

/**
 * 获取文章字数（纯文本字符数）
 *
 * @param object|null $archive
 * @return int
 */
function shufei_get_word_count($archive = null)
{
    if (!$archive) $archive = shufei_get_archive();
    if (!$archive || empty($archive->text)) return 0;

    $text = $archive->text;
    // 去除 Markdown 标记
    $text = preg_replace('/```[\s\S]*?```/', '', $text);
    $text = preg_replace('/`[^`]*`/', '', $text);
    $text = preg_replace('/!\[.*?\]\(.*?\)/', '', $text);
    $text = preg_replace('/\[([^\]]*)\]\(.*?\)/', '$1', $text);
    $text = preg_replace('/^#{1,6}\s+/m', '', $text);
    $text = preg_replace('/^[>\-\*\+]\s*/m', '', $text);
    $text = preg_replace('/\$\$[\s\S]+?\$\$/', '', $text);
    $text = preg_replace('/(?<!\$)\$(?!\$)[^\$\n]+?(?<!\$)\$(?!\$)/', '', $text);
    $text = strip_tags($text);
    $text = preg_replace('/\s+/', '', $text);

    return mb_strlen($text, 'UTF-8');
}

/**
 * 估算阅读时长（分钟）
 *
 * @param object|null $archive
 * @return int
 */
function shufei_get_reading_time($archive = null)
{
    $wordCount = shufei_get_word_count($archive);
    // 中文阅读速度约 300-500 字/分钟
    $minutes = max(1, intval(ceil($wordCount / 400)));
    return $minutes;
}

/**
 * 获取 OG 图片尺寸信息
 *
 * @param string $imageUrl
 * @return array|null ['width' => int, 'height' => int]
 */
function shufei_get_og_image_dimensions($imageUrl)
{
    if (empty($imageUrl)) return null;

    // 仅处理本站图片，避免对外部图片发起请求
    $siteUrl = \Typecho\Widget::widget('Widget_Options')->siteUrl;
    $siteHost = parse_url($siteUrl, PHP_URL_HOST);
    $imgHost = parse_url($imageUrl, PHP_URL_HOST);

    // 远程图片无法可靠获取尺寸，返回默认 1200x630
    if (empty($imgHost) || $imgHost !== $siteHost) {
        return array('width' => 1200, 'height' => 630);
    }

    // 本站图片：尝试转换为服务器路径
    $themeDir = dirname(__FILE__);
    $rootDir = dirname(dirname(dirname(dirname(__FILE__)))); // usr/themes/ShuFeiCat -> 根目录
    $imgPath = parse_url($imageUrl, PHP_URL_PATH);

    // 候选路径
    $candidates = array();
    if ($imgPath) {
        $candidates[] = $rootDir . $imgPath;
        $candidates[] = rtrim($rootDir, '/') . $imgPath;
    }

    foreach ($candidates as $path) {
        if (is_file($path)) {
            $info = @getimagesize($path);
            if ($info && isset($info[0]) && isset($info[1]) && $info[0] > 0) {
                return array('width' => $info[0], 'height' => $info[1]);
            }
        }
    }

    // 无法获取时返回默认
    return array('width' => 1200, 'height' => 630);
}

/**
 * 生成面包屑结构化数据
 *
 * @return array BreadcrumbList JSON-LD 数组
 */
function shufei_get_breadcrumbs_jsonld()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $siteUrl = $options->siteUrl;
    $siteTitle = $options->title;
    $archive = shufei_get_archive();

    $items = array();
    $items[] = array(
        '@type' => 'ListItem',
        'position' => 1,
        'name' => $siteTitle,
        'item' => $siteUrl
    );

    $position = 2;

    if (shufei_is_post() && $archive) {
        // 首页 > 分类 > 文章
        if (!empty($archive->categories) && is_array($archive->categories)) {
            $cat = $archive->categories[0];
            if (isset($cat['permalink']) && isset($cat['name'])) {
                $items[] = array(
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $cat['name'],
                    'item' => $cat['permalink']
                );
            }
        }
        $items[] = array(
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $archive->title,
            'item' => $archive->permalink
        );
    } elseif (shufei_is_page() && $archive) {
        $items[] = array(
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $archive->title,
            'item' => $archive->permalink
        );
    } else {
        // 列表型页面（分类/标签/作者/归档）：使用 getArchiveUrl() 避免无文章时 permalink 触发 TypeError
        $archiveUrl = method_exists($archive, 'getArchiveUrl') ? $archive->getArchiveUrl() : '';
        $archiveTitle = method_exists($archive, 'getArchiveTitle') ? $archive->getArchiveTitle() : '';

        if (shufei_is_category() && $archive && !empty($archiveUrl)) {
            $items[] = array(
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => !empty($archiveTitle) ? $archiveTitle : _t('分类'),
                'item' => $archiveUrl
            );
        } elseif (shufei_is_tag() && $archive && !empty($archiveUrl)) {
            $items[] = array(
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => !empty($archiveTitle) ? (_t('标签: ') . $archiveTitle) : _t('标签'),
                'item' => $archiveUrl
            );
        } elseif (shufei_is_author() && $archive && !empty($archiveUrl)) {
            $items[] = array(
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => !empty($archiveTitle) ? $archiveTitle : _t('作者'),
                'item' => $archiveUrl
            );
        } elseif (shufei_is_archive() && $archive && !empty($archiveUrl)) {
            $items[] = array(
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => _t('归档'),
                'item' => $archiveUrl
            );
        }
    }

    // 仅有首页时不输出面包屑
    if (count($items) <= 1) return null;

    return array(
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $items
    );
}

/**
 * 输出当前页面的 robots meta 值
 *
 * @return string
 */
function shufei_get_robots_content()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $mode = !empty($options->seoRobots) ? $options->seoRobots : 'auto';

    if ($mode === 'noindex') {
        return 'noindex,nofollow';
    }
    if ($mode === 'index') {
        return 'index,follow';
    }

    // auto 模式
    if (shufei_is_search() || shufei_is_404()) {
        return 'noindex,nofollow';
    }

    // 分页大于 1 标记为 noindex
    if (!empty($_GET['page']) && intval($_GET['page']) > 1) {
        return 'noindex,follow';
    }

    return 'index,follow';
}

