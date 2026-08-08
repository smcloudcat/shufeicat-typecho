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

// 计算站点根 URL（与后台/核心保持一致），保证 Cookie 前缀一致
// 直接访问本文件时 Request::getRequestRoot() 会基于脚本路径动态计算（得到 /usr/themes/ShuFeiCat/core），
// 与页面渲染上下文（站点根）不一致，会导致密码 cookie 写入的 key 与页面读取的 key 不同；
// 故显式定义 __TYPECHO_ROOT_URL__ 走 Options::___rootUrl() 的常量分支
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

    case 'password_verify':
        // 密码文章/页面验证（AJAX 版，替代核心 POST 流程，避免密码错误时核心抛 403 异常）
        $cid = isset($_POST['cid']) ? intval($_POST['cid']) : 0;
        $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
        if ($cid <= 0) {
            echo json_encode(array('success' => false, 'message' => '参数错误'));
            exit;
        }

        // 初始化 Cookie 前缀（与核心一致），验证通过后写入密码 cookie
        $options = \Typecho\Widget::widget('Widget_Options');
        \Typecho\Cookie::setPrefix($options->rootUrl);

        $db = \Typecho\Db::get();
        $row = $db->fetchRow($db->select('password')->from('table.contents')->where('cid = ?', $cid));
        $realPassword = isset($row['password']) ? $row['password'] : '';

        if ($realPassword !== '' && hash_equals($realPassword, $password)) {
            // 密码正确：写入密码 cookie（与会话 cookie 一致），前端再 Pjax/跳转刷新内容
            \Typecho\Cookie::set('protectPassword_' . $cid, $password);
            echo json_encode(array('success' => true));
        } else {
            echo json_encode(array('success' => false, 'message' => '密码错误，请重新输入'));
        }
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
