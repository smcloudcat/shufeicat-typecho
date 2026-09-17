<?php
/**
 * AI 摘要连续对话模块
 *
 * 依赖摘要功能（core/ai-summary.php）开启后，允许访客在摘要卡片下方与 AI 连续对话。
 *  - 对话模式：aiChatMode（off=关闭 / summary_only=仅摘要不开放对话 / on=摘要+对话）
 *  - 全局助手：aiAssistantGlobal（off/on），开启后所有公开文章自动启用摘要+对话
 *  - 轮数限制：aiChatMaxRounds（每个会话最大对话轮数，默认 10）
 *  - 提示词  ：aiChatSystemPrompt（可选自定义追加系统提示词）
 *
 * AI 可通过 Function Calling 调用站内工具：
 *  - search_posts     搜索全站公开文章
 *  - get_post_content 获取某篇公开文章正文（节选）
 *  - search_comments  搜索全站公开评论
 *  - get_site_info    获取站点公开统计信息
 *
 * 隐私锁（代码层强制，与提示词无关）：
 *  - 文章相关查询一律限定 type=post AND status='publish' AND password 为空，
 *    密码文章 / 隐藏文章 / 草稿 / 待审文章永远不会进入工具结果；
 *  - get_post_content 对不可访问文章统一返回"不可访问"，不泄露存在性差异；
 *  - 评论查询只 SELECT author/created/text 三列，mail / url / ip / agent 字段
 *    从 SQL 层面就不读取，评论同时 JOIN 文章表并应用上述公开过滤；
 *  - 工具参数与返回均不包含任何用户配置密钥或服务器路径。
 *
 * 对话历史不落库：由前端随请求携带（截断），服务端只做长度与角色白名单校验。
 */
if (!defined('__TYPECHO_ROOT_DIR__') && !defined('SHUFEI_AI_CHAT_OK')) exit;

class AiChat
{
    /** 工具调用最大循环轮数（防死循环） */
    const MAX_TOOL_ROUNDS = 4;
    /** 单个工具结果最大字符数 */
    const MAX_TOOL_RESULT_CHARS = 4000;
    /** 对话回答 max_tokens */
    const CHAT_MAX_TOKENS = 800;
    /** 历史消息最大条数 */
    const MAX_HISTORY = 12;
    /** 历史单条最大字符数 */
    const MAX_HISTORY_CHARS = 1500;
    /** 用户输入最大字符数 */
    const MAX_MESSAGE_CHARS = 1000;

    /**
     * 获取对话模式（off / summary_only / on）
     */
    public static function getMode()
    {
        $options = \Typecho\Widget::widget('Widget_Options');
        $mode = isset($options->aiChatMode) ? trim((string)$options->aiChatMode) : 'off';
        if (!in_array($mode, array('off', 'summary_only', 'on'), true)) {
            $mode = 'off';
        }
        return $mode;
    }

    /**
     * 对话功能是否开启（依赖 AI 总总结功能开启）
     */
    public static function isEnabled()
    {
        if (!class_exists('AiSummary') || !AiSummary::isEnabled()) {
            return false;
        }
        return self::getMode() === 'on';
    }

    /**
     * 每会话最大对话轮数
     */
    public static function getMaxRounds()
    {
        $options = \Typecho\Widget::widget('Widget_Options');
        $n = isset($options->aiChatMaxRounds) ? intval($options->aiChatMaxRounds) : 10;
        return max(1, min(30, $n));
    }

    /* ================================================================
     * 频率限制（文件计数，模式同 password-rate-limit.php）
     * ================================================================ */

    /** 窗口参数：60 秒内最多 8 次请求 */
    const RATE_WINDOW = 60;
    const RATE_MAX = 8;

