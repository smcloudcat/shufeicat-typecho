<?php
/**
 * 图片下载代理
 * 用于灯箱"下载"按钮：跨域图片（未开启 CORS）无法由浏览器直接触发下载，
 * 本文件在服务器端中转图片内容并强制以附件形式返回，实现真正下载。
 */

// 定义常量防止直接访问
if (!defined('__TYPECHO_ROOT_DIR__')) {
    $rootDir = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
    if (file_exists($rootDir . '/config.inc.php')) {
        require_once $rootDir . '/config.inc.php';
    } else {
        header('HTTP/1.1 403 Forbidden');
        exit('Access denied');
    }
}

$url = isset($_GET['url']) ? trim((string)$_GET['url']) : '';
$csrfToken = isset($_GET['_']) ? (string)$_GET['_'] : '';

// 错误状态码需写入 Typecho Response 单例，否则冲刷时会被其默认 200 覆盖
function shufei_download_proxy_status($code)
{
    if (class_exists('\\Typecho\\Response')) {
        \Typecho\Response::getInstance()->setStatus((int)$code);
    } else {
        http_response_code((int)$code);
    }
}

// CSRF 校验：与 ajax-handler.php 一致，基于 session token
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$expectedToken = isset($_SESSION['shufei_ajax_token']) ? $_SESSION['shufei_ajax_token'] : '';
if (empty($csrfToken) || empty($expectedToken) || !hash_equals($expectedToken, $csrfToken)) {
    shufei_download_proxy_status(403);
    exit('Forbidden');
}

// 仅允许 http/https
if ($url === '' || !preg_match('#^https?://#i', $url)) {
    shufei_download_proxy_status(400);
    exit('Bad request');
}

// 简易 SSRF 防护：拒绝本地/内网地址
$host = parse_url($url, PHP_URL_HOST);
if ($host) {
    $hostLower = strtolower($host);
    $ip = gethostbyname($hostLower);
    $long = ip2long($ip);
    $isPrivate = ($long !== false && (
        $hostLower === 'localhost'
        || $long === ip2long('127.0.0.1')
        || $long === ip2long('0.0.0.0')
        || ($long >= ip2long('10.0.0.0') && $long <= ip2long('10.255.255.255'))
        || ($long >= ip2long('172.16.0.0') && $long <= ip2long('172.31.255.255'))
        || ($long >= ip2long('192.168.0.0') && $long <= ip2long('192.168.255.255'))
    ));
    if ($isPrivate) {
        shufei_download_proxy_status(403);
        exit('Forbidden host');
    }
}

// 提取安全的文件名
$filename = basename((string)parse_url($url, PHP_URL_PATH));
$filename = preg_replace('/[^\w.\-]+/', '_', $filename);
if ($filename === '' || $filename === '_') {
    $filename = 'image';
}

// 优先使用 cURL（连接/读超时可控，强制 IPv4 避免部分环境下 IPv6 挂起）
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_HTTPHEADER => array('User-Agent: Mozilla/5.0 (ShuFeiCat Image Download Proxy)'),
        CURLOPT_REFERER => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ));
    $data = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($status < 200 || $status >= 300 || $data === false || $data === '') {
        shufei_download_proxy_status(502);
        exit('Fetch failed');
    }
    if (strpos($contentType, 'image/') !== 0) {
        $contentType = 'application/octet-stream';
    }
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . strlen($data));
    echo $data;
    exit;
}

// cURL 不可用时回退到 fopen
@ini_set('default_socket_timeout', 8);
$context = stream_context_create(array(
    'http' => array(
        'timeout' => 8,
        'follow_location' => 1,
        'ignore_errors' => true,
        'user_agent' => 'Mozilla/5.0 (ShuFeiCat Image Download Proxy)',
        'header' => 'Referer: ' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '') . "\r\n"
    ),
    'ssl' => array('verify_peer' => false, 'verify_peer_name' => false)
));

$handle = @fopen($url, 'rb', false, $context);

if (!$handle) {
    shufei_download_proxy_status(502);
    exit('Fetch failed');
}

// 解析响应头，获取 Content-Type
$contentType = '';
$meta = stream_get_meta_data($handle);
if (isset($meta['wrapper_data']) && is_array($meta['wrapper_data'])) {
    foreach ($meta['wrapper_data'] as $h) {
        if (stripos($h, 'Content-Type:') === 0) {
            $contentType = trim(substr($h, 13));
            break;
        }
    }
}
if ($contentType === '' || strpos($contentType, 'image/') !== 0) {
    $contentType = 'application/octet-stream';
}

header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

// 流式输出，避免大图占用过多内存
while (!feof($handle)) {
    $chunk = fread($handle, 8192);
    if ($chunk === false) {
        break;
    }
    echo $chunk;
}
fclose($handle);
exit;
