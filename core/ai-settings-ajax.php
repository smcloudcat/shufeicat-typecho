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

// 全局 fatal error handler（区分 JSON / SSE 两种响应模式）
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        if (!empty($GLOBALS['__shufei_aiset_sse'])) {
            // 流式模式：错误也以 SSE 事件下发，避免 JSON 混进事件流
            if (!headers_sent()) {
                header('Content-Type: text/event-stream; charset=utf-8');
            }
            echo 'data: ' . json_encode(array('type' => 'error', 'message' => 'PHP致命错误: ' . $err['message']), JSON_UNESCAPED_UNICODE) . "\n\n";
            if (function_exists('flush')) { @flush(); }
            return;
        }
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

// 功能开关：后台 AI 助手默认关闭，关闭时不接受任何对话请求（双保险：前台也不会输出悬浮窗）
if (!isset($options->adminAiAssistant) || $options->adminAiAssistant !== 'on') {
    echo json_encode(array('success' => false, 'message' => '后台 AI 设置助手已关闭，请在「AI助手」设置中开启后再使用'));
    exit;
}

// 复用前台对话的历史清洗 + Provider 选择逻辑
require_once dirname(__FILE__) . '/ai-chat.php';
// URL 字段校验（与 theme-save-ajax 共用同一套规则）
require_once dirname(__FILE__) . '/theme-url-rules.php';

/* ================================================================
 * 工具实现
 * ================================================================ */

const SHUFEI_AISET_MAX_TOOL_ROUNDS = 5;
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

/**
 * 助手自身配置字段（adminAi*）：对 AI 完全不可见、不可写
 *
 * 防止 AI 把助手自己的接口地址/开关改坏导致助手永久失效（自锁），
 * 也避免管理员的密钥经由助手配置被间接读取。
 */