    private static function rateFile()
    {
        $ip = '0.0.0.0';
        if (function_exists('shufei_get_voter_ip')) {
            $ip = shufei_get_voter_ip();
        } elseif (isset($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        $key = hash('sha256', 'ai_chat|' . $ip);
        return dirname(__FILE__) . '/cache/ai_chat_' . $key . '.json';
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

    /* ================================================================
     * 隐私锁：文章访问判定（唯一入口，所有工具共用）
     * ================================================================ */

    /**
     * 读取一篇「可公开访问」的文章行（type=post / status=publish / 无密码）
     *
     * @param int  $cid
     * @param bool $withText 是否取正文
     * @return array|null 不可访问时返回 null
     */
    public static function getAccessiblePost($cid, $withText = false)
    {
        $cid = intval($cid);
        if ($cid <= 0) {
            return null;
        }
        try {
            $db = \Typecho\Db::get();
            $prefix = $db->getPrefix();
            $cols = 'cid, title, slug, created, modified, authorId';
            if ($withText) {
                $cols .= ', text';
            }
            // 隐私锁：status=publish 且无密码。任何情况下不得放宽此条件。
            $row = $db->fetchRow($db->select($cols)
                ->from($prefix . 'contents')
                ->where('cid = ?', $cid)
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish')
                ->where('(password IS NULL OR password = ?)', ''));
            return $row ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** 将时间戳转为 Y-m-d 字符串 */
    private static function fmtDate($ts)
    {
        $ts = intval($ts);
        return $ts > 0 ? date('Y-m-d', $ts) : '';
    }

    /** 尝试生成文章前台链接（失败返回空字符串，不抛异常） */
    private static function buildPermalink($row)
    {
        try {
            if (!class_exists('\Typecho\Router')) {
                return '';
            }
            $options = \Typecho\Widget::widget('Widget_Options');
            $created = intval($row['created']);
            $params = array(
                'cid'       => $row['cid'],
                'slug'      => (isset($row['slug']) && $row['slug'] !== '') ? $row['slug'] : $row['cid'],
                'year'      => date('Y', $created),
                'month'     => date('m', $created),
                'day'       => date('d', $created),
                'category'  => '',
                'directory' => '',
                'author'    => '',
            );
            $url = \Typecho\Router::url('archive', $params, rtrim($options->rootUrl, '/'));
            // 路由表缺失时返回 '#'；含未填充占位符时放弃（避免输出半吊子链接）
            if ($url === '' || $url === '#' || strpos($url, '{') !== false) {
                return '';
            }
            return $url;
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** 剥离 Markdown 标记与 HTML，取纯文本节选 */
    private static function textExcerpt($text, $len)
    {
        $text = preg_replace('/<!--markdown-->/i', '', (string)$text);
        $text = preg_replace('/\[.*?\]\((.*?)\)/', '$1', (string)$text); // 链接只留 URL
        $text = preg_replace('/!\[(.*?)\]\((.*?)\)/', '', (string)$text);
        $text = str_replace(array('#', '*', '`', '>', '|'), ' ', (string)$text);
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text, 'UTF-8') > $len) {
            $text = mb_substr($text, 0, $len, 'UTF-8') . '……';
        }
        return $text;
    }

    /* ================================================================
     * 工具定义与执行
     * ================================================================ */

    /**
     * OpenAI Function Calling 工具定义
     */
    private static function toolDefinitions()
    {
        return array(
            array(
                'type' => 'function',
                'function' => array(
                    'name' => 'search_posts',
                    'description' => '搜索本站公开文章。返回标题、日期、摘要片段等。当用户询问站内有哪些相关内容时使用。',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'keyword' => array('type' => 'string', 'description' => '搜索关键词（匹配标题或正文）'),
                            'limit'   => array('type' => 'integer', 'description' => '返回条数，默认 5，最大 8'),
                        ),
                        'required' => array('keyword'),
                    ),
                ),
            ),
            array(
                'type' => 'function',
                'function' => array(
                    'name' => 'get_post_content',
                    'description' => '获取指定文章的正文内容（节选，最多 6000 字）。仅限公开文章，密码或隐藏文章不可访问。',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'cid' => array('type' => 'integer', 'description' => '文章 ID（cid）'),
                        ),
                        'required' => array('cid'),
                    ),
                ),
            ),
            array(
                'type' => 'function',
                'function' => array(
                    'name' => 'search_comments',
                    'description' => '搜索本站公开文章下的评论内容（不含任何评论者隐私信息）。',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'keyword' => array('type' => 'string', 'description' => '搜索关键词（匹配评论内容或昵称）'),
                            'limit'   => array('type' => 'integer', 'description' => '返回条数，默认 5，最大 8'),
                        ),
                        'required' => array('keyword'),
                    ),
                ),
            ),
            array(
                'type' => 'function',
                'function' => array(
                    'name' => 'get_site_info',
                    'description' => '获取站点公开统计信息：站点标题、简介、公开文章总数、评论总数、分类列表、最近更新文章等。',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(),
                    ),
                ),
            ),
        );
    }

    /**
     * 执行工具调用（所有 SQL 均带隐私锁条件）
     *
     * @param string $name
     * @param string $argsJson
     * @return string JSON 字符串（截断到 MAX_TOOL_RESULT_CHARS）
     */
    private static function executeTool($name, $argsJson)
    {
        $args = array();
        $decoded = json_decode((string)$argsJson, true);
        if (is_array($decoded)) {
            $args = $decoded;
        }

        switch ($name) {
            case 'search_posts':
                $result = self::toolSearchPosts($args);
                break;
            case 'get_post_content':
                $result = self::toolGetPostContent($args);
                break;
            case 'search_comments':
                $result = self::toolSearchComments($args);
                break;
            case 'get_site_info':
                $result = self::toolGetSiteInfo();
                break;
            default:
                $result = array('error' => '未知工具');
        }

        $json = json_encode($result, JSON_UNESCAPED_UNICODE);
        if (mb_strlen($json, 'UTF-8') > self::MAX_TOOL_RESULT_CHARS) {
            $json = mb_substr($json, 0, self::MAX_TOOL_RESULT_CHARS, 'UTF-8') . '……(结果已截断)';
        }
        return $json;
    }

    /** 工具：搜索公开文章 */
    private static function toolSearchPosts($args)
    {
        $keyword = isset($args['keyword']) ? trim((string)$args['keyword']) : '';
        $limit = isset($args['limit']) ? intval($args['limit']) : 5;
        $limit = max(1, min(8, $limit));
        if ($keyword === '') {
            return array('error' => '关键词为空');
        }
        $keyword = mb_substr($keyword, 0, 60, 'UTF-8');
        try {
            $db = \Typecho\Db::get();
            $prefix = $db->getPrefix();
            $like = '%' . self::escapeLike($keyword) . '%';
            // 隐私锁：仅公开且无密码文章
            $rows = $db->fetchAll($db->select('cid, title, slug, created, text')
                ->from($prefix . 'contents')
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish')
                ->where('(password IS NULL OR password = ?)', '')
                ->where('(title LIKE ? OR text LIKE ?)', $like, $like)
                ->order('created', \Typecho\Db::SORT_DESC)
                ->limit($limit));
            $posts = array();
            foreach ($rows as $row) {
                $row['text'] = isset($row['text']) ? $row['text'] : '';
                $posts[] = array(
                    'cid'     => intval($row['cid']),
                    'title'   => $row['title'],
                    'date'    => self::fmtDate($row['created']),
                    'url'     => self::buildPermalink($row),
                    'excerpt' => self::textExcerpt($row['text'], 160),
                );
            }
            return array('total' => count($posts), 'posts' => $posts);
        } catch (\Throwable $e) {
            return array('error' => '搜索失败');
        }
    }

    /** 工具：获取公开文章正文节选 */
    private static function toolGetPostContent($args)
    {
        $cid = isset($args['cid']) ? intval($args['cid']) : 0;
        // 统一走 getAccessiblePost：密码/隐藏/草稿一律不可访问
        $post = self::getAccessiblePost($cid, true);
        if (!$post) {
            return array('error' => '该文章不可访问（可能不存在、未公开或受保护）');
        }
        $text = isset($post['text']) ? $post['text'] : '';
        if (strpos($text, '<!--markdown-->') === 0) {
            $text = substr($text, strlen('<!--markdown-->'));
        }
        // 截断后再剥离标记，节省性能
        if (mb_strlen($text, 'UTF-8') > 8000) {
            $text = mb_substr($text, 0, 8000, 'UTF-8');
        }
        $content = self::textExcerpt($text, 6000);
        return array(
            'cid'     => intval($post['cid']),
            'title'   => $post['title'],
            'date'    => self::fmtDate($post['created']),
            'url'     => self::buildPermalink($post),
            'content' => $content,
        );
    }

    /** 工具：搜索公开评论（SQL 层面只取 author/created/text，不读 mail/url/ip） */
    private static function toolSearchComments($args)
    {
        $keyword = isset($args['keyword']) ? trim((string)$args['keyword']) : '';
        $limit = isset($args['limit']) ? intval($args['limit']) : 5;
        $limit = max(1, min(8, $limit));
        if ($keyword === '') {
            return array('error' => '关键词为空');
        }
        $keyword = mb_substr($keyword, 0, 60, 'UTF-8');
        try {
            $db = \Typecho\Db::get();
            $prefix = $db->getPrefix();
            $like = '%' . self::escapeLike($keyword) . '%';
            // 隐私锁：评论只取 author/created/text 三列（mail/url/ip/agent 从不查询）；
            // 且文章必须公开无密码——通过 JOIN 过滤，密码文章下的评论同样不可见
            $rows = $db->fetchAll($db->select(
                'table.comments.author',
                'table.comments.created',
                'table.comments.text',
                'table.contents.title AS post_title'
            )
                ->from($prefix . 'comments')
                ->join('table.contents', 'table.contents.cid = table.comments.cid')
                ->where('table.comments.status = ?', 'approved')
                ->where('table.comments.type = ?', 'comment')
                ->where('table.contents.type = ?', 'post')
                ->where('table.contents.status = ?', 'publish')
                ->where('(table.contents.password IS NULL OR table.contents.password = ?)', '')
                ->where('(table.comments.text LIKE ? OR table.comments.author LIKE ?)', $like, $like)
                ->order('table.comments.created', \Typecho\Db::SORT_DESC)
                ->limit($limit));
            $comments = array();
            foreach ($rows as $row) {
                $comments[] = array(
                    'author'     => $row['author'],
                    'date'       => self::fmtDate($row['created']),
                    'content'    => self::textExcerpt($row['text'], 200),
                    'post_title' => isset($row['post_title']) ? $row['post_title'] : '',
                );
            }
            return array('total' => count($comments), 'comments' => $comments);
        } catch (\Throwable $e) {
            return array('error' => '搜索失败');
        }
    }

    /** 工具：站点公开统计信息 */
    private static function toolGetSiteInfo()
    {
        try {
            $options = \Typecho\Widget::widget('Widget_Options');
            $db = \Typecho\Db::get();
            $prefix = $db->getPrefix();

            // 公开文章总数（隐私锁条件）
            $postCount = $db->fetchObject($db->select(array('COUNT(cid)' => 'cnt'))
                ->from($prefix . 'contents')
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish')
                ->where('(password IS NULL OR password = ?)', ''));
            $postCount = isset($postCount->cnt) ? intval($postCount->cnt) : 0;

            // 公开评论总数
            $commentCount = $db->fetchObject($db->select(array('COUNT(coid)' => 'cnt'))
                ->from($prefix . 'comments')
                ->where('status = ?', 'approved')
                ->where('type = ?', 'comment'));
            $commentCount = isset($commentCount->cnt) ? intval($commentCount->cnt) : 0;

            // 分类列表（公开聚合数据）
            $categories = array();
            try {
                $catRows = $db->fetchAll($db->select('name, count')
                    ->from($prefix . 'metas')
                    ->where('type = ?', 'category')
                    ->order('order', \Typecho\Db::SORT_ASC)
                    ->limit(20));
                foreach ($catRows as $c) {
                    $categories[] = array('name' => $c['name'], 'count' => intval($c['count']));
                }
            } catch (\Throwable $e) {
                // 分类读取失败不影响整体
            }

            // 最近更新的 5 篇公开文章
            $recent = array();
            $rows = $db->fetchAll($db->select('cid, title, slug, created')
                ->from($prefix . 'contents')
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish')
                ->where('(password IS NULL OR password = ?)', '')
                ->order('created', \Typecho\Db::SORT_DESC)
                ->limit(5));
            foreach ($rows as $row) {
                $recent[] = array(
                    'title' => $row['title'],
                    'date'  => self::fmtDate($row['created']),
                    'url'   => self::buildPermalink($row),
                );
            }

            return array(
                'site_title'       => $options->title,
                'site_description' => isset($options->description) ? $options->description : '',
                'public_posts'     => $postCount,
                'public_comments'  => $commentCount,
                'categories'       => $categories,
                'recent_posts'     => $recent,
            );
        } catch (\Throwable $e) {
            return array('error' => '获取站点信息失败');
        }
    }

    /** LIKE 通配符转义 */
    private static function escapeLike($s)
    {
        $s = str_replace('\\', '\\\\', $s);
        return str_replace(array('%', '_'), array('\\%', '\\_'), $s);
    }

    /* ================================================================
     * 主流程：多轮对话 + 工具调用
     * ================================================================ */

    /**
     * 处理一次对话请求
     *
     * @param int    $cid     当前文章 ID；0 表示「全站会话」（仅全局AI助手模式允许，首页入口使用）
     * @param string $message 用户输入
     * @param array  $history 历史消息 [{role:'user'|'assistant', content:'...'}, ...]（由前端携带）
     * @return array ['success'=>bool, 'reply'=>string, 'message'=>string]
     */
    public static function handle($cid, $message, $history = array())
    {
        if (!self::isEnabled()) {
            return array('success' => false, 'reply' => '', 'message' => 'AI 对话功能未开启');
        }

        $cid = intval($cid);
        $post = null;

        if ($cid > 0) {
            // 隐私锁：当前文章必须可公开访问才允许基于它对话
            $post = self::getAccessiblePost($cid);
            if (!$post) {
                return array('success' => false, 'reply' => '', 'message' => '当前文章不可访问，无法进行 AI 对话');
            }

            // 摘要可用性（全局模式 = 所有公开文章；否则看单篇 aiSummary 字段）
            // 对话入口在摘要卡片下，保持与摘要一致的可见范围
            if (class_exists('AiSummary') && !AiSummary::isAvailable($cid)) {
                return array('success' => false, 'reply' => '', 'message' => '该文章未开启 AI 摘要，无法对话');
            }
        } else {
            // cid=0：全站共享会话（首页入口）。仅全局AI助手模式开放，
            // 隐私由工具层锁死（所有查询仍强制公开文章/公开评论）
            if (!class_exists('AiSummary') || !AiSummary::isGlobalMode()) {
                return array('success' => false, 'reply' => '', 'message' => '当前页面不支持 AI 对话');
            }
        }

        // 频率限制
        if (!self::rateAllowed()) {
            return array('success' => false, 'reply' => '', 'message' => '提问太频繁啦，请稍等一分钟再试');
        }

        $message = trim((string)$message);
        if ($message === '') {
            return array('success' => false, 'reply' => '', 'message' => '请输入内容');
        }
        if (mb_strlen($message, 'UTF-8') > self::MAX_MESSAGE_CHARS) {
            $message = mb_substr($message, 0, self::MAX_MESSAGE_CHARS, 'UTF-8');
        }

        // 校验并截断历史（轮数上限 = maxRounds，即 user/assistant 成对出现）
        $maxRounds = self::getMaxRounds();
        $cleanHistory = self::sanitizeHistory($history, $maxRounds * 2);
        if (count($cleanHistory) >= $maxRounds * 2) {
            return array('success' => false, 'reply' => '', 'message' => '对话轮数已达上限（' . $maxRounds . ' 轮），点击「新对话」可重新开始');
        }

        // 记录限速
        self::rateRecord();

        // 构建消息序列
        $messages = array();
        $messages[] = array('role' => 'system', 'content' => self::buildSystemPrompt($post));
        foreach ($cleanHistory as $h) {
            $messages[] = $h;
        }
        $messages[] = array('role' => 'user', 'content' => $message);

        // 获取 Provider（复用摘要的配置选择逻辑）
        $provider = self::getProvider();
        if (!$provider || !$provider->isConfigured()) {
            return array('success' => false, 'reply' => '', 'message' => 'AI 接口未配置');
        }

        $toolsSupported = ($provider->getChatUrl() !== '');

        // 支持工具的提供商：走工具调用循环
        if ($toolsSupported) {
            $result = self::runToolLoop($provider, $messages);
            if ($result !== null) {
                return $result;
            }
            // runToolLoop 返回 null 表示接口不支持 tools，降级为普通对话
        }

        // 普通对话（无工具）
        $resp = $provider->chat($messages, 0.7, self::CHAT_MAX_TOKENS);
        if (!$resp['success']) {
            return array('success' => false, 'reply' => '', 'message' => isset($resp['message']) ? $resp['message'] : 'AI 接口调用失败');
        }
        $reply = trim($resp['content']);
        if ($reply === '') {
            return array('success' => false, 'reply' => '', 'message' => 'AI 未返回有效内容');
        }
        return array('success' => true, 'reply' => $reply, 'message' => 'ok');
    }

    /**
     * 工具调用循环
     *
     * @return array|null 处理结果；接口不支持 tools 时返回 null（由调用方降级）
     */
    private static function runToolLoop($provider, $messages)
    {
        $tools = self::toolDefinitions();

        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            $resp = $provider->chatWithTools($messages, $tools, 0.7, self::CHAT_MAX_TOKENS);

            // 接口不支持 tools（HTTP 4xx 类错误）：整体降级为普通对话
            if (!$resp['success']) {
                if (!empty($resp['tool_unsupported'])) {
                    return null;
                }
                return array('success' => false, 'reply' => '', 'message' => isset($resp['message']) ? $resp['message'] : 'AI 接口调用失败');
            }

            $toolCalls = isset($resp['tool_calls']) ? $resp['tool_calls'] : array();
            if (empty($toolCalls)) {
                // 无工具调用，直接返回最终回答
                $reply = trim(isset($resp['content']) ? $resp['content'] : '');
                if ($reply === '') {
                    return array('success' => false, 'reply' => '', 'message' => 'AI 未返回有效内容');
                }
                return array('success' => true, 'reply' => $reply, 'message' => 'ok');
            }

            // 有工具调用：附加 assistant 消息（含 tool_calls 原始结构），执行并追加 tool 结果
            $messages[] = $resp['assistant_message'];
            foreach ($toolCalls as $call) {
                $toolResult = self::executeTool($call['name'], $call['arguments']);
                $messages[] = array(
                    'role'         => 'tool',
                    'tool_call_id' => $call['id'],
                    'content'      => $toolResult,
                );
            }
        }

        // 超过最大工具轮数仍未给出答案
        return array('success' => false, 'reply' => '', 'message' => 'AI 处理超时，请换个问法再试');
    }

    /**
     * 清洗历史消息：角色白名单 + 长度截断 + 条数截断（保留最近的）
     * 公开方法：后台 AI 设置助手端点复用同一套历史校验
     */
    public static function sanitizeHistory($history, $maxItems)
    {
        if (!is_array($history)) {
            return array();
        }
        $clean = array();
        foreach ($history as $h) {
            if (!is_array($h)) {
                continue;
            }
            $role = isset($h['role']) ? strtolower(trim((string)$h['role'])) : '';
            $content = isset($h['content']) ? trim((string)$h['content']) : '';
            if (!in_array($role, array('user', 'assistant'), true)) {
                continue;
            }
            if ($content === '') {
                continue;
            }
            if (mb_strlen($content, 'UTF-8') > self::MAX_HISTORY_CHARS) {
                $content = mb_substr($content, 0, self::MAX_HISTORY_CHARS, 'UTF-8');
            }
            $clean[] = array('role' => $role, 'content' => $content);
        }
        // 只保留最近 maxItems 条
        if (count($clean) > $maxItems) {
            $clean = array_slice($clean, -$maxItems);
        }
        return $clean;
    }

    /**
     * 构建系统提示词
     *
     * @param array|null $post 当前文章行；null 表示全站会话（首页入口，无文章上下文）
     */
    private static function buildSystemPrompt($post)
    {
        $options = \Typecho\Widget::widget('Widget_Options');
        $siteTitle = isset($options->title) ? $options->title : '';

        if (!$post) {
            // 全站会话：不绑定文章，仅站点身份 + 工具能力
            $prompt = "你是网站「{$siteTitle}」的 AI 助手，正在网站首页与访客对话，这是一个全站共享的连续会话（访客可能在任意页面继续与你交流）。\n"
                . "你可以调用工具查询全站公开信息（搜索文章、搜索评论、获取站点统计、获取文章正文等），请优先基于工具结果回答，不要编造不存在的内容。\n"
                . "规则：\n"
                . "1. 使用简体中文，回答简洁友好，适合网页聊天气泡展示。\n"
                . "2. 用户询问本站内容时优先调用工具查询，不要凭空猜测。\n"
                . "3. 涉及他人隐私（邮箱、IP、账号）、密码文章或受保护内容的问题，一律婉拒并说明仅提供公开信息。\n"
                . "4. 与站点无关的问题可以简短回答，但优先引导回站内话题。";
            return self::appendCustomPrompt($prompt, $options);
        }

        $postTitle = $post['title'];
        $postDate  = self::fmtDate($post['created']);
        $postUrl   = self::buildPermalink($post);

        // 当前文章摘要（来自缓存，生成摘要时已存）
        $summary = '';
        if (class_exists('AiSummary')) {
            $summary = (string)AiSummary::getCached($post['cid']);
        }

        // 当前文章正文节选（已经过 getAccessiblePost 隐私校验）
        $text = '';
        $full = self::getAccessiblePost($post['cid'], true);
        if ($full && isset($full['text'])) {
            $t = $full['text'];
            if (strpos($t, '<!--markdown-->') === 0) {
                $t = substr($t, strlen('<!--markdown-->'));
            }
            if (mb_strlen($t, 'UTF-8') > 6000) {
                $t = mb_substr($t, 0, 6000, 'UTF-8');
            }
            $text = self::textExcerpt($t, 5000);
        }

        $prompt = "你是网站「{$siteTitle}」的 AI 助手，正在文章《{$postTitle}》（发布于 {$postDate}）页面下方与访客对话。\n";
        if ($summary !== '') {
            $prompt .= "\n【本文摘要】\n" . $summary . "\n";
        }
        if ($text !== '') {
            $prompt .= "\n【本文内容（节选）】\n" . $text . "\n";
        }
        if ($postUrl !== '') {
            $prompt .= "\n本文链接：" . $postUrl . "\n";
        }
        $prompt .= "\n你可以调用工具查询全站公开信息（搜索文章、搜索评论、获取站点统计等），请优先基于工具结果与上文回答，不要编造不存在的内容。\n"
            . "规则：\n"
            . "1. 使用简体中文，回答简洁友好，适合网页聊天气泡展示。\n"
            . "2. 用户询问本站内容时优先调用工具查询，不要凭空猜测。\n"
            . "3. 涉及他人隐私（邮箱、IP、账号）、密码文章或受保护内容的问题，一律婉拒并说明仅提供公开信息。\n"
            . "4. 与站点无关的问题可以简短回答，但优先引导回站内话题。";

        return self::appendCustomPrompt($prompt, $options);
    }

    /** 追加管理员自定义提示词 */
    private static function appendCustomPrompt($prompt, $options)
    {
        $custom = isset($options->aiChatSystemPrompt) ? trim((string)$options->aiChatSystemPrompt) : '';
        if ($custom !== '') {
            $prompt .= "\n\n【站长补充要求】\n" . mb_substr($custom, 0, 1000, 'UTF-8');
        }
        return $prompt;
    }

    /**
     * 获取 AI 提供商实例（与 AiSummary::getProvider 相同的选择逻辑）
     */
    private static function getProvider()
    {
        $_aiProviderFile = dirname(__FILE__) . '/ai-provider.php';
        if (!class_exists('AiProvider') && file_exists($_aiProviderFile)) {
            require_once $_aiProviderFile;
        }
        if (!class_exists('AiProvider')) {
            return null;
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

// 供独立 AJAX 入口引用的守卫常量
if (!defined('SHUFEI_AI_CHAT_OK')) {
    define('SHUFEI_AI_CHAT_OK', 1);
}
