<?php
/**
 * 主题设置保存 AJAX 接口
 *
 * 背景：后台主题设置保存走 Typecho Widget\Themes\Edit::config()，
 * $form->validate() 任一字段失败（当前为 7 个 url 规则字段）会静默 goBack 不保存，
 * 且 AJAX 前端跟随 200 重定向误报"保存成功"，导致"设置改了不生效"。
 *
 * 本端点接管保存：
 *  - 读取表单提交的全部字段（与 getAllRequest 语义一致：缺省字段取 null）
 *  - 对 url 规则字段做与后端一致的校验；非法值先自动清洗（去引号/尖括号/控制字符/空白），
 *    清洗后合法则采用清洗值；仍非法则将原始值保留为空并返回具体字段错误
 *  - 无错误时将全部设置写为单个 JSON 选项 theme:{theme}（与核心保存格式一致）
 *  - 返回 JSON：{success:true} 或 {success:false, errors:{字段:提示}}
 *
 * 请求方式：POST
 * 鉴权方式与 style-ajax.php 一致：登录态 + administrator 权限 + Origin/Referer 同源校验
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

// 鉴权：仅管理员可用（主题设置只属于 administrator）
try {
    $user = \Widget\User::alloc();
    if (!$user->hasLogin()) {
        echo json_encode(array('success' => false, 'message' => '请先登录'));
        exit;
    }
    $group = isset($user->group) ? $user->group : '';
    if ($group !== 'administrator') {
        echo json_encode(array('success' => false, 'message' => '权限不足'));
        exit;
    }
} catch (\Throwable $e) {
    echo json_encode(array('success' => false, 'message' => '身份验证失败: ' . $e->getMessage()));
    exit;
}

// CSRF 防护：校验 Origin/Referer 同源
require_once dirname(__FILE__) . '/admin-csrf.php';
if (!shufei_admin_csrf_verify()) {
    echo json_encode(array('success' => false, 'message' => '请求来源校验失败，请刷新页面后重试'));
    exit;
}

/**
 * 与 Typecho Common::safeUrl + Validate::url 一致的 URL 校验
 * 返回 [bool 合法, string 清洗后值]
 */
function shufei_theme_save_check_url($raw)
{
    $str = (string) $raw;

    // 快速通过：合法原样返回
    if (shufei_theme_save_php_url_valid($str)) {
        return array(true, $str);
    }

    // 清洗：去除引号、尖括号、反引号、反斜杠、控制字符与首尾空白
    $cleaned = str_replace(array('"', "'", '<', '>', '`', '\\'), '', $str);
    $cleaned = preg_replace('/[\x00-\x1f\x7f]/', '', $cleaned);
    $cleaned = preg_replace('/[\x{00A0}\x{3000}]/u', '', $cleaned);
    $cleaned = trim($cleaned);

    if ($cleaned !== '' && shufei_theme_save_php_url_valid($cleaned)) {
        return array(true, $cleaned);
    }

    // 中文等非 ASCII 是 FILTER_VALIDATE_URL 的硬限制：尝试对路径/查询部分做 rawurlencode
    $ascii = shufei_theme_save_encode_nonascii($cleaned);
    if ($ascii !== '' && shufei_theme_save_php_url_valid($ascii)) {
        return array(true, $ascii);
    }

    return array(false, '');
}

/**
 * 精确复现 Typecho Validate::url 的判定
 */
function shufei_theme_save_php_url_valid($str)
{
    if ($str === '') {
        return false;
    }
    $url = shufei_theme_save_safe_url($str);
    return (bool) (filter_var($str, FILTER_VALIDATE_URL) && ($url === $str));
}

function shufei_theme_save_safe_url($url)
{
    $params = parse_url(str_replace(["\r", "\n", "\t", ' '], '', $url));

    if (isset($params['scheme'])) {
        if (!in_array($params['scheme'], array('http', 'https'))) {
            return '/';
        }
    }

    $params = array_map(function ($string) {
        $string = str_replace(array('%0d', '%0a'), '', strip_tags($string));
        $string = preg_replace(
            array("/\(\s*([\"'])/i", "/([\"'])\s*\)/i"),
            '',
            $string
        );
        $string = str_replace(array('"', "'", '<', '>'), '', $string);
        return $string;
    }, $params);

    return shufei_theme_save_build_url($params);
}

function shufei_theme_save_build_url(array $params)
{
    return (isset($params['scheme']) ? $params['scheme'] . '://' : null)
        . (isset($params['user']) ? $params['user']
            . (isset($params['pass']) ? ':' . $params['pass'] : null) . '@' : null)
        . (isset($params['host']) ? $params['host'] : null)
        . (isset($params['port']) ? ':' . $params['port'] : null)
        . (isset($params['path']) ? $params['path'] : null)
        . (isset($params['query']) ? '?' . $params['query'] : null)
        . (isset($params['fragment']) ? '#' . $params['fragment'] : null);
}

