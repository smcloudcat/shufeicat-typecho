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
require_once dirname(__FILE__) . '/vote.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

header('Content-Type: application/json');

// CSRF 防护：基于 session 的独立 token 校验
// 原实现依赖 Widget\Security，独立访问本文件时上下文不完整会导致 token 不一致
// 改用 session token：header.php 生成写入 $_SESSION，本文件校验
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$csrfToken = isset($_POST['_']) ? $_POST['_'] : '';
$sessionKey = 'shufei_ajax_token';
$expectedToken = isset($_SESSION[$sessionKey]) ? $_SESSION[$sessionKey] : '';

// token 为空或校验失败，尝试用 Typecho 原生 Security 回退（兼容旧版）
if (empty($expectedToken)) {
    if (class_exists('\Widget\Security')) {
        try {
            $security = \Widget\Security::alloc();
            $expectedToken = $security->getToken('shufei_ajax');
        } catch (\Throwable $e) {
            $expectedToken = '';
        }
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

    case 'comment_like':
        $coid = isset($_POST['coid']) ? intval($_POST['coid']) : 0;
        if ($coid <= 0) {
            echo json_encode(array('success' => false, 'message' => '参数错误'));
            exit;
        }
        $result = shufei_add_comment_like($coid);
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

    case 'vote':
        $cid = isset($_POST['cid']) ? intval($_POST['cid']) : 0;
        $optionIndex = isset($_POST['option']) ? intval($_POST['option']) : -1;
        if ($cid <= 0) {
            echo json_encode(array('success' => false, 'message' => '参数错误'));
            exit;
        }
        $result = shufei_submit_vote($cid, $optionIndex);
        // 投票成功后返回最新统计
        if ($result['success']) {
            $config = shufei_get_vote_config($cid);
            if ($config) {
                $counts = shufei_get_vote_counts($cid, $config['options']);
                $result['counts'] = $counts['counts'];
                $result['total'] = $counts['total'];
                $result['option'] = $optionIndex;
            }
        }
        echo json_encode($result);
        break;

    case 'get_vote_results':
        $cid = isset($_POST['cid']) ? intval($_POST['cid']) : 0;
        if ($cid <= 0) {
            echo json_encode(array('success' => false, 'message' => '参数错误'));
            exit;
        }
        $config = shufei_get_vote_config($cid);
        if (!$config) {
            echo json_encode(array('success' => false, 'message' => '未开启投票'));
            exit;
        }
        $check = shufei_check_voted($cid);
        $counts = shufei_get_vote_counts($cid, $config['options']);
        $isExpired = ($config['deadline'] > 0 && time() > $config['deadline']);
        echo json_encode(array(
            'success'  => true,
            'question' => $config['question'],
            'options'  => $config['options'],
            'counts'   => $counts['counts'],
            'total'    => $counts['total'],
            'voted'    => $check['voted'],
            'option'   => $check['option'],
            'expired'  => $isExpired,
            'deadline' => $config['deadline']
        ));
        break;

    default:
        echo json_encode(array('success' => false, 'message' => '未知操作'));
        break;
}

exit;
