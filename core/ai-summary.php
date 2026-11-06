<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 文章 AI 总结类
 *
 * 前台文章页展示 AI 生成的摘要。
 *  - 全局开关：aiSummaryEnabled（off/on），在后台「AI助手」设置
 *  - 全局助手：aiAssistantGlobal（off/on），开启后所有公开文章自动启用（免逐篇设置）
 *  - 缓存时间：aiSummaryCacheTime（秒），默认 86400（1天）
 *  - 单篇文章开关：自定义字段 aiSummary（on/off），默认 off
 *
 * 总结内容缓存于 core/cache/ai_summary_{cid}.json，带时间戳。
 * 隐私锁：密码文章未解锁 / 隐藏 / 草稿 / 待审文章不生成摘要。
 */
class AiSummary
{
    /**
     * 是否全局开启 AI 总结
     */
    public static function isEnabled()
    {
        $options = \Typecho\Widget::widget('Widget_Options');
        return isset($options->aiSummaryEnabled) ? ($options->aiSummaryEnabled === 'on') : false;
    }

    /**
     * 全局 AI 助手模式：开启后所有公开文章自动启用 AI 摘要（无需逐篇设置字段）
     */
    public static function isGlobalMode()
    {
        $options = \Typecho\Widget::widget('Widget_Options');
        return isset($options->aiAssistantGlobal) ? ($options->aiAssistantGlobal === 'on') : false;
    }

    /**
     * 摘要对某篇文章是否可用
     * 全局模式优先（所有公开文章可用），否则看单篇 aiSummary 字段
     */
    public static function isAvailable($cid)
    {
        if (!self::isEnabled()) {
            return false;
        }
        if (self::isGlobalMode()) {
            return true;
        }
        return self::isArticleEnabled($cid);
    }

    /* ================================================================
     * 生成频率限制（文件计数，模式同 ai-chat.php）
     * 全局模式下访客首次浏览会自动触发生成，必须限速防止端点被刷爆 AI 额度
     * ================================================================ */

    /** 窗口参数：60 秒内最多 12 次真实生成 */
    const RATE_WINDOW = 60;
    const RATE_MAX = 12;

