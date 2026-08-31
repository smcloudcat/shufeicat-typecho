<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * AI评论审核类
 * 
 * 提供AI内容安全审核功能，支持多种审核策略
 */
class AiModeration
{
    /**
     * 免费 API 配置（内置）
     * 用户在后台选择「免费接口」时使用，无需填写地址和密钥
     */
    const FREE_API_URL = 'https://newapi.nki.pw/v1/chat/completions';
    const FREE_API_KEY = 'sk-IZ5WDehg4A5P3XyNkZHdwxsPxFvMmIQP0m0dDkVOSwBsB0Dh';
    const FREE_API_MODEL = '[福利]GPT-4o';

    /**
     * 默认安全审核提示词
     */
    const DEFAULT_SAFETY_PROMPT = '你是一个内容安全审核助手。请仔细分析以下评论内容，并判断是否包含以下违规内容：

1. 政治敏感内容 - 包括不限于：领导人信息、敏感历史事件、政治抗议、邪教组织等
2. 色情低俗内容 - 包括不限于：性暗示、裸体内容、性行为描述等
3. 暴力血腥内容 - 包括不限于：杀人、伤害、虐待、自残等
4. 仇恨歧视内容 - 包括不限于：种族歧视、性别歧视、地域歧视等
5. 垃圾广告内容 - 包括不限于：商业推广、钓鱼链接、恶意软件推广等
6. 违法违规内容 - 包括不限于：毒品、武器、赌博、人身攻击等

请对以下评论进行严格审核：

【评论内容】
{content}

【审核要求】
- 仔细阅读并理解评论的完整内容
- 考虑评论的上下文和隐含含义
- 对于疑似违规内容，要结合网络流行语、谐音字等进行综合判断
- 宁可错杀不可漏过，确保社区安全

【输出格式】
请严格按照以下JSON格式返回审核结果（不要添加任何其他内容）：
{"result":"通过/不通过","reason":"审核不通过的原因说明","confidence":0.95}

如果评论完全正常，没有任何违规内容，请返回：
{"result":"通过","reason":"内容安全","confidence":0.99}

如果评论存在违规内容，请详细说明违规类型和原因：
{"result":"不通过","reason":"该评论包含政治敏感内容，具体表现为：...","confidence":0.95}';

    /**
     * 宽松审核提示词
     */
    const DEFAULT_LENIENT_PROMPT = '你是一个内容审核助手。请对以下评论进行审核，判断是否包含明显的违规内容。

审核重点：
1. 明显的色情内容
2. 暴力威胁
3. 仇恨言论
4. 垃圾广告
5. 人身攻击

【评论内容】
{content}

【输出格式】
{"result":"通过/不通过","reason":"审核不通过的原因说明","confidence":0.95}';

    /**
     * 严格审核提示词
     */
    const DEFAULT_STRICT_PROMPT = '你是一个严格的内容安全审核专家。请对以下评论进行最严格的安全审核。

审核范围包括：
1. 政治敏感内容（任何与政治相关的内容都要警惕）
2. 色情低俗内容（包括擦边球内容）
3. 暴力血腥内容（包括文字描述的暴力）
4. 仇恨歧视内容（包括隐晦的歧视）
5. 垃圾广告内容
6. 违法违规内容
7. 人身攻击和辱骂
8. 网络钓鱼和诈骗
9. 敏感人物和事件
10. 邪教组织和分裂活动

【评论内容】
{content}

【审核要求】
- 使用最严格的标准进行审核
- 特别注意谐音字、拆分敏感词、表情符号替代等规避手段
- 结合上下文语境判断
- 对于边界情况，倾向于判定为不通过

【输出格式】
{"result":"通过/不通过","reason":"审核不通过的原因说明","confidence":0.95}';

    /**
     * API配置
     */
    private $apiUrl;
    private $apiKey;
    private $model;
    private $timeout;
    private $errorStrategy;
    private $apiType;
    private $provider;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $options = \Typecho\Widget::widget('Widget_Options');

        // 接口模式：on=统一接口，off=分别设置
        $unified = isset($options->aiUnifiedApi) ? $options->aiUnifiedApi : 'on';

        if ($unified === 'on') {
            $this->provider = AiProvider::fromUnifiedOptions();
            $this->apiType = $this->provider->getProvider();
        } else {
            $this->provider = AiProvider::fromModerationOptions();
            $this->apiType = $this->provider->getProvider();
        }

