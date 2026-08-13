<?php
/**
 * 密码文章/页面密码验证接口（纯主题实现）
 *
 * 密码表单不再提交给 Typecho 核心（核心在密码错误时会抛出 403 异常并显示堆栈），
 * 而是提交到本接口，由主题自行验证密码并写入密码 cookie。
 *
 * 输入（POST）：cid / password / return
 *  - 密码正确：写入 protectPassword_{cid} cookie，跳转回内容页（正常显示内容）
 *  - 密码错误：跳转回内容页并附带 pw_error=1（模板据此显示"密码错误"提示）
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    $rootDir = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
    if (file_exists($rootDir . '/config.inc.php')) {
        require_once $rootDir . '/config.inc.php';
    } else {
        exit('系统配置文件缺失');
    }
}

// 计算站点根 URL（保证 cookie 前缀与后台/核心一致）
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
        if ($scriptName) {
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
    exit('系统初始化失败');
}

require_once dirname(__FILE__) . '/password-rate-limit.php';

$cid = isset($_POST['cid']) ? intval($_POST['cid']) : 0;
$password = isset($_POST['password']) ? (string)$_POST['password'] : '';
$return = isset($_POST['return']) ? trim((string)$_POST['return']) : '';

if ($cid <= 0) {
    exit('参数错误');
}

if (!$return || !preg_match('#^https?://#i', $return)) {
    $return = $options->siteUrl;
}

// 限速：IP + CID 维度，防止无限爆破
if (!shufei_password_rate_allowed($cid)) {
    $sep = (false === strpos($return, '?')) ? '?' : '&';
    if (false === strpos($return, 'pw_error=')) {
        $return .= $sep . 'pw_error=1';
    }
    header('Location: ' . $return);
    exit;
}

// 查询内容密码（与 Typecho 核心一致，存储于 contents.password 字段）
$db = \Typecho\Db::get();
$row = $db->fetchRow($db->select('password')->from('table.contents')->where('cid = ?', $cid));
$realPassword = isset($row['password']) ? $row['password'] : '';

if ($realPassword !== '' && hash_equals($realPassword, $password)) {
    // 密码正确：写入密码 cookie（与会话 cookie 一致，行为同核心）
    \Typecho\Cookie::set('protectPassword_' . $cid, $password);
    shufei_password_rate_record($cid, true);
    header('Location: ' . $return);
    exit;
}

shufei_password_rate_record($cid, false);

// 密码错误：跳回内容页并携带错误标记
$sep = (false === strpos($return, '?')) ? '?' : '&';
if (false === strpos($return, 'pw_error=')) {
    $return .= $sep . 'pw_error=1';
}
header('Location: ' . $return);
exit;
