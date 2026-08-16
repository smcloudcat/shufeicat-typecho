<?php
/**
 * AI写作助手 AJAX 接口
 *
 * 处理后台文章编辑器的 AI 美化、续写、检查请求
 * 仅允许已登录的管理员/编辑使用
 *
 * 注意：必须手动调用 Cookie::setPrefix() 初始化 Cookie 前缀，
 * 否则 Widget_User 无法正确读取带前缀的 __typecho_uid 等认证 Cookie
 */

// 输出 JSON 头
header('Content-Type: application/json; charset=utf-8');

// 载入 Typecho 配置（定义 __TYPECHO_ROOT_DIR__、自动加载、数据库连接）
if (!defined('__TYPECHO_ROOT_DIR__')) {
    $rootDir = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
    if (file_exists($rootDir . '/config.inc.php')) {
        require_once $rootDir . '/config.inc.php';
    } else {
        echo json_encode(array('success' => false, 'message' => '系统配置文件缺失'));
        exit;
    }
}

/**
 * 关键修复：手动定义 __TYPECHO_ROOT_URL__
 *
 * Cookie 前缀由 md5(rootUrl) 计算得到。在后台页面（/admin/*）中，
 * rootUrl 会去掉 /admin 后缀，得到站点根 URL（如 https://example.com）。
 * 但本端点位于 /usr/themes/ShuFeiCat/core/，getRequestRoot() 会返回
 * 该子目录路径，导致 Cookie 前缀与后台不一致，无法读取认证 Cookie。
 *
 * 通过预先定义 __TYPECHO_ROOT_URL__ 常量，强制使用站点根 URL，
 * 使 Cookie 前缀与后台登录时一致。
 */
