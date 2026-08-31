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

    // 2. 先从文章原始 Markdown 文本提取第一张图片（性能优化：不触发 content 全量解析）
    //    访问 $post->content 会触发 HyperDown 全文解析 + 16 轮扩展正则，
    //    列表页每篇文章都解析一次代价极高（约 10-30ms/篇）。Markdown 文章在原始文本
    //    中即可命中 ![alt](url)，绝大多数场景无需走到第 3 步。
    if (!empty($post->text)) {
        // 去掉 Typecho 的 <!--markdown--> 前缀
        $rawText = preg_replace('/^<!--markdown-->/', '', $post->text);
        // 剔除行内代码块，避免匹配到示例语法如 `![desc](url)` 中的 url
        $cleanText = preg_replace('/`[^`]*`/', '', $rawText);
        preg_match_all('/!\[.*?\]\(([^)]+)\)/', $cleanText, $mdMatches);
        if (!empty($mdMatches[1][0])) {
            return $mdMatches[1][0];
        }
    }

    // 3. 最后才从解析后的 HTML 内容提取 img 标签（覆盖富文本编辑器撰写的内容，
    //    仅在原始文本未命中时触发一次全量 content 解析）
    $content = $post->content ?? '';
    preg_match_all('/<img.*?src=["\'](.*?)["\']/', $content, $matches);
    if (!empty($matches[1])) {
        return $matches[1][0];
    }

    // 4. 使用随机缩略图
    return shufei_get_random_thumbnail();
}

/**
 * 列表页批量预加载 fields / categories / author 数据（消除 N+1 查询）
 *
 * 原理：Typecho Widget::__get 优先读取 row['#name'] 缓存（见 var/Typecho/Widget.php），
 * 通过公开的 __set 魔法为每个文章对象预置 '#fields'/'#categories'/'#author'，
 * 模板随后访问 $post->fields / $post->category(',') / $post->author 均直接命中，不再逐篇查库。
 *
 * 查询数：20 篇文章从约 60 次（fields+categories+author 各 1 次/篇）降为 3 次
 * （fields 1 次批量 IN + relationships/categories 1 次批量 IN + author 每唯一作者 1 次）。
 *
 * @param array $posts 文章 widget 对象数组（clone 自 Archive/From）
 * @return void
 */
