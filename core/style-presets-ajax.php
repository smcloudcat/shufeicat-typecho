<?php
/**
 * 样式存储（外观预设）AJAX 接口
 *
 * 支持以下 action（POST）：
 *  - list:   获取全部样式预设列表
 *  - save:   保存当前外观设置为一个新预设（name + 各字段值）
 *  - apply:  将指定预设一键应用到主题设置
 *  - delete: 删除指定预设
 *
 * 请求方式：POST
 * 返回格式：JSON
 * 鉴权方式与 style-ajax.php / update-ajax.php 一致：
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

// 载入样式预设核心逻辑
require_once dirname(__FILE__) . '/style-presets.php';

// CSRF 防护：校验 Origin/Referer 同源
require_once dirname(__FILE__) . '/admin-csrf.php';
if (!shufei_admin_csrf_verify()) {
    echo json_encode(array('success' => false, 'message' => '请求来源校验失败，请刷新页面后重试'));
    exit;
}

$action = isset($_POST['action']) ? (string) $_POST['action'] : '';

/**
 * 输出预设列表（精简字段 + 样式预览色）
 */
function shufei_style_presets_json()
{
    $out = array();
    foreach (shufei_style_presets_get_all() as $p) {
        $settings = isset($p['settings']) && is_array($p['settings']) ? $p['settings'] : array();
        $out[] = array(
            'id'         => isset($p['id']) ? $p['id'] : '',
            'name'       => isset($p['name']) ? (string) $p['name'] : '',
            'created_at' => isset($p['created_at']) ? intval($p['created_at']) : 0,
            'count'      => count($settings),
            'preview'    => array(
                'themeColor' => isset($settings['themeColor']) ? (string) $settings['themeColor'] : '',
                'background' => isset($settings['bgGradient']) ? (string) $settings['bgGradient']
                              : (isset($settings['bgColor']) ? (string) $settings['bgColor'] : ''),
            ),
        );
    }
    return $out;
}

try {
    switch ($action) {
        case 'list':
            $recommended = array();
            foreach (shufei_recommended_presets() as $rec) {
                $settings = isset($rec['settings']) && is_array($rec['settings']) ? $rec['settings'] : array();
                $recommended[] = array(
                    'id'      => isset($rec['id']) ? $rec['id'] : '',
                    'name'    => isset($rec['name']) ? (string) $rec['name'] : '',
                    'desc'    => isset($rec['desc']) ? (string) $rec['desc'] : '',
                    'image'   => isset($rec['image']) ? (string) $rec['image'] : '',
                    'preview' => array(
                        'themeColor' => isset($settings['themeColor']) ? (string) $settings['themeColor'] : '',
                        'background' => shufei_recommended_preview_bg($settings),
                    ),
                );
            }
            echo json_encode(array(
                'success'     => true,
                'presets'     => shufei_style_presets_json(),
                'recommended' => $recommended,
            ));
            break;

        case 'save':
            $name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
            if ($name === '' || mb_strlen($name, 'UTF-8') > 30) {
                echo json_encode(array('success' => false, 'message' => '样式名称不能为空且不超过30个字符'));
                break;
            }
            $settings = array();
            foreach (shufei_style_preset_fields() as $key) {
                if (isset($_POST[$key])) {
                    $settings[$key] = trim((string) $_POST[$key]);
                }
            }
            $preset = shufei_style_presets_save($name, $settings);
            echo json_encode(array(
                'success' => true,
                'message' => '样式已保存：「' . $preset['name'] . '」',
                'presets' => shufei_style_presets_json(),
            ));
            break;

        case 'apply':
            $id = isset($_POST['id']) ? (string) $_POST['id'] : '';
            if ($id === '') {
                echo json_encode(array('success' => false, 'message' => '缺少预设 ID'));
                break;
            }
            $preset = shufei_style_presets_apply($id);
            if ($preset === null) {
                echo json_encode(array('success' => false, 'message' => '未找到该样式预设，可能已被删除'));
                break;
            }
            echo json_encode(array(
                'success' => true,
                'message' => '已应用样式「' . (isset($preset['name']) ? $preset['name'] : '') . '」，请点击下方的「保存设置」按钮持久化',
                'presets' => shufei_style_presets_json(),
            ));
            break;

        case 'tip':
            $mode = isset($_POST['mode']) ? (string) $_POST['mode'] : '';
            if ($mode === 'applied' || $mode === 'dismissed') {
                shufei_style_tip_set($mode);
            }
            echo json_encode(array('success' => true));
            break;

        case 'delete':
            $id = isset($_POST['id']) ? (string) $_POST['id'] : '';
            if ($id === '') {
                echo json_encode(array('success' => false, 'message' => '缺少预设 ID'));
                break;
            }
            if (!shufei_style_presets_delete($id)) {
                echo json_encode(array('success' => false, 'message' => '未找到该样式预设'));
                break;
            }
            echo json_encode(array(
                'success' => true,
                'message' => '预设已删除',
                'presets' => shufei_style_presets_json(),
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