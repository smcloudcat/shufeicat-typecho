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
 * URL 校验规则已抽到共享库 theme-url-rules.php
 * （ai-settings-ajax.php 的 AI 设置助手 update 工具同样复用）
 */
require_once dirname(__FILE__) . '/theme-url-rules.php';

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

    // 多选（Checkbox）字段：全部取消勾选时浏览器不会提交该键，
    // 此处显式补空数组，避免"取消全部勾选"保存后又回退为默认值。
    // 仅当本次提交确实来自含该字段的新版表单时生效（防旧版表单误伤）。
    if (array_key_exists('listRadius', $settings)) {
        foreach (array('listMeta') as $multiField) {
            if (!array_key_exists($multiField, $settings)) {
                $settings[$multiField] = array();
            }
        }
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