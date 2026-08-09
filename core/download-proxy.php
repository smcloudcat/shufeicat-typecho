<?php
/**
 * 图片下载代理
 * 用于灯箱"下载"按钮：跨域图片（未开启 CORS）无法由浏览器直接触发下载，
 * 本文件在服务器端中转图片内容并强制以附件形式返回，实现真正下载。
 *
 * 安全加固：
 *  - 仅允许 http/https
 *  - 拒绝本地/内网/保留地址（含重定向后的最终地址）
 *  - 只回传 image/* 内容，避免被用作内网探测
 *  - 限制响应大小，防止超大文件占用服务器资源
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

/**
 * 判断主机名是否指向本地/内网/保留地址
 * 解析失败或无法确认时保守拒绝
 */
function shufei_download_proxy_is_private_host($host)
{
    $host = strtolower(trim((string)$host));
    if ($host === '' || $host === 'localhost') {
        return true;
    }

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        // 主机本身就是 IP 字面量
        $ip = $host;
    } else {
        $ip = gethostbyname($host);
        if ($ip === '' || $ip === $host) {
            // 解析失败，无法确认公网地址，保守拒绝
            return true;
        }
    }

    $long = ip2long($ip);
    if ($long === false) {
        // 非 IPv4（如 IPv6），当前代理强制 IPv4 连接，保守拒绝
        return true;
    }

    if ($long === ip2long('127.0.0.1') || $long === ip2long('0.0.0.0')) {
        return true;
    }
    if ($long >= ip2long('10.0.0.0') && $long <= ip2long('10.255.255.255')) {
        return true;
    }
    if ($long >= ip2long('172.16.0.0') && $long <= ip2long('172.31.255.255')) {
        return true;
    }
    if ($long >= ip2long('192.168.0.0') && $long <= ip2long('192.168.255.255')) {
        return true;
    }
    return false;
}

/**
 * 校验 URL：仅允许公网 http/https
 */
function shufei_download_proxy_validate_url($url)
{
    $url = trim((string)$url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return false;
    }
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host || shufei_download_proxy_is_private_host($host)) {
        return false;
    }
    return true;
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

// 校验目标地址（拒绝非 http/https 与本地/内网地址）
if (!shufei_download_proxy_validate_url($url)) {
    shufei_download_proxy_status(400);
    exit('Bad request');
}

// 提取安全的文件名
$filename = basename((string)parse_url($url, PHP_URL_PATH));
$filename = preg_replace('/[^\w.\-]+/', '_', $filename);
if ($filename === '' || $filename === '_') {
    $filename = 'image';
}

// 仅允许图片内容，避免被当作内网探测工具
function shufei_download_proxy_is_image($contentType)
{
    $ct = strtolower(trim((string)explode(';', (string)$contentType)[0]));
    return strpos($ct, 'image/') === 0;
}

// 优先使用 cURL（连接/读超时可控，强制 IPv4 避免部分环境下 IPv6 挂起）
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    $curlOpts = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_MAXFILESIZE => 20971520, // 20MB
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_HTTPHEADER => array('User-Agent: Mozilla/5.0 (ShuFeiCat Image Download Proxy)'),
        CURLOPT_REFERER => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    );
    if (defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
        $curlOpts[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
    }
    curl_setopt_array($ch, $curlOpts);
    $data = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $effectiveUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    // 重定向后的最终地址仍需校验（防止跳转到内网）
    if ($effectiveUrl !== '' && !shufei_download_proxy_validate_url($effectiveUrl)) {
        shufei_download_proxy_status(403);
        exit('Forbidden host');
    }
    if ($status < 200 || $status >= 300 || $data === false || $data === '') {
        shufei_download_proxy_status(502);
        exit('Fetch failed');
    }
    if (!shufei_download_proxy_is_image($contentType)) {
        shufei_download_proxy_status(415);
        exit('Not an image');
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
        'max_redirects' => 3,
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
if (!shufei_download_proxy_is_image($contentType)) {
    fclose($handle);
    shufei_download_proxy_status(415);
    exit('Not an image');
}

header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

// 流式输出，避免大图占用过多内存；限制最大 20MB
$maxBytes = 20971520;
$total = 0;
while (!feof($handle) && $total < $maxBytes) {
    $chunk = fread($handle, 8192);
    if ($chunk === false) {
        break;
    }
    $total += strlen($chunk);
    echo $chunk;
}
fclose($handle);
exit;