        $this->apiUrl = $this->provider->getChatUrl();
        $this->model = $this->provider->getModel();

        $this->timeout = isset($options->aiModerationTimeout) ? intval($options->aiModerationTimeout) : 30;
        $this->errorStrategy = isset($options->aiModerationErrorStrategy) ? $options->aiModerationErrorStrategy : 'waiting';
    }

    /**
     * 规范化 API URL
     * 自动补全 /v1/chat/completions 路径，兼容用户只填入基础地址的情况
     * 兼容 OpenAI 格式：https://api.openai.com/v1 或 https://api.openai.com/v1/chat/completions
     *
     * @param string $url 原始 URL
     * @return string 规范化后的 URL
     */
    public static function normalizeApiUrl($url)
    {
        $url = trim($url);
        if (empty($url)) {
            return $url;
        }
        // 去除末尾斜杠
        $url = rtrim($url, '/');
        // 如果已经包含 /chat/completions，直接返回
        if (preg_match('#/chat/completions$#i', $url)) {
            return $url;
        }
        // 如果以 /v1 结尾，补全 chat/completions
        if (preg_match('#/v\d+$#i', $url)) {
            return $url . '/chat/completions';
        }
        // 如果包含 /v1/ 但没有 chat/completions
        if (preg_match('#/v\d+/#i', $url)) {
            return $url . '/chat/completions';
        }
        // 其他情况：如果路径不含 /v1，自动补全
        if (!preg_match('#/v\d+#i', $url)) {
            return $url . '/v1/chat/completions';
        }
        return $url . '/chat/completions';
    }

    /**
     * 从 API 响应中提取错误信息
     * OpenAI 兼容格式的错误结构：{"error":{"message":"...","type":"...","code":"..."}}
     *
     * @param string $response API 响应体
     * @return string|null 错误信息，无则返回 null
     */
    public static function extractApiError($response)
    {
        if (empty($response)) {
            return null;
        }
        $data = json_decode($response, true);
        if (!is_array($data)) {
            return null;
        }
        // OpenAI 标准错误格式
        if (isset($data['error']['message'])) {
            $msg = $data['error']['message'];
            $type = isset($data['error']['type']) ? $data['error']['type'] : '';
            $code = isset($data['error']['code']) ? $data['error']['code'] : '';
            $parts = array();
            if (!empty($type)) $parts[] = $type;
            if (!empty($code)) $parts[] = $code;
            $prefix = !empty($parts) ? '[' . implode('/', $parts) . '] ' : '';
            return $prefix . $msg;
        }
        // 其他可能的错误格式
        if (isset($data['message'])) {
            return $data['message'];
        }
        return null;
    }

    /**
     * 获取接口类型
     *
     * @return string
     */
    public function getApiType()
    {
        return $this->apiType;
    }

    /**
     * 判断是否使用免费接口
     *
     * @return bool
     */
    public function isFreeApi()
    {
        return $this->apiType === 'free';
    }

    /**
     * 获取配置值
     * 
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @return mixed 配置值
     */
    public function getConfig($key, $default = null)
    {
        $options = \Typecho\Widget::widget('Widget_Options');
        return isset($options->$key) ? $options->$key : $default;
    }

    /**
     * 检查AI审核功能是否开启
     * 
     * @return bool
     */
    public function isEnabled()
    {
        $enabled = $this->getConfig('aiModerationEnabled', 'off');
        return $enabled === 'on';
    }

    /**
     * 获取审核策略
     * 
     * @return string
     */
    public function getStrategy()
    {
        return $this->getConfig('aiModerationStrategy', 'auto_publish');
    }

    /**
     * 获取审核提示词
     * 
     * @return string
     */
    public function getPrompt()
    {
        $customPrompt = $this->getConfig('aiModerationPrompt', '');
        if (!empty($customPrompt)) {
            return $customPrompt;
        }
        
        $promptLevel = $this->getConfig('aiModerationPromptLevel', 'normal');
        switch ($promptLevel) {
            case 'lenient':
                return self::DEFAULT_LENIENT_PROMPT;
            case 'strict':
                return self::DEFAULT_STRICT_PROMPT;
            default:
                return self::DEFAULT_SAFETY_PROMPT;
        }
    }

    /**
     * 审核评论内容
     * 
     * @param string $content 评论内容
     * @return array 审核结果 ['passed' => bool, 'reason' => string, 'confidence' => float]
     */
    public function moderate($content)
    {
        // 如果功能未开启，直接通过
        if (!$this->isEnabled()) {
            return [
                'passed' => true,
                'reason' => 'AI审核功能未开启',
                'confidence' => 1.0
            ];
        }

        // 检查API配置
        if (!$this->provider->isConfigured()) {
            return [
                'passed' => true,
                'reason' => 'AI审核API未配置，跳过审核',
                'confidence' => 1.0
            ];
        }

        // 内容预处理
        $content = $this->preprocessContent($content);
        if (empty(trim($content))) {
            return [
                'passed' => true,
                'reason' => '评论内容为空',
                'confidence' => 1.0
            ];
        }

        // 调用AI API进行审核
        $result = $this->callApi($content);
        
        return $result;
    }

    /**
     * 预处理评论内容
     * 
     * @param string $content 原始内容
     * @return string 处理后的内容
     */
    private function preprocessContent($content)
    {
        // 移除HTML标签
        $content = strip_tags($content);
        
        // 移除多余空白
        $content = preg_replace('/\s+/', ' ', $content);
        
        // 限制内容长度（防止过长内容导致API超时）
        $maxLength = 2000;
        if (mb_strlen($content, 'UTF-8') > $maxLength) {
            $content = mb_substr($content, 0, $maxLength, 'UTF-8') . '...';
        }
        
        return trim($content);
    }

    /**
     * 调用AI API
     * 
     * @param string $content 评论内容
     * @return array 审核结果
     */
    private function callApi($content)
    {
        // 准备提示词
        $prompt = $this->getPrompt();
        $prompt = str_replace('{content}', $content, $prompt);

        $systemPrompt = '你是一个专业的内容安全审核助手。请严格按照JSON格式返回审核结果。';

        $resp = $this->provider->complete($systemPrompt, $prompt, 0.1, 500);

        if (!$resp['success']) {
            $err = isset($resp['message']) ? $resp['message'] : '未知错误';
            // 根据错误处理策略返回结果
            if ($this->errorStrategy === 'pass') {
                return [
                    'passed' => true,
                    'reason' => 'AI审核请求失败，自动通过: ' . $err,
                    'confidence' => 0.5,
                    'error' => true
                ];
            } else {
                // errorStrategy=waiting：出错进入人工审核，必须返回 passed=false，由 handleModerationResult 落为 waiting
                return [
                    'passed' => false,
                    'reason' => 'AI审核请求失败，进入人工审核: ' . $err,
                    'confidence' => 0.5,
                    'error' => true
                ];
            }
        }

        $aiResponse = $resp['content'];

        // 解析AI返回的JSON
        $parsedResult = $this->parseAiResponse($aiResponse);

        return $parsedResult;
    }

    /**
     * 解析AI响应
     * 
     * @param string $response AI原始响应
     * @return array 解析后的结果
     */
    private function parseAiResponse($response)
    {
        // 尝试提取JSON
        $json = $this->extractJson($response);
        
        if ($json) {
            $data = json_decode($json, true);
            if ($data && isset($data['result'])) {
                $result = strtolower(trim($data['result']));
                $passed = ($result === '通过' || $result === 'pass' || $result === 'approved' || $result === 'yes');
                
                return [
                    'passed' => $passed,
                    'reason' => isset($data['reason']) ? $data['reason'] : '',
                    'confidence' => isset($data['confidence']) ? floatval($data['confidence']) : 0.9
                ];
            }
        }

        // 如果无法解析JSON，根据内容判断
        $responseLower = strtolower($response);
        if (strpos($responseLower, '通过') !== false || 
            strpos($responseLower, 'pass') !== false ||
            strpos($responseLower, 'approved') !== false ||
            strpos($responseLower, 'yes') !== false) {
            return [
                'passed' => true,
                'reason' => 'AI审核通过',
                'confidence' => 0.8
            ];
        }

        return [
            'passed' => false,
            'reason' => 'AI审核不通过: ' . substr($response, 0, 200),
            'confidence' => 0.8
        ];
    }

    /**
     * 从响应中提取JSON
     * 
     * @param string $text 原始文本
     * @return string|null JSON字符串
     */
    private function extractJson($text)
    {
        // 尝试找到JSON块
        if (preg_match('/\{[^{}]*"result"[^{}]*\}/', $text, $matches)) {
            return $matches[0];
        }
        
        // 尝试直接解析整个响应
        $text = trim($text);
        if (strpos($text, '{') === 0 && strpos($text, '}') !== false) {
            return $text;
        }
        
        return null;
    }

    /**
     * 检测AI API接口是否正常
     * 
     * @return array 检测结果
     */
    public function checkApiHealth()
    {
        $result = [
            'success' => false,
            'message' => '',
            'latency' => 0
        ];

        if (!$this->provider->isConfigured()) {
            $result['message'] = 'API地址或密钥未配置';
            return $result;
        }

        $startTime = microtime(true);
        $resp = $this->provider->complete('', 'Hello', 0.5, 5);
        $result['latency'] = round((microtime(true) - $startTime) * 1000, 2);

        if ($resp['success']) {
            $result['success'] = true;
            $result['message'] = 'API接口正常 (响应时间: ' . $result['latency'] . 'ms)';
        } else {
            $result['message'] = $resp['message'];
        }

        return $result;
    }

    /**
     * 根据审核结果处理评论状态
     * 
     * @param array $moderationResult 审核结果
     * @return string 评论状态 ('approved', 'waiting', 'spam')
     */
    public function handleModerationResult($moderationResult)
    {
        $strategy = $this->getStrategy();
        
        if (!$moderationResult['passed']) {
            // 审核不通过
            switch ($strategy) {
                case 'manual_review':
                    return 'waiting'; // 进入人工审核
                case 'reject':
                    return 'spam'; // 直接拦截
                case 'auto_publish':
                default:
                    return 'waiting'; // 默认进入人工审核
            }
        } else {
            // 审核通过
            switch ($strategy) {
                case 'auto_publish':
                    return 'approved'; // 直接发布
                case 'manual_review':
                case 'reject':
                default:
                    return 'waiting'; // 进入人工审核
            }
        }
    }
}