function shufei_preload_list_data(array $posts)
{
    if (empty($posts)) {
        return;
    }

    $db = \Typecho\Db::get();

    // 收集 cid 与唯一作者 uid
    $cids = array();
    $uids = array();
    foreach ($posts as $post) {
        $cid = intval($post->cid);
        if ($cid > 0) {
            $cids[] = $cid;
        }
        $uid = intval($post->authorId);
        if ($uid > 0) {
            $uids[$uid] = true;
        }
    }
    $cids = array_values(array_unique($cids));
    if (empty($cids)) {
        return;
    }

    // ---- 1. fields 批量预取（与 ___fields() 相同的取值逻辑）----
    $fieldsRows = $db->fetchAll($db->select()->from('table.fields')->where('cid IN ?', $cids));
    $fieldsByCid = array();
    foreach ($fieldsRows as $row) {
        $cid = intval($row['cid']);
        if (!isset($fieldsByCid[$cid])) {
            $fieldsByCid[$cid] = array();
        }
        $value = 'json' == $row['type'] ? json_decode($row['str_value'], true) : $row[$row['type'] . '_value'];
        $fieldsByCid[$cid][$row['name']] = $value;
    }

    // ---- 2. categories 批量预取（结构与 ___categories()/toArray 一致）----
    // 先取 cid → mid 映射（一次查询），再复用 Rows::alloc() 的全量分类缓存（含 permalink，
    // 侧边栏分类树也调用 Rows::alloc()，同请求内共享同一次查询）
    $relRows = $db->fetchAll($db->select('table.relationships.cid', 'table.metas.mid')
        ->from('table.relationships')
        ->join('table.metas', 'table.relationships.mid = table.metas.mid')
        ->where('table.relationships.cid IN ?', $cids)
        ->where('table.metas.type = ?', 'category'));
    $midsByCid = array();
    foreach ($relRows as $row) {
        $midsByCid[intval($row['cid'])][] = intval($row['mid']);
    }

    $categoryRows = array();
    if (!empty($midsByCid)) {
        try {
            $allCategories = \Widget\Metas\Category\Rows::alloc()->toArray(array(
                'mid', 'name', 'slug', 'description', 'order', 'parent', 'count', 'permalink'
            ));
            foreach ($allCategories as $cat) {
                $categoryRows[intval($cat['mid'])] = $cat;
            }
        } catch (\Exception $e) {
            $categoryRows = array();
        }
    }

    // ---- 3. author 预取（每唯一 uid 仅查询一次，实例共享给同作者的所有文章）----
    $authorByUid = array();
    foreach (array_keys($uids) as $uid) {
        try {
            // Author widget 查询 users 表 where uid = ?，实例复用避免重复查询
            $authorByUid[$uid] = \Widget\Users\Author::allocWithAlias('shufei_preload_author_' . $uid, array('uid' => $uid));
        } catch (\Exception $e) {
            // 作者不存在等异常时跳过，回退到模板原有的惰性加载路径
            unset($authorByUid[$uid]);
        }
    }

    // ---- 4. 预置到每个文章对象（__set 检测到 ___xxx 方法存在时写入 row['#xxx'] 缓存）----
    foreach ($posts as $post) {
        $cid = intval($post->cid);

        // fields：空字段集合也预置（与 ___fields() 行为一致返回空 Config）
        $fieldsConfig = new \Typecho\Config(isset($fieldsByCid[$cid]) ? $fieldsByCid[$cid] : array());
        $post->fields = $fieldsConfig;

        // categories：保持 Rows 全表顺序（Rows 已按 order 排序），与 CategoryRelated 输出结构一致
        $cats = array();
        if (isset($midsByCid[$cid])) {
            foreach ($midsByCid[$cid] as $mid) {
                if (isset($categoryRows[$mid])) {
                    $cats[] = $categoryRows[$mid];
                }
            }
        }
        $post->categories = $cats;

        // author
        $uid = intval($post->authorId);
        if ($uid > 0 && isset($authorByUid[$uid])) {
            $post->author = $authorByUid[$uid];
        }
    }
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
 * 获取站长头像URL
 * 支持邮箱（自动使用所选 Gravatar 镜像源）或图片 URL
 *
 * @return string 头像URL
 */
function shufei_get_author_avatar_url()
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $val = isset($options->authorAvatar) ? trim($options->authorAvatar) : '';
    if ($val === '') {
        $val = 'https://q1.qlogo.cn/g?b=qq&nk=3522934828&s=100';
    }
    if (filter_var($val, FILTER_VALIDATE_EMAIL)) {
        return shufei_get_gravatar_url($val, 200);
    }
    return $val;
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

/**
 * 渲染密码保护表单（纯主题实现，不走 Typecho 核心的密码 POST 流程）
 *
 * 密码提交到 core/password-verify.php 由主题自行验证：
 *  - 密码正确：写入 protectPassword_{cid} cookie 并跳回内容页
 *  - 密码错误：跳回内容页并附带 pw_error=1，此处显示"密码错误"提示
 * 这样可避免触发 Typecho 核心在密码错误时抛出的 403 异常（调试模式下显示堆栈）。
 *
 * @param object $widget 当前 Archive 对象（模板中的 $this）
 * @param string $title  标题
 * @param string $desc   描述文字
 * @return string
 */
function shufei_render_password_protection($widget, $title = '文章已加密~', $desc = '')
{
    if ($desc === '') {
        $desc = _t('这是一篇受密码保护的内容，请输入正确的密码来查看全文。');
    }

    $html = '<div class="password-protection">';
    $html .= '<div class="password-lock-icon"><i class="fa fa-lock"></i></div>';
    $html .= '<h2 class="password-title">' . $title . '</h2>';
    $html .= '<p class="password-desc">' . $desc . '</p>';

    if ($widget->request->get('pw_error')) {
        $html .= '<p class="password-error"><i class="fa fa-times-circle"></i> ' . _t('密码错误，请重新输入') . '</p>';
    }

    // 注意：$widget->options 为 protected 属性，在独立函数（类外部作用域）中访问会触发
    // Widget::__get() 返回 null，故此处使用全局 Options 单例；
    // 属性式 themeUrl 不带尾部斜杠，需 rtrim 后补斜杠再拼接
    $options = \Typecho\Widget::widget('Widget_Options');
    $themeRoot = rtrim($options->themeUrl, '/') . '/';
    $html .= '<form class="protected" data-ajax="1" action="' . $themeRoot . 'core/password-verify.php" method="post">';
    $html .= '<p><input type="password" class="text" name="password" placeholder="' . _t('请输入访问密码') . '" />';
    $html .= '<input type="hidden" name="cid" value="' . $widget->cid . '" />';
    $html .= '<input type="hidden" name="return" value="' . $widget->permalink . '" />';
    $html .= '<input type="submit" class="submit" value="' . _t('提交') . '" /></p>';
    $html .= '</form>';

    $html .= '<p class="password-hint"><i class="fa fa-info-circle"></i> ' . _t('请联系博主获取访问密码') . '</p>';
    $html .= '</div>';

    return $html;
}