function shufei_ai_set_is_self_config($field)
{
    return strpos(strtolower(trim((string)$field)), 'adminai') === 0;
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
        if (shufei_ai_set_is_sensitive($field) || shufei_ai_set_is_self_config($field)) {
            continue; // 敏感字段 / 助手自身配置对 AI 完全不可见
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
        if (shufei_ai_set_is_self_config($field)) {
            // 助手自身配置：不可自改（否则可能把助手自己改坏）
            $errors[$field] = '这是后台 AI 助手自身的配置项，不允许通过 AI 修改，请在表单中手动填写';
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
function shufei_ai_set_execute_tool($name, $argsJson, &$actions, $emit = null)
{
    // 工具开始执行：流式路径下先告诉前端"在干什么"（工具本身很快，等待主要还是 AI 请求）
    if (is_callable($emit)) {
        $label = '正在执行操作…';
        if ($name === 'list_theme_settings') {
            $label = '正在读取主题设置…';
        } elseif ($name === 'update_theme_settings') {
            $label = '正在修改主题设置…';
        }
        $emit(array('type' => 'tool', 'name' => $name, 'text' => $label));
    }

    $beforeCount = count($actions);
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

    // 新增的动作条目实时推送（前端立即可见，无需等总结）
    if (is_callable($emit)) {
        for ($i = $beforeCount; $i < count($actions); $i++) {
            $emit(array('type' => 'action', 'text' => $actions[$i]));
        }
    }

    $json = json_encode($result, JSON_UNESCAPED_UNICODE);
    if (mb_strlen($json, 'UTF-8') > SHUFEI_AISET_MAX_TOOL_RESULT) {
        $json = mb_substr($json, 0, SHUFEI_AISET_MAX_TOOL_RESULT, 'UTF-8') . '……(结果已截断)';
    }
    return $json;
}

/* ================================================================
 * 文本式工具调用兜底通道
 * 部分接口/模型不支持原生 Function Calling，会把工具调用写成正文文本
 * （如 <{{list_theme_settings}}>），导致「只回复一条消息就停住」。
 * 这里解析这些文本标记并照常执行工具，让对话继续跑完。
 * ================================================================ */

/** 文本协议说明（追加进对话，引导模型用可识别的格式继续调用工具） */
function shufei_ai_set_text_tool_protocol()
{
    return "【工具调用格式】当前接口无法使用原生 Function Calling，请改用下面的纯文本格式调用工具：\n"
        . "单独输出一行，内容为合法 JSON，格式如下：\n"
        . "<<<TOOL>>>{\"name\":\"工具名\",\"arguments\":{参数对象}}\n"
        . "示例：\n"
        . "<<<TOOL>>>{\"name\":\"list_theme_settings\",\"arguments\":{}}\n"
        . "<<<TOOL>>>{\"name\":\"update_theme_settings\",\"arguments\":{\"updates\":{\"primaryColor\":\"#6C5CE7\"}}}\n"
        . "系统会自动执行该调用并把结果返回给你，你随后继续回答。\n"
        . "注意：不要使用其他格式（例如 <{{工具名}}> 这类标记不会被识别）；也不要只在正文里说「我先查看一下」而不输出上面的调用行。";
}

/** 从 $offset 起提取第一段平衡的 JSON 对象/数组文本（失败返回空串） */
function shufei_ai_set_extract_balanced($text, $offset)
{
    $len = strlen($text);
    $start = -1;
    $limit = min($len, (int)$offset + 300); // 只在标记后 300 字符内寻找，避免误取正文里的花括号
    for ($i = max(0, (int)$offset); $i < $limit; $i++) {
        if ($text[$i] === '{' || $text[$i] === '[') {
            $start = $i;
            break;
        }
    }
    if ($start < 0) {
        return '';
    }

    $open = $text[$start];
    $close = $open === '{' ? '}' : ']';
    $depth = 0;
    $inStr = false;
    $esc = false;
    for ($i = $start; $i < $len; $i++) {
        $ch = $text[$i];
        if ($inStr) {
            if ($esc) {
                $esc = false;
            } elseif ($ch === '\\') {
                $esc = true;
            } elseif ($ch === '"') {
                $inStr = false;
            }
            continue;
        }
        if ($ch === '"') {
            $inStr = true;
        } elseif ($ch === $open) {
            $depth++;
        } elseif ($ch === $close) {
            $depth--;
            if ($depth === 0) {
                return substr($text, $start, $i - $start + 1);
            }
        }
    }
    return '';
}

/**
 * 解析模型正文中的文本式工具调用
 * 兼容格式：
 *   ① <<<TOOL>>>{"name":"...","arguments":{...}}      （本端点约定的文本协议）
 *   ② <tool_call>{...}</tool_call> / <tool_calls>[{...}]</tool_calls>
 *   ③ <{{tool_name}}> / {{tool_name}} / <tool_name>   （弱模型常见占位标记，仅识别已知工具）
 *   ④ 正文整体就是 {"name":...,"arguments":...}
 *
 * @return array [{id, name, arguments}, ...]（无匹配返回空数组）
 */
function shufei_ai_set_parse_text_tool_calls($content)
{
    $content = (string)$content;
    if ($content === '') {
        return array();
    }
    $known = array('list_theme_settings', 'update_theme_settings');
    $calls = array();

    $pushCall = function ($name, $args) use (&$calls, $known) {
        $name = trim((string)$name);
        if (!in_array($name, $known, true)) {
            return;
        }
        if (is_string($args)) {
            $argsStr = trim($args);
        } elseif (is_array($args)) {
            $argsStr = empty($args) ? '{}' : json_encode($args, JSON_UNESCAPED_UNICODE);
        } else {
            $argsStr = '{}';
        }
        if ($argsStr === '' || $argsStr === 'null') {
            $argsStr = '{}';
        }
        $calls[] = array(
            'id'        => 'txt_' . substr(md5($name . '|' . count($calls) . '|' . $argsStr), 0, 12),
            'name'      => $name,
            'arguments' => $argsStr,
        );
    };

    // ①② 标记 + JSON 参数
    if (preg_match_all('/(?:<<<TOOL>>>|<<<TOOL_CALL>>>|<\s*tool_calls?\s*>)/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $hit) {
            $json = shufei_ai_set_extract_balanced($content, $hit[1] + strlen($hit[0]));
            if ($json === '') {
                continue;
            }
            $data = json_decode($json, true);
            if (!is_array($data)) {
                continue;
            }
            $items = isset($data['name']) ? array($data) : $data;
            foreach ($items as $item) {
                if (is_array($item) && isset($item['name'])) {
                    $pushCall($item['name'], isset($item['arguments']) ? $item['arguments'] : array());
                }
            }
        }
    }

    // ③ 裸名标记（无参数）
    if (empty($calls)) {
        foreach ($known as $name) {
            $q = preg_quote($name, '/');
            if (preg_match('/<?\s*\{\{\s*' . $q . '\s*\}\}\s*>?/i', $content)
                || preg_match('/<\s*' . $q . '\s*>/i', $content)) {
                $pushCall($name, array());
            }
        }
    }

    // ④ 正文整体即调用 JSON
    if (empty($calls)) {
        $trimmed = trim($content);
        if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
            $data = json_decode($trimmed, true);
            if (is_array($data)) {
                $items = isset($data['name']) ? array($data) : $data;
                foreach ($items as $item) {
                    if (is_array($item) && isset($item['name'])) {
                        $pushCall($item['name'], isset($item['arguments']) ? $item['arguments'] : array());
                    }
                }
            }
        }
    }

    return $calls;
}

/** 剥离回复中的工具调用痕迹（避免把 <{{...}}>、<<<TOOL>>>{...} 之类标记展示给用户） */
function shufei_ai_set_strip_tool_markup($text)
{
    $text = (string)$text;
    if ($text === '') {
        return '';
    }
    // 成对标签（含内部 JSON）
    $text = preg_replace('/<\s*tool_calls?\s*>[\s\S]*?<\s*\/\s*tool_calls?\s*>/i', '', $text);
    // 前缀标记 + 紧随的 JSON
    $guard = 0;
    while ($guard++ < 20 && preg_match('/(?:<<<TOOL>>>|<<<TOOL_CALL>>>|<\s*tool_calls?\s*>)/i', $text, $m, PREG_OFFSET_CAPTURE)) {
        $tagPos = $m[0][1];
        $tagEnd = $tagPos + strlen($m[0][0]);
        $json = shufei_ai_set_extract_balanced($text, $tagEnd);
        $cutEnd = ($json === '') ? $tagEnd : $tagEnd + strlen($json);
        $text = substr($text, 0, $tagPos) . substr($text, $cutEnd);
    }
    // 残留标签字符
    $text = preg_replace('/<\s*\/?\s*tool_calls?\s*>/i', '', $text);
    // 裸名标记
    $text = preg_replace('/<?\s*\{\{\s*(?:list_theme_settings|update_theme_settings)\s*\}\}\s*>?/i', '', $text);
    $text = preg_replace('/<\s*(?:list_theme_settings|update_theme_settings)\s*>/i', '', $text);
    return trim($text);
}

/**
 * 流式增量过滤：避免把「文本式工具调用」标记实时打到用户眼前
 *
 * 流式场景下模型可能把调用写成正文（<{{list_theme_settings}}> / <<<TOOL>>>{...}），
 * 这些内容必须在推送前拦掉；标记还常被分片切断，故末尾疑似片段先扣留再判定。
 *
 * @param string $pending  跨分片的待定缓冲（引用）
 * @param string $delta    新到的增量
 * @param bool   $suppress 本轮是否已判定为工具标记（引用）
 * @return string 可安全展示的文本
 */
function shufei_ai_set_stream_filter(&$pending, $delta, &$suppress)
{
    if ($suppress) {
        return '';
    }
    $pending .= $delta;

    // 命中完整标记起点：标记之前的文本照常展示，其后的内容整轮丢弃
    if (preg_match('/(?:<\{\{|<<<TOOL|<\s*tool_calls?\b|<\s*(?:list_theme_settings|update_theme_settings)\s*>)/i', $pending, $m, PREG_OFFSET_CAPTURE)) {
        $before = substr($pending, 0, $m[0][1]);
        $suppress = true;
        $pending = '';
        return $before;
    }

    // 末尾疑似标记前缀（可能被分片切断）→ 先扣留，等下一片再判
    $candidates = array('<{{', '<<<TOOL', '<tool_call', '<tool_calls', '<list_theme_settings>', '<update_theme_settings>');
    $hold = 0;
    foreach ($candidates as $cand) {
        $max = min(strlen($cand), strlen($pending));
        for ($k = 1; $k <= $max; $k++) {
            if (substr($pending, -$k) === substr($cand, 0, $k) && $k > $hold) {
                $hold = $k;
            }
        }
    }
    if ($hold >= strlen($pending)) {
        // 整段都还是疑似前缀：超过 40 字节仍未确认就放行（避免卡住正常文本）
        if (strlen($pending) < 40) {
            return '';
        }
        $hold = 0;
    }
    if ($hold > 0 && $hold < 40) {
        $send = substr($pending, 0, strlen($pending) - $hold);
        $pending = substr($pending, -$hold);
        return $send;
    }

    $send = $pending;
    $pending = '';
    return $send;
}

/**
 * 执行「对话 + 工具调用」循环（JSON 与 SSE 两条路径共用）
 *
 * @param AiProvider    $provider
 * @param array         $messages      初始消息（函数内会继续追加）
 * @param callable|null $emit          事件回调 function(array $event)；为 null 时静默（JSON 路径）
 * @param array         $actions       输出：修改动作摘要
 * @param bool          $failed        输出：是否失败
 * @param string        $failMessage   输出：失败原因
 * @param bool          $allowStream   是否允许流式请求（SSE 路径传 true）
 * @return string 最终回复文本（已净化）
 */
function shufei_ai_set_run_tool_loop($provider, $messages, $emit, &$actions, &$failed, &$failMessage, $allowStream = false)
{
    $actions = array();
    $reply = '';
    $failed = false;
    $failMessage = '';

    $tools = shufei_ai_set_tool_definitions();
    $useNativeTools = true;
    $useStream = $allowStream && is_callable($emit);
    $protocolInjected = false;
    $unsupportedRetries = 0;
    $streamRetries = 0;
    $usedTools = false;
    $streamedAny = false; // 是否已向前端吐过字（决定轮次切换要不要清空气泡）

    for ($round = 0; $round < SHUFEI_AISET_MAX_TOOL_ROUNDS; $round++) {
        if (is_callable($emit)) {
            // 新一轮开始：上一轮已吐过字 → 先让前端清空气泡（中间过程不堆积，最终回复不"缩水"）
            if ($round > 0 && $streamedAny) {
                $emit(array('type' => 'reset'));
            }
            $emit(array('type' => 'status', 'text' => $round === 0 ? 'AI 正在思考…' : '正在继续处理…'));
        }

        /* ---------- 发起请求：优先流式，不行再退回普通 ---------- */
        if ($useStream) {
            $emitted = false;
            $streamPending = '';
            $streamSuppress = false;
            $resp = $provider->chatStream($messages, 0.3, SHUFEI_AISET_CHAT_MAX_TOKENS, function ($delta) use ($emit, &$emitted, &$streamedAny, &$streamPending, &$streamSuppress) {
                $safe = shufei_ai_set_stream_filter($streamPending, $delta, $streamSuppress);
                if ($safe !== '') {
                    $emitted = true;
                    $streamedAny = true;
                    $emit(array('type' => 'delta', 'text' => $safe));
                }
            });
            // 轮末：补发被扣留的安全内容（若已判定为工具标记则丢弃）
            if (!$streamSuppress && $streamPending !== '') {
                $tail = shufei_ai_set_strip_tool_markup($streamPending);
                $streamPending = '';
                if ($tail !== '') {
                    $emitted = true;
                    $streamedAny = true;
                    $emit(array('type' => 'delta', 'text' => $tail));
                }
            }
            if (empty($resp['success']) && !empty($resp['stream_unsupported']) && !$emitted && $streamRetries < 1) {
                // 接口不支持流式（且本轮还没吐出任何内容）→ 切普通模式重试本轮
                $useStream = false;
                $streamRetries++;
                $emit(array('type' => 'notice', 'text' => '当前接口不支持流式输出，已切换为普通回复'));
                $round--;
                continue;
            }
            if (!empty($resp['success'])) {
                // 统一成与 chatWithTools 相同的结构，后面的处理逻辑完全共用
                $tcs = isset($resp['tool_calls']) ? $resp['tool_calls'] : array();
                $assistantMessage = array('role' => 'assistant');
                if (!empty($tcs)) {
                    $native = array();
                    foreach ($tcs as $tc) {
                        $native[] = array(
                            'id'       => $tc['id'],
                            'type'     => 'function',
                            'function' => array('name' => $tc['name'], 'arguments' => $tc['arguments']),
                        );
                    }
                    $assistantMessage['tool_calls'] = $native;
                    if (isset($resp['content']) && $resp['content'] !== '') {
                        $assistantMessage['content'] = $resp['content'];
                    }
                }
                $resp['assistant_message'] = $assistantMessage;
            }
        } else {
            $resp = $provider->chatWithTools($messages, $useNativeTools ? $tools : array(), 0.3, SHUFEI_AISET_CHAT_MAX_TOKENS);
        }

        if (empty($resp['success'])) {
            if (!empty($resp['tool_unsupported']) && $useNativeTools && !$useStream && $unsupportedRetries < 2) {
                // 接口明确不支持 tools 参数：切换为文本协议模式重试（不计入工具轮次）
                $useNativeTools = false;
                $unsupportedRetries++;
                if (!$protocolInjected) {
                    $protocolInjected = true;
                    $messages[] = array('role' => 'user', 'content' => shufei_ai_set_text_tool_protocol());
                }
                $round--;
                continue;
            }
            $failed = true;
            $failMessage = isset($resp['message']) ? $resp['message'] : 'AI 接口调用失败';
            break;
        }

        $content = trim(isset($resp['content']) ? (string)$resp['content'] : '');
        $toolCalls = isset($resp['tool_calls']) ? $resp['tool_calls'] : array();
        $nativeCall = !empty($toolCalls);

        // 无原生调用 → 尝试解析正文里的文本式调用
        if (!$nativeCall && $content !== '') {
            $toolCalls = shufei_ai_set_parse_text_tool_calls($content);
        }

        if (empty($toolCalls)) {
            $reply = $content;
            break;
        }

        $usedTools = true;

        if ($nativeCall) {
            // 原生调用：回传原始 assistant 消息（含 tool_calls 结构），以 tool 角色回填结果
            $messages[] = $resp['assistant_message'];
            foreach ($toolCalls as $call) {
                $toolResult = shufei_ai_set_execute_tool($call['name'], $call['arguments'], $actions, $emit);
                $messages[] = array(
                    'role'         => 'tool',
                    'tool_call_id' => $call['id'],
                    'content'      => $toolResult,
                );
            }
        } else {
            // 文本协议：接口多半不认识 role:tool，结果以 user 回合回填以保证兼容性
            if (!$protocolInjected) {
                $protocolInjected = true;
                $messages[] = array('role' => 'user', 'content' => shufei_ai_set_text_tool_protocol());
            }
            $stripped = shufei_ai_set_strip_tool_markup($content);
            $messages[] = array('role' => 'assistant', 'content' => $stripped !== '' ? $stripped : '（正在调用工具）');
            $lines = array();
            foreach ($toolCalls as $call) {
                $toolResult = shufei_ai_set_execute_tool($call['name'], $call['arguments'], $actions, $emit);
                $lines[] = '工具 ' . $call['name'] . ' 执行结果：' . $toolResult;
            }
            $messages[] = array(
                'role'    => 'user',
                'content' => implode("\n\n", $lines)
                    . "\n\n请根据以上结果继续：若还需调用工具，按格式单独输出一行调用；否则用简体中文简要总结刚才做了什么。",
            );
        }
    }

    // 收尾：模型未给出自然语言回复（工具轮次用尽 / 只输出了调用标记）时补一次总结
    if (!$failed && $reply === '') {
        if ($usedTools) {
            $messages[] = array('role' => 'user', 'content' => '请用简体中文简要总结刚才的操作结果（不要再调用工具），并提醒我刷新设置页查看新值。');
        }
        if (is_callable($emit)) {
            // 收尾总结是全新内容：先清掉前面轮次残留的过程文字
            if ($streamedAny) {
                $emit(array('type' => 'reset'));
                $streamedAny = false;
            }
            $emit(array('type' => 'status', 'text' => '正在整理结果…'));
        }
        $summary = '';
        $final = array('success' => false, 'message' => 'AI 未返回有效内容');
        if ($useStream) {
            // 流式收尾：总结也走逐字输出（同样做工具标记过滤）
            $tailPending = '';
            $tailSuppress = false;
            $final = $provider->chatStream($messages, 0.3, SHUFEI_AISET_CHAT_MAX_TOKENS, function ($delta) use ($emit, &$tailPending, &$tailSuppress) {
                $safe = shufei_ai_set_stream_filter($tailPending, $delta, $tailSuppress);
                if ($safe !== '') {
                    $emit(array('type' => 'delta', 'text' => $safe));
                }
            });
            if (!$tailSuppress && $tailPending !== '') {
                $tail = shufei_ai_set_strip_tool_markup($tailPending);
                if ($tail !== '') {
                    $emit(array('type' => 'delta', 'text' => $tail));
                }
            }
            if (!empty($final['success'])) {
                $summary = shufei_ai_set_strip_tool_markup(trim((string)$final['content']));
            } else {
                // 流式不可用 → 退回普通调用拿总结
                $final = $provider->chat($messages, 0.3, SHUFEI_AISET_CHAT_MAX_TOKENS);
                if (!empty($final['success']) && isset($final['content'])) {
                    $summary = shufei_ai_set_strip_tool_markup(trim((string)$final['content']));
                    if ($summary !== '') {
                        $emit(array('type' => 'delta', 'text' => $summary));
                    }
                }
            }
        } else {
            $final = $provider->chat($messages, 0.3, SHUFEI_AISET_CHAT_MAX_TOKENS);
            if (!empty($final['success']) && isset($final['content'])) {
                $summary = shufei_ai_set_strip_tool_markup(trim((string)$final['content']));
            }
        }
        if ($summary === '') {
            if ($usedTools) {
                // 模型没能总结：用动作摘要兜底，至少让用户知道发生了什么
                $summary = empty($actions)
                    ? '已执行你的请求，请刷新设置页查看当前值与状态。'
                    : '已执行你的请求：' . implode('；', $actions) . '。刷新设置页即可看到表单新值。';
            } else {
                $failed = true;
                $failMessage = isset($final['message']) ? $final['message'] : 'AI 未返回有效内容';
            }
        }
        $reply = $summary;
    }

    return shufei_ai_set_strip_tool_markup($reply);
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
        . "工具调用方式：\n"
        . "1. 优先使用接口提供的原生 Function Calling 能力直接调用工具。\n"
        . "2. 仅当接口不支持原生调用时，才使用下面的纯文本调用格式（单独占一行，内容为合法 JSON），系统会自动执行并把结果返回给你：\n"
        . "   <<<TOOL>>>{\"name\":\"工具名\",\"arguments\":{参数对象}}\n"
        . "   例如：<<<TOOL>>>{\"name\":\"list_theme_settings\",\"arguments\":{}}\n"
        . "3. 严禁在回复正文里写 <{{工具名}}> 这类标记，也严禁只说「我先查看一下」却不实际输出工具调用——那不会被执行，对话会中断。\n"
        . "规则：\n"
        . "1. 修改前必须先用 list_theme_settings 确认字段名与当前值，不要凭空猜测字段名。\n"
        . "2. 一次可以修改多个字段；调用工具后必须继续给出自然语言总结，告诉用户改了什么，并提醒刷新设置页查看表单新值。\n"
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

    // 与后台悬浮窗的就绪判断完全一致：跟随站点接口 or 助手自定义接口
    $provider = AiProvider::fromAdminAssistantOptions();

    if (!$provider || !$provider->isConfigured()) {
        $apiSource = isset($options->adminAiApiSource) ? $options->adminAiApiSource : 'follow';
        echo json_encode(array('success' => false, 'message' => $apiSource === 'custom'
            ? '助手的自定义接口未配置，请在「AI助手」设置中补全「助手接口 API 地址 / 密钥」'
            : 'AI 接口未配置，请先在「AI助手」中配置「统一接口」，或将「助手接口来源」改为「自定义接口」单独指定'));
        exit;
    }

    // 组装消息
    $messages = array();
    $messages[] = array('role' => 'system', 'content' => shufei_ai_set_system_prompt());
    foreach ($cleanHistory as $h) {
        $messages[] = $h;
    }
    $messages[] = array('role' => 'user', 'content' => $message);

    // 是否走流式（前端带 stream=1 且后台开启「助手流式回复」）
    $wantStream = isset($_POST['stream']) && (string)$_POST['stream'] === '1'
        && (!isset($options->adminAiStream) || $options->adminAiStream === 'on');

    if ($wantStream) {
        /* ---------------- SSE 流式路径 ---------------- */
        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        $GLOBALS['__shufei_aiset_sse'] = true;

        $emit = function ($event) {
            echo 'data: ' . json_encode($event, JSON_UNESCAPED_UNICODE) . "\n\n";
            if (function_exists('flush')) { @flush(); }
        };

        $emit(array('type' => 'start'));

        $actions = array();
        $failed = false;
        $failMessage = '';
        // 工具调用循环 + 流式正文（文本协议兜底仍在函数内生效）
        $reply = shufei_ai_set_run_tool_loop($provider, $messages, $emit, $actions, $failed, $failMessage, true);

        if ($failed) {
            $emit(array('type' => 'error', 'message' => $failMessage));
        } elseif ($reply === '') {
            $emit(array('type' => 'error', 'message' => 'AI 未返回有效内容'));
        } else {
            $emit(array('type' => 'done', 'success' => true, 'reply' => $reply, 'actions' => $actions));
        }
        exit;
    }

    /* ---------------- 普通 JSON 路径（保持原有行为） ---------------- */
    $actions = array();
    $failed = false;
    $failMessage = '';
    $reply = shufei_ai_set_run_tool_loop($provider, $messages, null, $actions, $failed, $failMessage, false);

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
