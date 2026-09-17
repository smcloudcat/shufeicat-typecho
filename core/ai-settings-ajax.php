<?php
/**
 * 后台 AI 设置助手 AJAX 接口
 *
 * 管理员在主题外观设置页通过对话让 AI 读取/修改主题设置。
 *
 * 工具（代码层强制安全边界）：
 *  - list_theme_settings   读取当前全部「非敏感」设置项与值（值超长自动截断）
 *  - update_theme_settings 修改设置，updates 为 JSON 对象 {"字段名": "新值"}
 *
 * 安全设计：
 *  - 鉴权：登录 + administrator + Origin/Referer 同源校验（与 theme-save-ajax 一致）
 *  - 敏感字段黑名单：字段名含 password/secret/key/token/appid/mailuser 的设置
 *    对 AI 完全不可见、不可修改（读和写都被拒绝），密钥类信息永不进入对话上下文
 *  - 值校验：字符串 ≤3000 字符；数组每项 ≤500 字符；URL 字段复用 theme-url-rules 校验，
 *    非法值拒绝写入并告知 AI
 *  - 写入方式与 theme-save-ajax 相同：读 theme:{theme} JSON → 合并 → 写回（保留全部字段）
 *  - 限速：60 秒最多 30 次真实 AI 调用（防 session 被盗后刷接口）
 *  - 对话历史由前端携带，服务端复用 AiChat::sanitizeHistory 白名单清洗
 *
 * 请求：POST message / history(JSON) / stream 固定为普通 JSON 响应
 * 响应：{success, reply, actions:[前端展示的修改摘要]}
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

// 鉴权：仅管理员可用
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

// 复用前台对话的历史清洗 + Provider 选择逻辑
require_once dirname(__FILE__) . '/ai-chat.php';
// URL 字段校验（与 theme-save-ajax 共用同一套规则）
require_once dirname(__FILE__) . '/theme-url-rules.php';

/* ================================================================
 * 工具实现
 * ================================================================ */

const SHUFEI_AISET_MAX_TOOL_ROUNDS = 3;
const SHUFEI_AISET_MAX_TOOL_RESULT = 4000;
const SHUFEI_AISET_CHAT_MAX_TOKENS = 900;
const SHUFEI_AISET_RATE_WINDOW = 60;
const SHUFEI_AISET_RATE_MAX = 30;
const SHUFEI_AISET_VALUE_MAX = 3000;
const SHUFEI_AISET_UPDATES_MAX = 30;

/**
 * 敏感字段黑名单：字段名（小写）包含以下任一子串即对 AI 隐藏
 * 覆盖：AI API Key、SMTP 密码/账号、验证码密钥、更新 token 等
 */
function shufei_ai_set_sensitive_fields()
{
    return array('password', 'secret', 'key', 'token', 'appid', 'mailuser');
}

function shufei_ai_set_is_sensitive($field)
{
    $lower = strtolower(trim((string)$field));
    foreach (shufei_ai_set_sensitive_fields() as $needle) {
        if ($needle !== '' && strpos($lower, $needle) !== false) {
            return true;
        }
    }
    return false;
}

/** 读取当前主题设置（theme:{theme} JSON），失败返回空数组 */
function shufei_ai_set_read_theme_options()
{
    $options = \Widget\Options::alloc();
    $themeKey = 'theme:' . $options->theme;
    $db = \Typecho\Db::get();
    $row = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', $themeKey));
    if (!$row) {
        return array();
    }
    $data = json_decode((string)$row['value'], true);
    return is_array($data) ? $data : array();
}

/** 写回主题设置 JSON（与 theme-save-ajax 一致的存储格式） */
function shufei_ai_set_write_theme_options($settings)
{
    $options = \Widget\Options::alloc();
    $themeKey = 'theme:' . $options->theme;
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
}

/** 工具：列出全部非敏感设置项 */
function shufei_ai_set_tool_list()
{
    $settings = shufei_ai_set_read_theme_options();
    $out = array();
    foreach ($settings as $field => $val) {
        if (shufei_ai_set_is_sensitive($field)) {
            continue; // 敏感字段对 AI 完全不可见
        }
        if (is_array($val)) {
            $val = array_map('strval', $val);
            $joined = implode(' | ', array_slice($val, 0, 10));
            $out[$field] = mb_substr($joined, 0, 500, 'UTF-8');
        } else {
            $out[$field] = mb_substr((string)$val, 0, 500, 'UTF-8');
        }
    }
    return array(
        'total'  => count($out),
        'note'   => '值为空字符串表示未设置。值为 "…" 结尾表示已截断。密钥/密码类字段不在此列。',
        'fields' => $out,
    );
}