/**
 * 将主机名之外的部分（路径/查询/锚点）中的非 ASCII 字符 percent-encode，
 * 以兼容 PHP FILTER_VALIDATE_URL 的 ASCII 限制
 */
function shufei_theme_save_encode_nonascii($url)
{
    $parts = parse_url($url);
    if (!isset($parts['scheme'], $parts['host'])) {
        return '';
    }
    $host = $parts['host'];
    // 主机含非 ASCII（如中文域名）时转 punycode 不可行，直接判失败
    if (preg_match('/[^\x20-\x7e]/', $host)) {
        return '';
    }
    $rebuilt = $parts['scheme'] . '://';
    if (isset($parts['user'])) {
        $rebuilt = rawurlencode($parts['user']);
        $host = $rebuilt . (isset($parts['pass']) ? ':' . rawurlencode($parts['pass']) : '') . '@' . $host;
    }
    $out = $parts['scheme'] . '://' . $host;
    if (isset($parts['port'])) {
        $out .= ':' . $parts['port'];
    }
    foreach (array('path', 'query', 'fragment') as $part) {
        if (!isset($parts[$part])) {
            continue;
        }
        $enc = preg_replace_callback('/[^\x21-\x7e]+/u', function ($m) {
            return rawurlencode($m[0]);
        }, $parts[$part]);
        $out .= ($part === 'path' ? $enc : ($part === 'query' ? '?' . $enc : '#' . $enc));
    }
    return $out;
}

/**
 * 需要校验 URL 的字段及其展示名
 */
function shufei_theme_save_url_fields()
{
    return array(
        'logoUrl'           => 'Logo 图片地址',
        'shufeiUpdateApiUrl' => '更新接口地址',
        'footerBeianLink'   => '备案链接',
        'footerGonganLink'  => '公安备案链接',
        'bgImage'           => '背景图片',
        'customCdn'         => '自定义 CDN 地址',
        'seoOgImage'        => 'OG 分享图',
    );
}

try {
    $theme = $options->theme;
    $themeKey = 'theme:' . $theme;

    // 收集全部 POST 字段（与核心 getAllRequest 语义一致：以提交为准）
    $settings = array();
    foreach ($_POST as $k => $v) {
        if ($k === 'action' || $k === '_') {
            continue;
        }
        if (is_array($v)) {
            $settings[$k] = array_map('strval', $v);
        } else {
            $settings[$k] = (string) $v;
        }
    }

    if (empty($settings)) {
        echo json_encode(array('success' => false, 'message' => '未收到任何设置数据'));
        exit;
    }

    // URL 字段校验 + 修复式清洗
    $errors = array();
    $cleanedFields = array();
    foreach (shufei_theme_save_url_fields() as $field => $label) {
        if (!array_key_exists($field, $settings)) {
            continue;
        }
        $val = is_string($settings[$field]) ? trim($settings[$field]) : '';
        if ($val === '') {
            continue; // 空值跳过（与核心 url 规则一致）
        }
        list($ok, $fixed) = shufei_theme_save_check_url($val);
        if ($ok) {
            if ($fixed !== $val) {
                $settings[$field] = $fixed;
                $cleanedFields[] = $label;
            }
        } else {
            $errors[$field] = $label . ' 的值不是合法的 http(s) 地址，已忽略该字段（其余设置正常保存）';
            // 非法值不落库：保留旧值（删除本次提交中的该字段）
            unset($settings[$field]);
        }
    }

    // 写入 theme:{theme}（与核心保存格式一致）
    $db = \Typecho\Db::get();
    $value = json_encode($settings);
    $row = $db->fetchRow($db->select('name')->from('table.options')->where('name = ?', $themeKey));
    if ($row) {
        $db->query($db->update('table.options')->rows(array('value' => $value))->where('name = ?', $themeKey));
    } else {
        $db->query($db->insert('table.options')->rows(array(
            'name'  => $themeKey,
            'user'  => 0,
            'value' => $value,
        )));
    }

    $message = '保存成功';
    if (!empty($cleanedFields)) {
        $message .= '（已自动修正：' . implode('、', $cleanedFields) . '）';
    }
    if (!empty($errors)) {
        $message .= '；' . count($errors) . ' 个字段值非法已忽略，请检查后重新填写';
    }

    echo json_encode(array(
        'success' => true,
        'message' => $message,
        'errors'  => $errors,
        'cleaned' => $cleanedFields,
    ));
} catch (\Throwable $e) {
    echo json_encode(array('success' => false, 'message' => '保存失败: ' . $e->getMessage()));
    exit;
}