<?php
/**
 * 免保存实时预览 AJAX 接口
 *
 * action=preview_save（POST）：把编辑器当前内容（text/title/markdown 模式）
 * 走与前台完全相同的渲染管线（shufei_render_post_content 假 widget：
 * pre_markdown_process → 行内代码保护 → KaTeX 保护 → HyperDown → 还原 →
 * shufei_apply_markdown_ext → 回复可见），结果暂存为一次性 token，由
 * preview-render.php 读取并整页呈现。
 *
 * 相比原生预览（admin/preview.php + write-js.php）：
 *  - 不保存草稿、不污染 draft 字段（原生 changed 时会 confirm 并 savePost）
 *  - 新文章（无 cid 无草稿）也能立即预览（原生三分支全落空静默无响应）
 *  - 渲染的就是编辑器当前内容，所见即所得
 *
 * 仅允许已登录的 administrator / editor 使用。鉴权与 CSRF 同 storage-ajax.php。
 */

header('Content-Type: application/json; charset=utf-8');

// 全局 fatal error handler：任何阶段的致命错误都返回 JSON
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(array('success' => false, 'message' => 'PHP致命错误: ' . $err['message']));
    }
});

// 载入 Typecho 配置
if (!defined('__TYPECHO_ROOT_DIR__')) {
    $rootDir = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
    if (file_exists($rootDir . '/config.inc.php')) {
        require_once $rootDir . '/config.inc.php';
    } else {
        echo json_encode(array('success' => false, 'message' => '系统配置文件缺失'));
        exit;
    }
}

// 手动定义 __TYPECHO_ROOT_URL__ 以让 Cookie 前缀与后台一致
if (!defined('__TYPECHO_ROOT_URL__')) {
    if (defined('__TYPECHO_SITE_URL__')) {
        define('__TYPECHO_ROOT_URL__', __TYPECHO_SITE_URL__);
    } else {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $protocol = $isSecure ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '');
        define('__TYPECHO_ROOT_URL__', rtrim($protocol . '://' . $host, '/'));
    }
}

try {
    $options = \Widget\Options::alloc();
    \Typecho\Cookie::setPrefix($options->rootUrl);
} catch (\Throwable $e) {
    echo json_encode(array('success' => false, 'message' => '系统初始化失败: ' . $e->getMessage()));
    exit;
}

// 鉴权
try {
    $user = \Widget\User::alloc();
    if (!$user->hasLogin()) {
        echo json_encode(array('success' => false, 'message' => '请先登录'));
        exit;
    }
    $group = $user->group ?? '';
    if (!in_array($group, array('administrator', 'editor'), true)) {
        echo json_encode(array('success' => false, 'message' => '权限不足，仅管理员或编辑可使用'));
        exit;
    }
} catch (\Throwable $e) {
    echo json_encode(array('success' => false, 'message' => '身份验证失败: ' . $e->getMessage()));
    exit;
}

// CSRF 防护
require_once dirname(__FILE__) . '/admin-csrf.php';
if (!shufei_admin_csrf_verify()) {
    echo json_encode(array('success' => false, 'message' => '请求来源校验失败，请刷新页面后重试'));
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'preview_save') {
    handlePreviewSave();
} else {
    echo json_encode(array('success' => false, 'message' => '未知操作'));
}
exit;

/**
 * 渲染编辑器当前内容并暂存为一次性 token
 */
function handlePreviewSave()
{
    $text = isset($_POST['text']) ? (string)$_POST['text'] : '';
    $title = isset($_POST['title']) ? (string)$_POST['title'] : '';
    $isMarkdown = !empty($_POST['markdown']) && $_POST['markdown'] !== '0';
    $cid = isset($_POST['cid']) ? preg_replace('/\D/', '', (string)$_POST['cid']) : '';
    if ($cid === '') $cid = '0';

    if (trim($text) === '') {
        echo json_encode(array('success' => false, 'message' => '内容为空，无法预览'));
        return;
    }

    // 与前台同款渲染管线：构造假 widget 复用 shufei_render_post_content
    try {
        require_once dirname(__FILE__) . '/markdown.php';
        require_once dirname(__FILE__) . '/media.php'; // shufei_process_images（apply_markdown_ext 依赖）
        require_once dirname(__FILE__) . '/comments.php'; // shufei_parse_reply_content / 引用卡片（render_post_content 依赖）
        // ⚠️ 必须与前台 functions.php 的 $_coreLibs 保持一致加载 ai-chat.php：
        // shufei_get_comment_cites_info() 构建引用卡片 permalink 时用的是
        // class_exists('AiChat') 守卫——缺此类时 permalink 为空，
        // [cite:cid] 卡片会降级成不可点击的 <span>（前台正常、预览死链）。
        require_once dirname(__FILE__) . '/ai-chat.php'; // AiChat::buildPermalink（引用卡片链接）
        $fakeWidget = (object)array(
            'cid' => (int)$cid,
            'text' => $text,
            'isMarkdown' => $isMarkdown,
            'modified' => time(),
            'created' => time(),
        );
        $html = shufei_render_post_content($fakeWidget);
        // 与 post.php 一致的旧 fields 兜底：正文无 [cite: 标记时，按被编辑文章的
        // 自定义字段 shufeiCitePosts 渲染文末引用列表（旧文章迁移前的数据形态），
        // 保证预览与前台呈现一致。预览帧无 fields 概念，需自行查库。
        $fieldsCites = '';
        if ((int)$cid > 0 && strpos($text, '[cite:') === false) {
            try {
                $db = \Typecho\Db::get();
                $prefix = $db->getPrefix();
                $row = $db->fetchRow($db->select()->from($prefix . 'contents')->where('cid = ?', (int)$cid));
                if ($row && !empty($row['fields'])) {
                    $fields = @unserialize((string)$row['fields']);
                    if (is_array($fields) && !empty($fields['shufeiCitePosts'])) {
                        $fieldsCites = shufei_render_article_cites(shufei_get_article_cites($fields['shufeiCitePosts']));
                    }
                }
            } catch (\Throwable $e) {
                $fieldsCites = ''; // 兜底失败不阻断预览
            }
        }
    } catch (\Throwable $e) {
        echo json_encode(array('success' => false, 'message' => '渲染失败: ' . $e->getMessage()));
        return;
    }

    // 暂存目录（复用主题 cache，带 GC）
    $dir = dirname(__FILE__) . '/cache/preview';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    // GC：清理 10 分钟前的残留 token 文件
    foreach ((array)glob($dir . '/*.json') as $old) {
        if (is_file($old) && (time() - filemtime($old)) > 600) {
            @unlink($old);
        }
    }

    $token = bin2hex(random_bytes(16));
    $payload = array(
        'uid' => (int)\Widget\User::alloc()->uid,
        'cid' => $cid,
        'title' => $title,
        'html' => $html,
        'fieldsCites' => $fieldsCites,
        'time' => time(),
    );
    if (@file_put_contents($dir . '/' . $token . '.json', json_encode($payload, JSON_UNESCAPED_UNICODE)) === false) {
        echo json_encode(array('success' => false, 'message' => '暂存失败，请检查 cache 目录权限'));
        return;
    }
    echo json_encode(array('success' => true, 'token' => $token));
}