/**
 * 工具：更新设置
 * 返回 ['ok'=>bool, 'updated'=>[字段...], 'errors'=>{字段:原因}]
 */
function shufei_ai_set_tool_update($argsJson)
{
    $args = json_decode((string)$argsJson, true);
    $updates = (is_array($args) && isset($args['updates']) && is_array($args['updates'])) ? $args['updates'] : array();
    if (empty($updates)) {
        return array('ok' => false, 'error' => '缺少 updates 参数（JSON 对象，如 {"primaryColor": "#FF6B6B"}）');
    }
    if (count($updates) > SHUFEI_AISET_UPDATES_MAX) {
        return array('ok' => false, 'error' => '单次最多修改 ' . SHUFEI_AISET_UPDATES_MAX . ' 个字段');
    }

    $urlFields = shufei_theme_save_url_fields();
    $settings = shufei_ai_set_read_theme_options();

    $updated = array();
    $errors = array();

    foreach ($updates as $field => $val) {
        $field = trim((string)$field);
        if ($field === '' || strlen($field) > 64 || !preg_match('/^[A-Za-z0-9_\-]+$/', $field)) {
            $errors[(string)$field] = '字段名非法';
            continue;
        }
        if (shufei_ai_set_is_sensitive($field)) {
            // 敏感字段一律拒绝：即使猜中名字也不可写
            $errors[$field] = '该字段包含密钥/密码等敏感信息，不允许通过 AI 修改，请在表单中手动填写';
            continue;
        }

        // 值规范化：仅允许字符串或字符串数组
        if (is_array($val)) {
            $val = array_map('strval', array_slice($val, 0, 20));
            foreach ($val as $i => $item) {
                if (mb_strlen($item, 'UTF-8') > 500) {
                    $val[$i] = mb_substr($item, 0, 500, 'UTF-8');
                }
            }
        } else {
            $val = (string)$val;
            if (mb_strlen($val, 'UTF-8') > SHUFEI_AISET_VALUE_MAX) {
                $val = mb_substr($val, 0, SHUFEI_AISET_VALUE_MAX, 'UTF-8');
            }
        }

        // URL 字段校验（复用 theme-save-ajax 的规则）：非法拒绝写入
        if (isset($urlFields[$field])) {
            $valStr = is_array($val) ? '' : trim($val);
            if ($valStr !== '') {
                list($ok, $fixed) = shufei_theme_save_check_url($valStr);
                if (!$ok) {
                    $errors[$field] = $urlFields[$field] . ' 的值不是合法的 http(s) 地址，未写入';
                    continue;
                }
                $val = $fixed;
            }
        }

        $settings[$field] = $val;
        $updated[] = $field;
    }

    if (empty($updated) && empty($errors)) {
        return array('ok' => false, 'error' => '没有可写入的修改');
    }

    if (!empty($updated)) {
        shufei_ai_set_write_theme_options($settings);
    }

    return array(
        'ok'      => true,
        'updated' => $updated,
        'errors'  => (object)$errors,
        'note'    => '设置已保存生效；设置页表单需刷新后才会显示新值',
    );
}

/** 工具定义（OpenAI Function Calling 格式） */
function shufei_ai_set_tool_definitions()
{
    return array(
        array(
            'type' => 'function',
            'function' => array(
                'name' => 'list_theme_settings',
                'description' => '读取主题当前全部可修改的设置项与值（密钥/密码类敏感字段不会出现）。修改设置前务必先调用此工具确认字段名。',
                'parameters' => array('type' => 'object', 'properties' => array()),
            ),
        ),
        array(
            'type' => 'function',
            'function' => array(
                'name' => 'update_theme_settings',
                'description' => '修改主题设置并立即保存生效。参数 updates 是 JSON 对象，键为字段名（来自 list_theme_settings），值为新字符串。',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(
                        'updates' => array(
                            'type' => 'object',
                            'description' => '要修改的字段与新值，如 {"primaryColor": "#FF6B6B", "footerCustomText": "欢迎常来"}',
                        ),
                    ),
                    'required' => array('updates'),
                ),
            ),
        ),
    );
}