    private static function rateFile()
    {
        $ip = '0.0.0.0';
        if (function_exists('shufei_get_voter_ip')) {
            $ip = shufei_get_voter_ip();
        } elseif (isset($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        $key = hash('sha256', 'ai_summary|' . $ip);
        return dirname(__FILE__) . '/cache/ai_summary_rate_' . $key . '.json';
    }

    public static function rateAllowed()
    {
        $file = self::rateFile();
        if (!is_file($file)) {
            return true;
        }
        $data = json_decode((string)@file_get_contents($file), true);
        $times = (is_array($data) && isset($data['hits']) && is_array($data['hits'])) ? $data['hits'] : array();
        $now = time();
        $times = array_values(array_filter($times, function ($t) use ($now) {
            return ($now - (int)$t) < self::RATE_WINDOW;
        }));
        return count($times) < self::RATE_MAX;
    }

    public static function rateRecord()
    {
        $file = self::rateFile();
        $data = json_decode((string)@file_get_contents($file), true);
        $times = (is_array($data) && isset($data['hits']) && is_array($data['hits'])) ? $data['hits'] : array();
        $now = time();
        $times[] = $now;
        $times = array_values(array_filter($times, function ($t) use ($now) {
            return ($now - (int)$t) < self::RATE_WINDOW;
        }));
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($file, json_encode(array('hits' => $times)), LOCK_EX);
    }

    /**
     * 获取缓存时间（秒）
     */
    public static function getCacheTime()
    {
        $options = \Typecho\Widget::widget('Widget_Options');
        $t = isset($options->aiSummaryCacheTime) ? intval($options->aiSummaryCacheTime) : 86400;
        return max(60, $t);
    }

    /**
     * 是否开启流式输出摘要
     */
    public static function isStreamEnabled()
    {
        $options = \Typecho\Widget::widget('Widget_Options');
        return isset($options->aiSummaryStream) ? ($options->aiSummaryStream === 'on') : false;
    }

    /**
     * 获取单篇文章的 AI 总结开关（自定义字段 aiSummary，默认 off）
     *
     * @param int $cid
     * @return bool
     */
    public static function isArticleEnabled($cid)
    {
        $cid = intval($cid);
        if ($cid <= 0) return false;
        $fields = self::getArticleFields($cid);
        $val = isset($fields['aiSummary']) ? strtolower(trim($fields['aiSummary'])) : '';
        return in_array($val, array('on', '1', 'true'), true);
    }

    /**
     * 获取文章字段表
     *
     * @param int $cid
     * @return array
     */
    private static function getArticleFields($cid)
    {
        static $cache = array();
        if (isset($cache[$cid])) {
            return $cache[$cid];
        }
        $fields = array();
        try {
            $db = \Typecho\Db::get();
            $prefix = $db->getPrefix();
            $rows = $db->fetchAll($db->select('name', 'str_value')
                ->from($prefix . 'fields')
                ->where('cid = ?', $cid));
            foreach ($rows as $f) {
                $fields[$f['name']] = $f['str_value'];
            }
        } catch (\Throwable $e) {
            // 忽略数据库异常
        }
        $cache[$cid] = $fields;
        return $fields;
    }

    /**
     * 读取文章行（publish 的 post），带请求内缓存
     *
     * @param int $cid
     * @return array|null
     */
    private static function getPostRow($cid)
    {
        static $rowCache = array();
        $cid = intval($cid);
        if (array_key_exists($cid, $rowCache)) {
            return $rowCache[$cid];
        }
        $row = null;
        try {
            $db = \Typecho\Db::get();
            $prefix = $db->getPrefix();
            $r = $db->fetchRow($db->select('text', 'password')
                ->from($prefix . 'contents')
                ->where('cid = ?', $cid)
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish')
                ->limit(1));
            if ($r) {
                $row = $r;
            }
        } catch (\Throwable $e) {
            $row = null;
        }
        $rowCache[$cid] = $row;
        return $row;
    }

    /**
     * 隐私锁：文章是否可公开访问（status=publish 且密码已解锁）
     *
     * 摘要端点（action=ai_summary）可被访客直接调用，必须在「读取缓存之前」校验：
     * 否则文章「曾公开 → 已生成摘要缓存 → 后加密码」时，缓存有效期内会把
     * 摘要泄露给未解锁访客。密码比对与 Typecho 核心一致（cookie 存明文密码）。
     *
     * @param int $cid
     * @return bool
     */
    public static function isArticleAccessible($cid)
    {
        $cid = intval($cid);
        $row = self::getPostRow($cid);
        if (!$row) {
            return false;
        }
        $password = isset($row['password']) ? (string)$row['password'] : '';
        if ($password === '') {
            return true;
        }
        $cookieVal = '';
        try {
            $cookieVal = (string)\Typecho\Cookie::get('protectPassword_' . $cid);
        } catch (\Throwable $e) {
            $cookieVal = '';
        }
        return ($cookieVal !== '' && hash_equals($password, $cookieVal));
    }

    /**
     * 读取文章正文（原始 markdown 或 HTML）
     *
     * 隐私锁：仅 status=publish 的文章；密码文章必须已解锁（cookie 为真实密码，
     * 行为与 Typecho 核心一致）才返回正文，防止摘要泄露受保护内容。
     *
     * @param int $cid
     * @return string
     */
    private static function getArticleText($cid)
    {
        $row = self::getPostRow($cid);
        if (!$row || !self::isArticleAccessible($cid)) {
            return '';
        }
        $text = $row['text'];
        // markdown 开头有 //<!--markdown--> 标记，去掉
        if (strpos($text, '<!--markdown-->') === 0) {
            $text = substr($text, strlen('<!--markdown-->'));
        }
        return $text;
    }

    /**
     * 从缓存读取总结
     *
     * @param int $cid
     * @return string|null
     */
    public static function getCached($cid)
    {
        $file = self::cacheFile($cid);
        if (!file_exists($file)) return null;
        $data = @json_decode(@file_get_contents($file), true);
        if (!is_array($data) || !isset($data['ts'], $data['summary'])) return null;
        if ((time() - $data['ts']) > self::getCacheTime()) return null;
        return $data['summary'];
    }

    /**
     * 写入缓存
     *
     * @param int    $cid
     * @param string $summary
     */
    public static function setCached($cid, $summary)
    {
        $file = self::cacheFile($cid);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($file, json_encode(array(
            'ts' => time(),
            'summary' => $summary,
        ), JSON_UNESCAPED_UNICODE));
    }

    /**
     * 获取缓存文件路径
     */
    private static function cacheFile($cid)
    {
        return dirname(__FILE__) . '/cache/ai_summary_' . intval($cid) . '.json';
    }

    /**
     * 生成 AI 总结（生成成功后写入缓存）
     *
     * @param int $cid
     * @return array ['success'=>bool, 'summary'=>string, 'cached'=>bool, 'message'=>string]
     */
    public static function generate($cid)
    {
        $cid = intval($cid);
        if ($cid <= 0) {
            return array('success' => false, 'summary' => '', 'cached' => false, 'message' => '参数错误');
        }
        if (!self::isEnabled()) {
            return array('success' => false, 'summary' => '', 'cached' => false, 'message' => '文章AI总结功能未开启');
        }
        if (!self::isAvailable($cid)) {
            return array('success' => false, 'summary' => '', 'cached' => false, 'message' => '该文章未开启AI总结');
        }

        // 隐私锁：读缓存之前校验文章可公开访问（密码未解锁的缓存不得下发）
        if (!self::isArticleAccessible($cid)) {
            return array('success' => false, 'summary' => '', 'cached' => false, 'message' => '文章内容为空或不可访问，无法生成总结');
        }

        // 读取缓存
        $cached = self::getCached($cid);
        if ($cached !== null) {
            return array('success' => true, 'summary' => $cached, 'cached' => true, 'message' => '已从缓存读取');
        }

        // 频率限制（仅真实生成计数，缓存命中不占额度）
        if (!self::rateAllowed()) {
            return array('success' => false, 'summary' => '', 'cached' => false, 'message' => '操作太频繁啦，请稍等一分钟再试');
        }
        self::rateRecord();

        $text = self::getArticleText($cid);
        if (trim($text) === '') {
            return array('success' => false, 'summary' => '', 'cached' => false, 'message' => '文章内容为空或不可访问，无法生成总结');
        }

        // 截断超长内容
        if (mb_strlen($text, 'UTF-8') > 12000) {
            $text = mb_substr($text, 0, 12000, 'UTF-8');
        }

        // 构建提示词
        list($systemPrompt, $userPrompt) = self::buildPrompts($text);

        $provider = self::getProvider();

        $result = $provider->complete($systemPrompt, $userPrompt, 0.3, 500);
        if (!$result['success']) {
            return array(
                'success' => false,
                'summary' => '',
                'cached' => false,
                'message' => isset($result['message']) ? $result['message'] : 'AI接口调用失败',
            );
        }

        $summary = trim($result['content']);
        if ($summary === '') {
            return array('success' => false, 'summary' => '', 'cached' => false, 'message' => 'AI未返回有效内容');
        }

        self::setCached($cid, $summary);

        return array('success' => true, 'summary' => $summary, 'cached' => false, 'message' => '生成成功');
    }

    /**
     * 流式生成 AI 总结（SSE）
     *
     * @param int      $cid
     * @param callable $onDelta 收到增量文本时回调 function(string $delta)
     * @return array ['success'=>bool, 'summary'=>string, 'cached'=>bool, 'message'=>string]
     */
    public static function generateStream($cid, $onDelta = null)
    {
        $cid = intval($cid);
        if ($cid <= 0) {
            return array('success' => false, 'summary' => '', 'cached' => false, 'message' => '参数错误');
        }
        if (!self::isEnabled()) {
            return array('success' => false, 'summary' => '', 'cached' => false, 'message' => '文章AI总结功能未开启');
        }
        if (!self::isAvailable($cid)) {
            return array('success' => false, 'summary' => '', 'cached' => false, 'message' => '该文章未开启AI总结');
        }

        // 隐私锁：读缓存之前校验文章可公开访问（密码未解锁的缓存不得下发）
        if (!self::isArticleAccessible($cid)) {
            return array('success' => false, 'summary' => '', 'cached' => false, 'message' => '文章内容为空或不可访问，无法生成总结');
        }

        // 读取缓存
        $cached = self::getCached($cid);
        if ($cached !== null) {
            if (is_callable($onDelta)) {
                $onDelta($cached);
            }
            return array('success' => true, 'summary' => $cached, 'cached' => true, 'message' => '已从缓存读取');
        }

        // 频率限制（仅真实生成计数，缓存命中不占额度）
        if (!self::rateAllowed()) {
            return array('success' => false, 'summary' => '', 'cached' => false, 'message' => '操作太频繁啦，请稍等一分钟再试');
        }
        self::rateRecord();

        $text = self::getArticleText($cid);
        if (trim($text) === '') {
            return array('success' => false, 'summary' => '', 'cached' => false, 'message' => '文章内容为空或不可访问，无法生成总结');
        }

        // 截断超长内容
        if (mb_strlen($text, 'UTF-8') > 12000) {
            $text = mb_substr($text, 0, 12000, 'UTF-8');
        }

        // 构建提示词
        list($systemPrompt, $userPrompt) = self::buildPrompts($text);

        $provider = self::getProvider();

        $content = '';
        $result = $provider->completeStream($systemPrompt, $userPrompt, 0.3, 500, function ($delta) use (&$content, $onDelta) {
            $content .= $delta;
            if (is_callable($onDelta)) {
                $onDelta($delta);
            }
        });

        if (!$result['success']) {
            return array(
                'success' => false,
                'summary' => isset($result['content']) ? $result['content'] : '',
                'cached' => false,
                'message' => isset($result['message']) ? $result['message'] : 'AI接口调用失败',
            );
        }

        $summary = trim($content);
        if ($summary === '') {
            return array('success' => false, 'summary' => '', 'cached' => false, 'message' => 'AI未返回有效内容');
        }

        self::setCached($cid, $summary);

        return array('success' => true, 'summary' => $summary, 'cached' => false, 'message' => '生成成功');
    }

    /**
     * 构建提示词（支持自定义系统提示词，{content} 占位文章内容）
     *
     * @param string $text 文章内容
     * @return array [systemPrompt, userPrompt]
     */
    private static function buildPrompts($text)
    {
        $options = \Typecho\Widget::widget('Widget_Options');
        $customPrompt = isset($options->aiSummaryPrompt) ? trim($options->aiSummaryPrompt) : '';
        if ($customPrompt !== '') {
            $systemPrompt = str_replace('{content}', $text, $customPrompt);
        } else {
            $systemPrompt = '你是一位专业的中文内容摘要助手。请阅读用户提供的文章，生成简洁、准确的摘要。';
        }
        $userPrompt = "请为以下文章生成一段中文摘要，要求：\n"
            . "1. 简洁精炼，控制在 150-250 字左右\n"
            . "2. 提炼文章的核心观点和主要内容\n"
            . "3. 语言通顺，逻辑清晰\n"
            . "4. 直接输出摘要正文，不要添加任何前缀、后缀或解释\n\n"
            . "【文章内容】\n" . $text;
        return array($systemPrompt, $userPrompt);
    }

    /**
     * 获取 AI 提供商实例（统一接口 / 写作接口 / 审核接口）
     */
    private static function getProvider()
    {
        // 性能优化（2026-08）：ai-provider.php 已移出 functions.php 无条件加载列表，
        // 仅在真正调用 AI 生成摘要时引入（后台分支与 AJAX 端点已有各自的引入路径）
        $_aiProviderFile = dirname(__FILE__) . '/ai-provider.php';
        if (!class_exists('AiProvider') && file_exists($_aiProviderFile)) {
            require_once $_aiProviderFile;
        }

        $options = \Typecho\Widget::widget('Widget_Options');
        $unified = isset($options->aiUnifiedApi) ? $options->aiUnifiedApi : 'on';
        $writerEnabled = isset($options->aiWriterEnabled) ? $options->aiWriterEnabled : 'off';
        $moderationEnabled = isset($options->aiModerationEnabled) ? $options->aiModerationEnabled : 'off';

        if ($unified === 'on') {
            return AiProvider::fromUnifiedOptions();
        }
        if ($writerEnabled === 'on') {
            return AiProvider::fromOptions();
        }
        if ($moderationEnabled === 'on') {
            return AiProvider::fromModerationOptions();
        }
        return AiProvider::fromUnifiedOptions();
    }
}
