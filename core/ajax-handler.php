<?php
/**
 * Ajax 请求处理文件
 * 处理点赞、浏览量等异步请求
 */

// 定义常量防止直接访问
if (!defined('__TYPECHO_ROOT_DIR__')) {
    // 尝试加载 Typecho 引导文件
    $rootDir = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
    if (file_exists($rootDir . '/config.inc.php')) {
        require_once $rootDir . '/config.inc.php';
    } else {
        exit('Access denied');
    }
}

require_once dirname(__FILE__) . '/post-stats.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

header('Content-Type: application/json');

// CSRF 防护：校验 Typecho 安全 token
// token 由后端使用当前请求 URL 作为 suffix 生成，前端通过 window.csrfToken 携带
$csrfToken = isset($_POST['_']) ? $_POST['_'] : '';
$expectedToken = '';
if (class_exists('\Widget\Security')) {
    try {
        $security = \Widget\Security::alloc();
        // 使用 Referer 作为 suffix（与 Typecho protect() 一致）
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $expectedToken = $security->getToken($referer);
    } catch (\Throwable $e) {
        // 安全组件初始化失败时拒绝所有请求
        echo json_encode(array('success' => false, 'message' => '安全校验失败'));
        exit;
    }
}

if (empty($csrfToken) || empty($expectedToken) || !hash_equals($expectedToken, $csrfToken)) {
    echo json_encode(array('success' => false, 'message' => '无效的请求，请刷新页面后重试'));
    exit;
}

switch ($action) {
    case 'like':
        $cid = isset($_POST['cid']) ? intval($_POST['cid']) : 0;
        if ($cid <= 0) {
            echo json_encode(array('success' => false, 'message' => '参数错误'));
            exit;
        }
        $result = shufei_add_like($cid);
        echo json_encode($result);
        break;

    case 'view':
        $cid = isset($_POST['cid']) ? intval($_POST['cid']) : 0;
        if ($cid <= 0) {
            echo json_encode(array('success' => false, 'message' => '参数错误'));
            exit;
        }
        $views = shufei_add_view($cid);
        echo json_encode(array('success' => true, 'views' => $views));
        break;

    default:
        echo json_encode(array('success' => false, 'message' => '未知操作'));
        break;
}

exit;
