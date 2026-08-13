<?php
/**
 * 推荐设置提醒 AJAX 接口
 *
 * 支持以下 action（POST）：
 *  - apply:   立即设置（应用推荐设置并记录样式版本号）
 *  - dismiss: 不再提醒（仅记录样式版本号，不应用设置）
 *
 * 请求方式：POST
 * 返回格式：JSON
 * 鉴权方式与 update-ajax.php / storage-ajax.php 一致：
 * 手动初始化 Cookie 前缀以读取后台登录 Cookie，仅允许已登录的 administrator / editor。
 */

header('Content-Type: application/json; charset=utf-8');

// 全局 fatal error handler
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
    $options = \Widget\Options::alloc();
    \Typecho\Cookie::setPrefix($options->rootUrl);
} catch (\Throwable $e) {
    echo json_encode(array('success' => false, 'message' => '系统初始化失败: ' . $e->getMessage()));
    exit;
}

// 鉴权：仅管理员/编辑可用
try {
    $user = \Widget\User::alloc();
    if (!$user->hasLogin()) {
        echo json_encode(array('success' => false, 'message' => '请先登录'));
        exit;
    }
    $group = $user->group ?? '';
    if (!in_array($group, array('administrator', 'editor'), true)) {
        echo json_encode(array('success' => false, 'message' => '权限不足'));
        exit;
    }
} catch (\Throwable $e) {
    echo json_encode(array('success' => false, 'message' => '身份验证失败: ' . $e->getMessage()));
    exit;
}

// 载入样式版本模块
require_once dirname(__FILE__) . '/style-version.php';

// CSRF 防护：校验 Origin/Referer 同源，防止跨站请求伪造
require_once dirname(__FILE__) . '/admin-csrf.php';
if (!shufei_admin_csrf_verify()) {
    echo json_encode(array('success' => false, 'message' => '请求来源校验失败，请刷新页面后重试'));
    exit;
}

$action = isset($_POST['action']) ? (string) $_POST['action'] : '';

try {
    switch ($action) {
        case 'apply':
            $settings = shufei_apply_recommended_settings();
            echo json_encode(array(
                'success'  => true,
                'message'  => '已应用推荐设置',
                'version'  => shufei_get_style_version(),
                'applied'  => array_keys(shufei_style_recommendations()),
                'settings' => $settings,
            ));
            break;

        case 'dismiss':
            $recorded = shufei_record_style_version();
            if (!$recorded) {
                echo json_encode(array('success' => false, 'message' => '记录失败，请稍后重试'));
                break;
            }
            echo json_encode(array(
                'success' => true,
                'message' => '已不再提醒',
                'version' => shufei_get_style_version(),
            ));
            break;

        default:
            echo json_encode(array('success' => false, 'message' => '未知操作'));
            break;
    }
} catch (\Throwable $e) {
    echo json_encode(array('success' => false, 'message' => '操作失败: ' . $e->getMessage()));
    exit;
}