/** 执行工具 */
function shufei_ai_set_execute_tool($name, $argsJson, &$actions)
{
    switch ($name) {
        case 'list_theme_settings':
            $result = shufei_ai_set_tool_list();
            break;
        case 'update_theme_settings':
            $result = shufei_ai_set_tool_update($argsJson);
            if (!empty($result['ok']) && !empty($result['updated'])) {
                $actions[] = '已修改 ' . count($result['updated']) . ' 项：' . implode('、', $result['updated']);
                if (!empty($result['errors'])) {
                    foreach ((array)$result['errors'] as $ef => $emsg) {
                        $actions[] = '未写入 ' . $ef . '：' . $emsg;
                    }
                }
            }
            break;
        default:
            $result = array('error' => '未知工具');
    }

    $json = json_encode($result, JSON_UNESCAPED_UNICODE);
    if (mb_strlen($json, 'UTF-8') > SHUFEI_AISET_MAX_TOOL_RESULT) {
        $json = mb_substr($json, 0, SHUFEI_AISET_MAX_TOOL_RESULT, 'UTF-8') . '……(结果已截断)';
    }
    return $json;
}

/** 限速（复用前台模式，独立 key） */
function shufei_ai_set_rate_key_file()
{
    $ip = isset($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)
        ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    $key = hash('sha256', 'ai_set|' . $ip);
    return dirname(__FILE__) . '/cache/ai_settings_rate_' . $key . '.json';
}

function shufei_ai_set_rate_allowed()
{
    $file = shufei_ai_set_rate_key_file();
    if (!is_file($file)) {
        return true;
    }
    $data = json_decode((string)@file_get_contents($file), true);
    $times = (is_array($data) && isset($data['hits']) && is_array($data['hits'])) ? $data['hits'] : array();
    $now = time();
    $times = array_values(array_filter($times, function ($t) use ($now) {
        return ($now - (int)$t) < SHUFEI_AISET_RATE_WINDOW;
    }));
    return count($times) < SHUFEI_AISET_RATE_MAX;
}

function shufei_ai_set_rate_record()
{
    $file = shufei_ai_set_rate_key_file();
    $data = json_decode((string)@file_get_contents($file), true);
    $times = (is_array($data) && isset($data['hits']) && is_array($data['hits'])) ? $data['hits'] : array();
    $now = time();
    $times[] = $now;
    $times = array_values(array_filter($times, function ($t) use ($now) {
        return ($now - (int)$t) < SHUFEI_AISET_RATE_WINDOW;
    }));
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($file, json_encode(array('hits' => $times)), LOCK_EX);
}

/** 系统提示词 */
function shufei_ai_set_system_prompt()
{
    $options = \Widget\Options::alloc();
    $siteTitle = isset($options->title) ? $options->title : '';
    return "你是网站「{$siteTitle}」后台的「主题设置 AI 助手」，当前管理员正在 ShuFeiCat 主题的外观设置页面与你对话。\n"
        . "你可以调用工具读取和修改主题设置：\n"
        . "- list_theme_settings：查看全部可修改的设置项及当前值\n"
        . "- update_theme_settings：修改设置（updates 为 JSON 对象 {\"字段名\": \"新值\"}），保存后立即对前台生效\n"
        . "规则：\n"
        . "1. 修改前必须先用 list_theme_settings 确认字段名与当前值，不要凭空猜测字段名。\n"
        . "2. 一次可以修改多个字段；修改成功后简要告诉用户改了什么，并提醒刷新设置页查看表单新值。\n"
        . "3. 密钥、密码等敏感字段既查不到也不允许修改；用户要求修改时婉拒，说明需在表单中手动填写。\n"
        . "4. 用户没有明确要求时不要擅自修改；对字段含义不确定时先查询再行动。\n"
        . "5. 使用简体中文，回答简洁专业。";
}

/* ================================================================
 * 主流程
 * ================================================================ */