if (!defined('__TYPECHO_ROOT_URL__')) {
    if (defined('__TYPECHO_SITE_URL__')) {
        // 最可靠：使用 config.inc.php 中配置的站点 URL，不依赖请求路径或主题目录名
        define('__TYPECHO_ROOT_URL__', __TYPECHO_SITE_URL__);
    } else {
        // 兜底：从请求中推导，通过 __TYPECHO_ROOT_DIR__ + __FILE__ 计算相对路径
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
    // 加载全局选项（连接数据库读取站点配置）
    $options = \Widget\Options::alloc();
    // 手动设置 Cookie 前缀，使 Widget\User 能读取带前缀的认证 Cookie
    // __TYPECHO_ROOT_URL__ 已在上面定义，rootUrl 会使用它，确保与管理后台一致
    \Typecho\Cookie::setPrefix($options->rootUrl);
} catch (\Throwable $e) {
    echo json_encode(array('success' => false, 'message' => '系统初始化失败: ' . $e->getMessage()));
    exit;
}

// 鉴权：仅允许已登录用户使用 AI 写作
try {
    $user = \Widget\User::alloc();
    if (!$user->hasLogin()) {
        echo json_encode(array('success' => false, 'message' => '请先登录后再使用 AI 助手'));
        exit;
    }
    // 仅允许 administrator / editor 使用（贡献者/订阅者无权编辑文章）
    $group = $user->group ?? '';
    if (!in_array($group, array('administrator', 'editor'), true)) {
        echo json_encode(array('success' => false, 'message' => '权限不足，仅管理员或编辑可使用 AI 写作助手'));
        exit;
    }
} catch (\Throwable $e) {
    echo json_encode(array('success' => false, 'message' => '身份验证失败: ' . $e->getMessage()));
    exit;
}

// 载入 AI 写作核心类
require_once dirname(__FILE__) . '/ai-writer.php';
require_once dirname(__FILE__) . '/ai-moderation.php';
require_once dirname(__FILE__) . '/ai-provider.php';

// CSRF 防护：校验 Origin/Referer 同源，防止跨站请求伪造（test_api 会向任意地址发起请求）
require_once dirname(__FILE__) . '/admin-csrf.php';
if (!shufei_admin_csrf_verify()) {
    echo json_encode(array('success' => false, 'message' => '请求来源校验失败，请刷新页面后重试'));
    exit;
}

// 获取请求参数
$action = isset($_POST['action']) ? $_POST['action'] : '';
$content = isset($_POST['content']) ? $_POST['content'] : '';

// 内容长度限制（防止超长内容导致 API 超时或费用过高）
$maxLen = 8000;
if (mb_strlen($content, 'UTF-8') > $maxLen) {
    echo json_encode(array('success' => false, 'message' => '内容过长（超过' . $maxLen . '字），请缩短后重试'));
    exit;
}

// 测试类操作：不需要 AI 写作已开启/已配置，允许在保存前测试任意接口
if ($action === 'test_api') {
    // 测试任意接口配置（供后台设置页即时测试，无需先保存）
    $testProvider = isset($_POST['provider']) ? trim($_POST['provider']) : 'custom_chat';
    $testUrl = isset($_POST['api_url']) ? trim($_POST['api_url']) : '';
    $testKey = isset($_POST['api_key']) ? trim($_POST['api_key']) : '';
    $testModel = isset($_POST['model']) ? trim($_POST['model']) : 'gpt-3.5-turbo';
    echo json_encode(shufei_test_ai_api($testUrl, $testKey, $testModel, $testProvider), JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取模型列表（供后台设置页拉取可用模型）
if ($action === 'fetch_models') {
    $testProvider = isset($_POST['provider']) ? trim($_POST['provider']) : 'custom_chat';
    $testUrl = isset($_POST['api_url']) ? trim($_POST['api_url']) : '';
    $testKey = isset($_POST['api_key']) ? trim($_POST['api_key']) : '';
    $testModel = isset($_POST['model']) ? trim($_POST['model']) : '';
    $provider = new AiProvider($testProvider, $testUrl, $testKey, $testModel, 30);
    echo json_encode($provider->fetchModels(), JSON_UNESCAPED_UNICODE);
    exit;
}

$writer = new AiWriter();

// 健康检查：测试已保存的写作接口，需要 AI 写作已开启
if ($action === 'health_check') {
    if (!$writer->isEnabled()) {
        echo json_encode(array('success' => false, 'message' => 'AI写作助手未开启，请在后台「AI助手」设置中开启'));
        exit;
    }
    echo json_encode($writer->checkApiHealth(), JSON_UNESCAPED_UNICODE);
    exit;
}

// 检查 AI 写作是否启用
if (!$writer->isEnabled()) {
    echo json_encode(array('success' => false, 'message' => 'AI写作助手未开启，请在后台「AI助手」设置中开启'));
    exit;
}

// 检查接口是否已配置
if (!$writer->isConfigured()) {
    echo json_encode(array('success' => false, 'message' => 'AI接口未配置，请在后台「AI助手」设置中填写 API 地址和密钥'));
    exit;
}

// 分发操作
switch ($action) {
    case 'beautify':
        $style = isset($_POST['style']) ? $_POST['style'] : 'literary';
        if (!isset(AiWriter::$beautifyStyles[$style])) {
            echo json_encode(array('success' => false, 'message' => '不支持的美化风格'));
            exit;
        }
        $result = $writer->beautify($content, $style);
        break;

    case 'continue':
        $length = isset($_POST['length']) ? intval($_POST['length']) : 300;
        $result = $writer->continueWriting($content, $length);
        break;

    case 'check':
        $result = $writer->check($content);
        break;

    default:
        $result = array('success' => false, 'message' => '未知操作: ' . htmlspecialchars($action));
        break;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
exit;

/**
 * 测试 AI API 接口连通性
 * 直接使用传入的参数测试，无需保存配置
 *
 * @param string $apiUrl API 地址
 * @param string $apiKey API 密钥
 * @param string $model 模型名称
 * @param string $provider 提供商类型（custom_chat/custom_responses/deepseek/openai/free）
 * @return array
 */
function shufei_test_ai_api($apiUrl, $apiKey, $model, $provider = 'custom_chat')
{
    $result = array(
        'success' => false,
        'message' => '',
        'latency' => 0,
    );

    // 免费接口：使用内置配置
    if ($provider === 'free' && class_exists('AiModeration')) {
        $apiUrl = AiModeration::FREE_API_URL;
        $apiKey = AiModeration::FREE_API_KEY;
        $model = AiModeration::FREE_API_MODEL;
    }

    $aiProvider = new AiProvider($provider, $apiUrl, $apiKey, $model, 15);
    if (!$aiProvider->isConfigured()) {
        $result['message'] = 'API 地址或密钥为空';
        return $result;
    }

    $startTime = microtime(true);
    $resp = $aiProvider->complete('', '你好', 0.5, 20);
    $result['latency'] = round((microtime(true) - $startTime) * 1000, 2);

    if ($resp['success']) {
        $result['success'] = true;
        $result['message'] = '✓ 接口连通正常 (响应时间: ' . $result['latency'] . 'ms)';
    } else {
        $result['message'] = $resp['message'];
    }

    return $result;
}
