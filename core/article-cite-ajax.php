<?php
/**
 * 文章引用选择器 AJAX 接口（后台编辑器用）
 *
 * 处理后台写文章/写页面时「引用文章」选择器的搜索请求。
 * 与评论引用的 article_search（前台 ajax-handler.php）区别：
 *  - 本端点仅允许已登录的管理员/编辑访问（后台编辑器场景）
 *  - 不加隐私锁：编辑器有权查看全部状态的文章（草稿/待审核等），
 *    前台渲染时再由 shufei_render_article_cites 的隐私锁决定是否显示
 *
 * 注意：必须手动调用 Cookie::setPrefix() 初始化 Cookie 前缀，
 * 否则 Widget_User 无法正确读取带前缀的 __typecho_uid 等认证 Cookie
 * （与 ai-writer-ajax.php 同一套初始化流程，详见该文件注释）
 */

// 输出 JSON 头
header('Content-Type: application/json; charset=utf-8');

// 载入 Typecho 配置（定义 __TYPECHO_ROOT_DIR__、自动加载、数据库连接）
if (!defined('__TYPECHO_ROOT_DIR__')) {
    $rootDir = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
    if (file_exists($rootDir . '/config.inc.php')) {
        require_once $rootDir . '/config.inc.php';
    } else {
        echo json_encode(array('success' => false, 'message' => '系统配置文件缺失'));
        exit;
    }
}

/**
 * 关键：手动定义 __TYPECHO_ROOT_URL__
 *
 * 本端点位于 /usr/themes/ShuFeiCat/core/，getRequestRoot() 会返回该子目录
 * 路径，导致 Cookie 前缀与后台登录时不一致，无法读取认证 Cookie。
 * 通过预先定义常量强制使用站点根 URL（优先取 config.inc.php 的站点 URL）。
 */
if (!defined('__TYPECHO_ROOT_URL__')) {
    if (defined('__TYPECHO_SITE_URL__')) {
        define('__TYPECHO_ROOT_URL__', __TYPECHO_SITE_URL__);
    } else {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $protocol = $isSecure ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '');
        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
        $basePath = '';
        if ($scriptName && defined('__TYPECHO_ROOT_DIR__')) {
            $relPath = str_replace('\\', '/', substr(__FILE__, strlen(__TYPECHO_ROOT_DIR__)));
            if ($relPath && substr($scriptName, -strlen($relPath)) === $relPath) {
                $basePath = substr($scriptName, 0, -strlen($relPath));
            }
        }
        define('__TYPECHO_ROOT_URL__', rtrim($protocol . '://' . $host . $basePath, '/'));
    }
}

try {
    // 加载全局选项（连接数据库读取站点配置）
    $options = \Widget\Options::alloc();
    // 手动设置 Cookie 前缀，使 Widget\User 能读取带前缀的认证 Cookie
    \Typecho\Cookie::setPrefix($options->rootUrl);
} catch (\Throwable $e) {
    echo json_encode(array('success' => false, 'message' => '系统初始化失败: ' . $e->getMessage()));
    exit;
}

// 鉴权：仅允许已登录的管理员/编辑使用（贡献者/订阅者无权编辑文章）
try {
    $user = \Widget\User::alloc();
    if (!$user->hasLogin()) {
        echo json_encode(array('success' => false, 'message' => '请先登录'));
        exit;
    }
    $group = $user->group ?? '';
    if (!in_array($group, array('administrator', 'editor'), true)) {
        echo json_encode(array('success' => false, 'message' => '权限不足，仅管理员或编辑可使用文章引用'));
        exit;
    }
} catch (\Throwable $e) {
    echo json_encode(array('success' => false, 'message' => '身份验证失败: ' . $e->getMessage()));
    exit;
}

// CSRF 防护：校验 Origin/Referer 同源
require_once dirname(__FILE__) . '/admin-csrf.php';
if (!shufei_admin_csrf_verify()) {
    echo json_encode(array('success' => false, 'message' => '请求来源校验失败，请刷新页面后重试'));
    exit;
}

// 搜索文章（引用候选）
$action = isset($_POST['action']) ? $_POST['action'] : '';
if ($action !== 'search') {
    echo json_encode(array('success' => false, 'message' => '未知操作'));
    exit;
}

$q = isset($_POST['q']) ? trim((string)$_POST['q']) : '';
if (function_exists('mb_substr')) {
    $q = mb_substr($q, 0, 50, 'UTF-8');
} else {
    $q = substr($q, 0, 150);
}
// 排除当前正在编辑的文章（引用"其他"文章，避免自引用）
$exclude = isset($_POST['exclude']) ? intval($_POST['exclude']) : 0;
$limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
if ($limit < 1) $limit = 10;
if ($limit > 20) $limit = 20;

try {
    $db = \Typecho\Db::get();
    $prefix = $db->getPrefix();
    $select = $db->select('cid, title, created, status')
        ->from($prefix . 'contents')
        // 只要文章（排除页面/附件/修订稿 post_draft）
        ->where('type = ?', 'post')
        ->order('created', \Typecho\Db::SORT_DESC)
        ->limit($limit);
    if ($exclude > 0) {
        $select->where('cid <> ?', $exclude);
    }
    if ($q !== '') {
        // LIKE 通配符手动转义，防止 %/_ 注入
        $esc = str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $q);
        $select->where('title LIKE ?', '%' . $esc . '%');
    }
    $rows = $db->fetchAll($select);

    $statusLabels = array(
        'publish' => '已发布',
        'waiting' => '待审核',
        'draft'   => '草稿',
        'private' => '私密',
        'hidden'  => '隐藏',
    );
    $articles = array();
    foreach ($rows as $row) {
        $status = (string)$row['status'];
        $articles[] = array(
            'cid'         => intval($row['cid']),
            'title'       => (string)$row['title'],
            'date'        => date('Y-m-d', intval($row['created'])),
            'status'      => $status,
            'statusLabel' => isset($statusLabels[$status]) ? $statusLabels[$status] : $status,
        );
    }
    echo json_encode(array('success' => true, 'articles' => $articles), JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    error_log('[ShuFeiCat] article-cite-ajax 搜索失败: ' . $e->getMessage());
    echo json_encode(array('success' => false, 'message' => '搜索失败，请稍后重试'));
}