try {
    $message = isset($_POST['message']) ? trim((string)$_POST['message']) : '';
    $historyRaw = isset($_POST['history']) ? $_POST['history'] : '';
    $history = array();
    $decoded = json_decode((string)$historyRaw, true);
    if (is_array($decoded)) {
        $history = $decoded;
    }

    if ($message === '') {
        echo json_encode(array('success' => false, 'message' => '请输入内容'));
        exit;
    }
    if (mb_strlen($message, 'UTF-8') > 1000) {
        $message = mb_substr($message, 0, 1000, 'UTF-8');
    }

    if (!shufei_ai_set_rate_allowed()) {
        echo json_encode(array('success' => false, 'message' => '操作太频繁，请稍等一分钟再试'));
        exit;
    }

    // 历史清洗（复用前台对话的白名单逻辑）
    $cleanHistory = AiChat::sanitizeHistory($history, 12);

    shufei_ai_set_rate_record();

    // Provider（与摘要/对话一致的选择逻辑）
    $_aiProviderFile = dirname(__FILE__) . '/ai-provider.php';
    if (!class_exists('AiProvider') && file_exists($_aiProviderFile)) {
        require_once $_aiProviderFile;
    }
    if (!class_exists('AiProvider')) {
        echo json_encode(array('success' => false, 'message' => 'AI 基础库缺失'));
        exit;
    }

    $unified = isset($options->aiUnifiedApi) ? $options->aiUnifiedApi : 'on';
    $writerEnabled = isset($options->aiWriterEnabled) ? $options->aiWriterEnabled : 'off';
    $moderationEnabled = isset($options->aiModerationEnabled) ? $options->aiModerationEnabled : 'off';
    if ($unified === 'on') {
        $provider = AiProvider::fromUnifiedOptions();
    } elseif ($writerEnabled === 'on') {
        $provider = AiProvider::fromOptions();
    } elseif ($moderationEnabled === 'on') {
        $provider = AiProvider::fromModerationOptions();
    } else {
        $provider = AiProvider::fromUnifiedOptions();
    }

    if (!$provider || !$provider->isConfigured()) {
        echo json_encode(array('success' => false, 'message' => 'AI 接口未配置，请先在下方「AI助手」中配置接口'));
        exit;
    }

    // 组装消息
    $messages = array();
    $messages[] = array('role' => 'system', 'content' => shufei_ai_set_system_prompt());
    foreach ($cleanHistory as $h) {
        $messages[] = $h;
    }
    $messages[] = array('role' => 'user', 'content' => $message);

    $actions = array();
    $reply = '';
    $failed = false;
    $failMessage = '';

    // 工具循环（接口不支持 tools 时自动降级为普通对话）
    $tools = shufei_ai_set_tool_definitions();
    $usedTools = false;

    for ($round = 0; $round < SHUFEI_AISET_MAX_TOOL_ROUNDS; $round++) {
        $resp = $provider->chatWithTools($messages, $tools, 0.3, SHUFEI_AISET_CHAT_MAX_TOKENS);

        if (!$resp['success']) {
            if (!empty($resp['tool_unsupported']) && !$usedTools) {
                break; // 接口不支持 tools → 降级普通对话
            }
            $failed = true;
            $failMessage = isset($resp['message']) ? $resp['message'] : 'AI 接口调用失败';
            break;
        }

        $toolCalls = isset($resp['tool_calls']) ? $resp['tool_calls'] : array();
        if (empty($toolCalls)) {
            $reply = trim(isset($resp['content']) ? $resp['content'] : '');
            break;
        }

        $usedTools = true;
        $messages[] = $resp['assistant_message'];
        foreach ($toolCalls as $call) {
            $toolResult = shufei_ai_set_execute_tool($call['name'], $call['arguments'], $actions);
            $messages[] = array(
                'role'         => 'tool',
                'tool_call_id' => $call['id'],
                'content'      => $toolResult,
            );
        }
    }

    // 降级普通对话 / 工具轮次用尽后的收尾
    if (!$failed && $reply === '') {
        $resp = $provider->chat($messages, 0.3, SHUFEI_AISET_CHAT_MAX_TOKENS);
        if ($resp['success']) {
            $reply = trim($resp['content']);
        } else {
            $failed = true;
            $failMessage = isset($resp['message']) ? $resp['message'] : 'AI 接口调用失败';
        }
    }

    if ($failed) {
        echo json_encode(array('success' => false, 'message' => $failMessage));
        exit;
    }
    if ($reply === '') {
        echo json_encode(array('success' => false, 'message' => 'AI 未返回有效内容'));
        exit;
    }

    echo json_encode(array(
        'success' => true,
        'reply'   => $reply,
        'actions' => $actions,
    ), JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    echo json_encode(array('success' => false, 'message' => '处理失败: ' . $e->getMessage()));
    exit;
}