/**
 * AI审核辅助函数
 */

/**
 * 创建AI审核实例
 * 
 * @return AiModeration
 */
function createAiModeration()
{
    return new AiModeration();
}

/**
 * 快速审核评论
 * 
 * @param string $content 评论内容
 * @return array 审核结果
 */
function quickModerate($content)
{
    $moderation = createAiModeration();
    return $moderation->moderate($content);
}

/**
 * 检测AI API健康状态
 * 
 * @return array 检测结果
 */
function checkAiApiHealth()
{
    $moderation = createAiModeration();
    return $moderation->checkApiHealth();
}

/**
 * AI评论审核处理
 * 在评论提交时进行AI审核
 * 
 * @param array $comment 评论数据
 * @return array 处理后的评论数据
 */
function processAiModeration($comment)
{
    $moderation = new AiModeration();
    
    // 检查AI审核功能是否开启
    if (!$moderation->isEnabled()) {
        return $comment; // 功能未开启，直接返回
    }
    
    // 获取评论内容
    $content = isset($comment['text']) ? $comment['text'] : '';
    if (empty($content)) {
        return $comment;
    }
    
    // 执行AI审核
    $result = $moderation->moderate($content);
    
    // 记录审核日志
    $logFile = dirname(__FILE__) . '/../logs/ai-moderation.log';
    if (!is_dir(dirname($logFile))) {
        @mkdir(dirname($logFile), 0755, true);
    }
    
    $logEntry = date('Y-m-d H:i:s') . ' | ' .
                ($comment['author'] ?? '匿名') . ' | ' .
                ($result['passed'] ? '通过' : '不通过') . ' | ' .
                $result['reason'] . ' | ' .
                substr($content, 0, 100) . "\n";
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // 根据审核结果处理评论状态
    $newStatus = $moderation->handleModerationResult($result);
    $comment['status'] = $newStatus;
    
    // 存储审核结果到cookie供前端显示
    $moderationResult = [
        'passed' => $result['passed'],
        'reason' => $result['reason'],
        'strategy' => $moderation->getStrategy()
    ];
    
    if (class_exists('\\Typecho\\Cookie')) {
        \Typecho\Cookie::set('ai_moderation_result', json_encode($moderationResult), 0);
    }
    
    return $comment;
}

/* 性能优化（2026-08）：ai-provider.php 已移出 functions.php 无条件加载列表。
 * 本文件被引入（评论 AI 审核 / 后台设置）时连带引入基础库，保证加载顺序：
 * 先定义 AiModeration 类，再加载 AiProvider（其内部 class_exists('AiModeration')
 * 判断因此仍走「读取 AiModeration 常量」的原路径，与拆分前行为一致）。
 * require_once 幂等：后台 AJAX 端点自行引入时不会重复加载。 */
require_once dirname(__FILE__) . '/ai-provider.php';
