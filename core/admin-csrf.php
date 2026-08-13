<?php
/**
 * 后台 AJAX 入口统一 CSRF 防护
 *
 * 这些端点（storage-ajax / mail-test-ajax / style-ajax / update-ajax / ai-writer-ajax）
 * 由浏览器以 same-origin fetch/XHR 调用，配合登录态 + 权限组校验。
 *
 * 为防止跨站请求伪造（CSRF），校验请求的 Origin / Referer 是否指向本站：
 * 浏览器对跨站 POST 会携带攻击者域名的 Origin，或发送攻击者页面的 Referer，
 * 因此同源校验可有效拦截 CSRF；仅当两者都缺失（极老客户端）时才放行，由登录态兜底。
 */
if (!defined('__TYPECHO_ROOT_DIR__') && !defined('SHUFEI_ADMIN_CSRF_OK')) exit;

function shufei_admin_site_hosts()
{
    $hosts = array();
    try {
        $options = \Widget\Options::alloc();
        if (!empty($options->siteUrl)) {
            $h = parse_url($options->siteUrl, PHP_URL_HOST);
            if ($h) $hosts[] = strtolower((string)$h);
        }
    } catch (\Throwable $e) {
    }
    if (isset($_SERVER['HTTP_HOST'])) {
        $hosts[] = strtolower((string)$_SERVER['HTTP_HOST']);
    }
    return array_values(array_unique($hosts));
}

function shufei_admin_host_matches($host, $allowedHosts)
{
    $host = strtolower(trim((string)$host));
    if ($host === '') return false;
    foreach ($allowedHosts as $allowed) {
        if ($host === $allowed) return true;
        // 允许子域：如 cdn.lwcat.cn 匹配 *.lwcat.cn
        if (strpos($allowed, '.') !== false && substr($host, -strlen('.' . $allowed)) === '.' . $allowed) {
            return true;
        }
    }
    return false;
}

/**
 * 校验 Origin / Referer 是否指向本站
 *
 * @return bool true=通过，false=疑似 CSRF
 */
function shufei_admin_csrf_verify()
{
    $hosts = shufei_admin_site_hosts();
    if (empty($hosts)) return true;

    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $origin = (string)$_SERVER['HTTP_ORIGIN'];
        if ($origin === '' || strtolower($origin) === 'null') {
            // Origin 为 "null"（沙箱 iframe / 部分跨站场景）→ 拒绝
            return false;
        }
        $oh = parse_url($origin, PHP_URL_HOST);
        return ($oh !== false && $oh !== null && shufei_admin_host_matches($oh, $hosts));
    }

    if (isset($_SERVER['HTTP_REFERER'])) {
        $referer = (string)$_SERVER['HTTP_REFERER'];
        if ($referer !== '') {
            $rh = parse_url($referer, PHP_URL_HOST);
            return ($rh !== false && $rh !== null && shufei_admin_host_matches($rh, $hosts));
        }
    }

    // 无 Origin 也无 Referer：放行，由登录态 + SameSite Cookie 兜底
    return true;
}
