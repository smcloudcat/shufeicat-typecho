<?php
/**
 * 免保存实时预览呈现页
 *
 * GET token=xxx：校验登录态 + 编辑权限 + token 归属 + 时效（10 分钟），
 * 读取 preview-ajax.php 暂存的渲染结果，以「前台同款排版上下文」整页输出：
 *  - 前台同款 CSS（normalize / style / font-awesome）+ vendorCssUrls（prism/katex/lightbox）
 *  - 前台同款 .post-content 正文容器
 *  - 前台同款 main.js（代码高亮 / 灯箱 / KaTeX / Mermaid 按需注入逻辑一致）
 *  - 预览专用标记 window.__shufeiPreviewFrame（main.js 据此只跑正文渲染相关 init）
 *
 * token 一次性：读取后立即删除文件。
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    $rootDir = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
    if (file_exists($rootDir . '/config.inc.php')) {
        require_once $rootDir . '/config.inc.php';
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        exit('系统配置缺失');
    }
}
if (!defined('__TYPECHO_ROOT_URL__')) {
    if (defined('__TYPECHO_SITE_URL__')) {
        define('__TYPECHO_ROOT_URL__', __TYPECHO_SITE_URL__);
    } else {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $protocol = $isSecure ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        define('__TYPECHO_ROOT_URL__', rtrim($protocol . '://' . $host, '/'));
    }
}

try {
    \Typecho\Cookie::setPrefix(__TYPECHO_ROOT_URL__);
} catch (\Throwable $e) {
}

// 登录 + 权限校验
try {
    $user = \Widget\User::alloc();
    if (!$user->hasLogin()) {
        header('HTTP/1.1 403 Forbidden');
        exit('请先登录后台');
    }
    $group = $user->group ?? '';
    if (!in_array($group, array('administrator', 'editor'), true)) {
        header('HTTP/1.1 403 Forbidden');
        exit('权限不足');
    }
    $uid = (int)$user->uid;
} catch (\Throwable $e) {
    header('HTTP/1.1 403 Forbidden');
    exit('身份验证失败');
}

$token = isset($_GET['token']) ? preg_replace('/[^a-f0-9]/', '', (string)$_GET['token']) : '';
if (strlen($token) !== 32) {
    header('HTTP/1.1 400 Bad Request');
    exit('token 无效');
}

$file = dirname(__FILE__) . '/cache/preview/' . $token . '.json';
$payload = null;
if (is_file($file)) {
    $payload = json_decode((string)@file_get_contents($file), true);
    @unlink($file); // 一次性：读取后立即删除
}
if (!$payload || !is_array($payload)
    || (int)($payload['uid'] ?? 0) !== $uid
    || (int)($payload['time'] ?? 0) < time() - 600) {
    header('HTTP/1.1 410 Gone');
    exit('预览已过期，请重新点击预览');
}

$options = \Widget\Options::alloc();
$themeUrl = rtrim($options->themeUrl, '/') . '/';

// 资源模式与 CDN 前缀（与 header.php / footer.php 同逻辑）
$resourceMode = !empty($options->resourceMode) ? $options->resourceMode : 'local';
$customCdn = !empty($options->customCdn) ? rtrim($options->customCdn, '/') : '';
$codeHlOn = (empty($options->codeHighlightEnabled) || $options->codeHighlightEnabled !== 'off');
$katexOn = (!empty($options->katexEnabled) && $options->katexEnabled === 'on');
$mermaidOn = (!empty($options->mermaidEnabled) && $options->mermaidEnabled === 'on');
$echartsOn = (!empty($options->echartsEnabled) && $options->echartsEnabled === 'on');

if ($resourceMode === 'cdn') {
    $cssUrls = array(
        'prism' => 'https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism-tomorrow.min.css',
        'katex' => 'https://cdn.jsdelivr.net/npm/katex@0.17.0/dist/katex.min.css',
        'lightbox' => 'https://cdn.jsdelivr.net/npm/lightbox3@1.1.0/dist/css/lightbox.min.css',
    );
    $jsUrls = array(
        'prism' => 'https://cdn.jsdelivr.net/npm/prismjs@1.29.0/prism.min.js',
        'prismAutoloader' => 'https://cdn.jsdelivr.net/npm/prismjs@1.29.0/plugins/autoloader/prism-autoloader.min.js',
        'lightbox' => 'https://cdn.jsdelivr.net/npm/lightbox3@1.1.0/dist/js/lightbox.min.js',
        'mermaid' => 'https://cdn.jsdelivr.net/npm/mermaid@10.9.6/mermaid.min.js',
        'echarts' => 'https://cdn.jsdelivr.net/npm/echarts@6.1.0/dist/echarts.min.js',
        'katex' => 'https://cdn.jsdelivr.net/npm/katex@0.17.0/dist/katex.min.js',
        'katexAutoRender' => 'https://cdn.jsdelivr.net/npm/katex@0.17.0/dist/contrib/auto-render.min.js',
    );
    $prismAutoloaderPath = '';
} elseif ($resourceMode === 'custom' && $customCdn) {
    $cssUrls = array(
        'prism' => $customCdn . '/assets/vendor/prismjs/prism-tomorrow.min.css',
        'katex' => $customCdn . '/assets/vendor/katex/katex.min.css',
        'lightbox' => $customCdn . '/assets/vendor/lightbox3/lightbox3.min.css',
    );
    $jsUrls = array(
        'prism' => $customCdn . '/assets/vendor/prismjs/prism.js',
        'prismAutoloader' => $customCdn . '/assets/vendor/prismjs/plugins/autoloader/prism-autoloader.min.js',
        'lightbox' => $customCdn . '/assets/vendor/lightbox3/lightbox3.min.js',
        'mermaid' => $customCdn . '/assets/vendor/mermaid/mermaid.min.js',
        'echarts' => $customCdn . '/assets/vendor/echarts/echarts.min.js',
        'katex' => $customCdn . '/assets/vendor/katex/katex.min.js',
        'katexAutoRender' => $customCdn . '/assets/vendor/katex/auto-render.min.js',
    );
    $prismAutoloaderPath = '';
} else {
    $cssUrls = array(
        'prism' => $themeUrl . 'assets/vendor/prismjs/prism-tomorrow.min.css',
        'katex' => $themeUrl . 'assets/vendor/katex/katex.min.css',
        'lightbox' => $themeUrl . 'assets/vendor/lightbox3/lightbox3.min.css',
    );
    $jsUrls = array(
        'prism' => $themeUrl . 'assets/vendor/prismjs/prism.js',
        'prismAutoloader' => $themeUrl . 'assets/vendor/prismjs/plugins/autoloader/prism-autoloader.min.js',
        'lightbox' => $themeUrl . 'assets/vendor/lightbox3/lightbox3.min.js',
        'mermaid' => $themeUrl . 'assets/vendor/mermaid/mermaid.min.js',
        'echarts' => $themeUrl . 'assets/vendor/echarts/echarts.min.js',
        'katex' => $themeUrl . 'assets/vendor/katex/katex.min.js',
        'katexAutoRender' => $themeUrl . 'assets/vendor/katex/auto-render.min.js',
    );
    $prismAutoloaderPath = $themeUrl . 'assets/vendor/prismjs/components/';
}

$title = isset($payload['title']) ? (string)$payload['title'] : '';
$html = isset($payload['html']) ? (string)$payload['html'] : '';
// 旧 fields 兜底的文末引用列表（正文无 [cite: 标记时由 preview-ajax 查库渲染，
// 位置与 post.php 一致：.post-content 之后、article 之内）
$fieldsCites = isset($payload['fieldsCites']) ? (string)$payload['fieldsCites'] : '';
$siteTitle = (string)$options->title;
$pageTitle = ($title !== '' ? $title . ' - ' : '') . $siteTitle;

header('Content-Type: text/html; charset=utf-8');
// 一次性 token 页绝不能被 CDN / 浏览器缓存（否则删了文件仍返回缓存 200）
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($pageTitle); ?></title>
<link rel="stylesheet" href="<?php echo htmlspecialchars($themeUrl . 'assets/css/normalize.min.css'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars($themeUrl . 'assets/css/style.min.css'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars($themeUrl . 'assets/vendor/font-awesome/css/font-awesome.min.css'); ?>">
<style>
/* 预览页专用：去掉前台页面级 chrome（header/sidebar/footer/评论），只保留正文排版上下文 */
body { background: #fff; }
#header, .site-header, .right-sidebar, aside, footer, .site-footer,
.comments, #comments, .post-navigation, .post-copyright, .post-tags,
.ai-site-box, .back-to-top { display: none !important; }
/* 目录挂件占位：main.js 的 initTableOfContents 需要 #toc-widget/#toc-nav 存在
 * 才会给标题注入 toc-heading-N 锚点 id（与前台渲染结果逐字节一致）；
 * 挂件本身保持隐藏（JS 会重置 style.display，须 !important 压住） */
#toc-widget { display: none !important; }
.typecho-post-area, .main, .body.container { max-width: 860px; margin: 0 auto; padding: 0 16px; }
.post-content { padding-top: 24px; }
/* 预览退出提示条 */
.shufei-preview-tip {
    position: fixed; left: 50%; bottom: 16px; transform: translateX(-50%);
    background: rgba(0,0,0,.72); color: #fff; font-size: 12px;
    padding: 6px 14px; border-radius: 4px; z-index: 99999;
}
</style>
<script>
// ===== 与前台 header.php / footer.php 一致的资源变量（供 main.js 按需注入）=====
window.pjaxEnabled = false; // 预览帧禁用 pjax
window.aiSummaryStream = false;
window.codeHighlightEnabled = <?php echo $codeHlOn ? 'true' : 'false'; ?>;
window.mermaidEnabled = <?php echo $mermaidOn ? 'true' : 'false'; ?>;
window.echartsEnabled = <?php echo $echartsOn ? 'true' : 'false'; ?>;
window.katexEnabled = <?php echo $katexOn ? 'true' : 'false'; ?>;
window.vendorScripts = {
    prism: '<?php echo $codeHlOn ? htmlspecialchars($jsUrls['prism'], ENT_QUOTES) : ''; ?>',
    prismAutoloader: '<?php echo $codeHlOn ? htmlspecialchars($jsUrls['prismAutoloader'], ENT_QUOTES) : ''; ?>',
    prismAutoloaderPath: '<?php echo htmlspecialchars($prismAutoloaderPath, ENT_QUOTES); ?>',
    lightbox: '<?php echo htmlspecialchars($jsUrls['lightbox'], ENT_QUOTES); ?>',
    mermaid: '<?php echo $mermaidOn ? htmlspecialchars($jsUrls['mermaid'], ENT_QUOTES) : ''; ?>',
    echarts: '<?php echo $echartsOn ? htmlspecialchars($jsUrls['echarts'], ENT_QUOTES) : ''; ?>',
    katex: '<?php echo $katexOn ? htmlspecialchars($jsUrls['katex'], ENT_QUOTES) : ''; ?>',
    katexAutoRender: '<?php echo $katexOn ? htmlspecialchars($jsUrls['katexAutoRender'], ENT_QUOTES) : ''; ?>'
};
window.vendorCssUrls = {
    prism: '<?php echo $codeHlOn ? htmlspecialchars($cssUrls['prism'], ENT_QUOTES) : ''; ?>',
    katex: '<?php echo $katexOn ? htmlspecialchars($cssUrls['katex'], ENT_QUOTES) : ''; ?>',
    lightbox: '<?php echo htmlspecialchars($cssUrls['lightbox'], ENT_QUOTES); ?>'
};
// 预览帧标记：main.js 据此只跑正文渲染相关 init
window.__shufeiPreviewFrame = true;
window.__shufeiPreviewCid = <?php echo (int)($payload['cid'] ?? 0); ?>;
// Esc 关闭：通知父窗口（编辑器内 iframe 场景）
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && window.parent && window.parent !== window) {
        window.parent.postMessage('shufeiPreviewClose', '*');
    }
});
</script>
<script src="<?php echo htmlspecialchars($themeUrl . 'assets/js/main.min.js'); ?>" defer></script>
</head>
<body>
<div id="toc-widget" style="display:none"><nav id="toc-nav"></nav></div>
<div class="main">
    <div class="body container">
        <div class="typecho-post-area">
            <article class="post">
                <h1 class="post-title" style="font-size:24px;margin:18px 0 10px;"><?php echo htmlspecialchars($title); ?></h1>
                <div class="post-content"><?php echo $html; ?></div>
                <?php if ($fieldsCites !== ''): ?>
                <?php echo $fieldsCites; ?>
                <?php endif; ?>
            </article>
        </div>
    </div>
</div>
<div class="shufei-preview-tip">实时预览（未保存）· 按 Esc 或点击后台「退出预览」关闭</div>
</body>
