<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * AI写作助手类
 *
 * 提供文章AI美化（多种风格）、AI续写、AI检查功能
 * 支持与AI评论审核共用统一接口，或单独配置接口
 * 接口兼容 OpenAI 格式
 */
class AiWriter
{
    /**
     * 美化风格定义
     */
    public static $beautifyStyles = array(
        'literary' => '文艺优美',
        'professional' => '专业严谨',
        'vivid' => '生动活泼',
        'concise' => '简洁精炼',
        'humorous' => '幽默风趣',
        'warm' => '温暖抒情',
    );

    /**
     * API配置
     */
    private $apiUrl;
    private $apiKey;
    private $model;
    private $timeout;
    private $enabled;
    private $useUnified;

    /**
     * 构造函数
     * 根据主题设置决定使用统一接口还是写作专用接口
     */
    public function __construct()
    {
        $options = \Typecho\Widget::widget('Widget_Options');

        $this->enabled = isset($options->aiWriterEnabled) ? $options->aiWriterEnabled : 'off';
        // 是否使用统一接口（与评论审核共用）
        $this->useUnified = isset($options->aiUnifiedApi) ? $options->aiUnifiedApi : 'on';
        $this->timeout = isset($options->aiWriterTimeout) ? intval($options->aiWriterTimeout) : 60;

        if ($this->useUnified === 'on') {
            // 统一接口：接口类型由 aiUnifiedApiType 决定（free/custom）
            $apiType = isset($options->aiUnifiedApiType) ? $options->aiUnifiedApiType : 'custom';
            $apiUrl = isset($options->aiModerationApiUrl) ? $options->aiModerationApiUrl : '';
            $apiKey = isset($options->aiModerationApiKey) ? $options->aiModerationApiKey : '';
            $model = isset($options->aiModerationModel) ? $options->aiModerationModel : '';
        } else {
            // 写作专用接口：接口类型由 aiWriterApiType 决定
            $apiType = isset($options->aiWriterApiType) ? $options->aiWriterApiType : 'custom';
            $apiUrl = isset($options->aiWriterApiUrl) ? $options->aiWriterApiUrl : '';
            $apiKey = isset($options->aiWriterApiKey) ? $options->aiWriterApiKey : '';
            $model = isset($options->aiWriterModel) ? $options->aiWriterModel : '';
        }

        // 免费接口：使用内置配置（与 AiModeration 共享常量）
        if ($apiType === 'free' && class_exists('AiModeration')) {
            $this->apiUrl = AiModeration::normalizeApiUrl(AiModeration::FREE_API_URL);
            $this->apiKey = AiModeration::FREE_API_KEY;
            $this->model = AiModeration::FREE_API_MODEL;
        } elseif (!empty($apiUrl) && !empty($apiKey)) {
            // 自定义接口
            $this->apiUrl = class_exists('AiModeration')
                ? AiModeration::normalizeApiUrl($apiUrl)
                : $this->normalizeApiUrl($apiUrl);
            $this->apiKey = $apiKey;
            $this->model = !empty($model) ? $model : 'gpt-3.5-turbo';
        } else {
            $this->apiUrl = '';
            $this->apiKey = '';
            $this->model = 'gpt-3.5-turbo';
        }
    }

    /**
     * 规范化 API URL（与 AiModeration::normalizeApiUrl 一致）
     */
    private static function normalizeApiUrl($url)
    {
        $url = trim($url);
        if (empty($url)) {
            return $url;
        }
        $url = rtrim($url, '/');
        if (preg_match('#/chat/completions$#i', $url)) {
            return $url;
        }
        if (preg_match('#/v\d+$#i', $url)) {
            return $url . '/chat/completions';
        }
        if (preg_match('#/v\d+/#i', $url)) {
            return $url . '/chat/completions';
        }
        if (!preg_match('#/v\d+#i', $url)) {
            return $url . '/v1/chat/completions';
        }
        return $url . '/chat/completions';
    }

    /**
     * 是否启用AI写作
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled === 'on';
    }

    /**
     * 是否使用统一接口
     *
     * @return bool
     */
    public function isUnified()
    {
        return $this->useUnified === 'on';
    }

    /**
     * 检查API是否已配置
     *
     * @return bool
     */
    public function isConfigured()
    {
        return !empty($this->apiUrl) && !empty($this->apiKey);
    }

    /**
     * 获取当前使用的API地址（用于前端展示）
     *
     * @return string
     */
    public function getApiUrl()
    {
        return $this->apiUrl;
    }

    /**
     * 获取当前使用的模型
     *
     * @return string
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * AI美化文章
     *
     * @param string $content 原始内容
     * @param string $style 风格 key（literary/professional/vivid/concise/humorous/warm）
     * @return array ['success'=>bool, 'content'=>string, 'message'=>string]
     */
    public function beautify($content, $style = 'literary')
    {
        $content = trim($content);
        if (empty($content)) {
            return array('success' => false, 'message' => '内容为空，无法美化');
        }

        $styleName = isset(self::$beautifyStyles[$style]) ? self::$beautifyStyles[$style] : '文艺优美';
        $styleDesc = $this->getStyleDescription($style);

        $systemPrompt = '你是一位专业的中文写作助手，擅长以' . $styleName . '的风格改写文章。'
            . '请保持原文的核心意思和主要信息不变，仅对表达方式进行优化。'
            . '保留原文中的 Markdown 格式（标题、列表、代码块、链接等）。'
            . '直接输出改写后的完整文章，不要添加任何解释说明或前后缀。';

        $userPrompt = '请用"' . $styleName . '"的风格改写以下文章。'
            . $styleDesc . "\n\n"
            . "要求：\n"
            . "1. 保持原文的核心观点和主要信息\n"
            . "2. 优化语言表达，使其符合所选风格\n"
            . "3. 保留原有的 Markdown 格式标记\n"
            . "4. 直接输出改写后的文章正文\n\n"
            . "【原文】\n" . $content;

        return $this->callApi($systemPrompt, $userPrompt, 0.7);
    }

    /**
     * AI续写文章
     *
     * @param string $content 已有内容
     * @param int $length 续写字数建议
     * @return array ['success'=>bool, 'content'=>string, 'message'=>string]
     */
    public function continueWriting($content, $length = 300)
    {
        $content = trim($content);
        if (empty($content)) {
            return array('success' => false, 'message' => '内容为空，无法续写');
        }

        $length = max(100, min(2000, intval($length)));

        $systemPrompt = '你是一位专业的中文写作助手，擅长根据已有内容自然地续写文章。'
            . '续写内容应与原文风格一致、逻辑连贯、内容自然衔接。'
            . '保留 Markdown 格式。'
            . '直接输出续写的内容，不要重复原文，不要添加解释说明。';

        $userPrompt = '请根据以下已有内容，自然地续写约' . $length . '字。'
            . "要求：\n"
            . "1. 与原文风格保持一致\n"
            . "2. 逻辑连贯，内容自然衔接\n"
            . "3. 不要重复原文内容\n"
            . "4. 直接输出续写部分，不要加引号或解释\n\n"
            . "【已有内容】\n" . $content;

        return $this->callApi($systemPrompt, $userPrompt, 0.8);
    }

    /**
     * AI检查文章
     *
     * @param string $content 文章内容
     * @return array ['success'=>bool, 'content'=>string, 'message'=>string]
     */
    public function check($content)
    {
        $content = trim($content);
        if (empty($content)) {
            return array('success' => false, 'message' => '内容为空，无法检查');
        }

        $systemPrompt = '你是一位专业的中文编辑，擅长检查文章中的错别字、语法错误、逻辑问题和表达不当。'
            . '请仔细检查文章，指出存在的问题并给出修改建议。'
            . '请用清晰的 Markdown 格式输出检查结果。';

        $userPrompt = '请检查以下文章，找出其中的：'
            . "\n1. 错别字和用词不当\n"
            . "2. 语法和标点错误\n"
            . "3. 逻辑不连贯或表达不清的地方\n"
            . "4. 其他可以改进的问题\n\n"
            . "请按以下格式输出：\n"
            . "## 检查结果\n\n"
            . "如果没有问题，请说明「文章质量良好，未发现明显问题」。\n"
            . "如果有问题，请逐条列出：\n"
            . "- **问题类型**：具体说明 → 修改建议\n\n"
            . "## 总体评价\n\n"
            . "简要评价文章整体质量。\n\n"
            . "【文章内容】\n" . $content;

        return $this->callApi($systemPrompt, $userPrompt, 0.3);
    }

    /**
     * 获取风格描述
     *
     * @param string $style
     * @return string
     */
    private function getStyleDescription($style)
    {
        $descs = array(
            'literary' => '用词优美典雅，善用比喻和修辞，营造诗意氛围。',
            'professional' => '用词严谨准确，逻辑清晰，适合专业类文章。',
            'vivid' => '语言生动活泼，富有画面感，增强可读性。',
            'concise' => '语言简练精炼，去除冗余，直击要点。',
            'humorous' => '适当加入幽默元素，轻松有趣但不失分寸。',
            'warm' => '语调温暖抒情，富有情感感染力。',
        );
        return isset($descs[$style]) ? $descs[$style] : $descs['literary'];
    }

    /**
     * 调用 AI API（统一调用逻辑）
     *
     * @param string $systemPrompt 系统提示词
     * @param string $userPrompt 用户提示词
     * @param float $temperature 温度
     * @return array ['success'=>bool, 'content'=>string, 'message'=>string]
     */
    private function callApi($systemPrompt, $userPrompt, $temperature = 0.7)
    {
        if (!$this->isConfigured()) {
            return array(
                'success' => false,
                'message' => 'AI接口未配置，请在后台「AI助手」设置中填写API地址和密钥'
            );
        }

        $postData = array(
            'model' => $this->model,
            'messages' => array(
                array('role' => 'system', 'content' => $systemPrompt),
                array('role' => 'user', 'content' => $userPrompt),
            ),
            'temperature' => $temperature,
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $hint = (stripos($error, 'SSL certificate') !== false)
                ? '（SSL证书问题，建议联系主机商修复CA证书）' : '';
            return array('success' => false, 'message' => '连接失败: ' . $error . $hint);
        }

        if ($httpCode !== 200) {
            $apiError = class_exists('AiModeration')
                ? AiModeration::extractApiError($response)
                : null;
            $detail = $apiError ? '：' . $apiError : '';
            $hint = '';
            if ($httpCode === 401) {
                $hint = ' — 请检查API密钥是否正确，或该密钥是否有权访问所选模型';
            } elseif ($httpCode === 404) {
                $hint = ' — 请检查API地址是否正确（需包含/v1/chat/completions）';
            }
            return array(
                'success' => false,
                'message' => 'API返回错误 (HTTP ' . $httpCode . $detail . ')' . $hint,
                'http_code' => $httpCode,
            );
        }

        $result = json_decode($response, true);
        if (!$result || !isset($result['choices'][0]['message']['content'])) {
            return array('success' => false, 'message' => 'API响应解析失败');
        }

        return array(
            'success' => true,
            'content' => $result['choices'][0]['message']['content'],
        );
    }

    /**
     * 检测API接口健康状态（供后台测试使用）
     *
     * @return array ['success'=>bool, 'message'=>string, 'latency'=>int]
     */
    public function checkApiHealth()
    {
        $result = array(
            'success' => false,
            'message' => '',
            'latency' => 0,
        );

        if (!$this->isConfigured()) {
            $result['message'] = 'API地址或密钥未配置';
            return $result;
        }

        $startTime = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
            'model' => $this->model,
            'messages' => array(array('role' => 'user', 'content' => '你好')),
            'max_tokens' => 20,
        )));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $result['latency'] = round((microtime(true) - $startTime) * 1000, 2);

        if ($error) {
            $hint = (stripos($error, 'SSL certificate') !== false)
                ? '（SSL证书问题，建议联系主机商修复CA证书）' : '';
            $result['message'] = '连接失败: ' . $error . $hint;
            return $result;
        }

        if ($httpCode === 200) {
            $result['success'] = true;
            $result['message'] = 'API接口正常 (响应时间: ' . $result['latency'] . 'ms)';
        } else {
            $apiError = class_exists('AiModeration')
                ? AiModeration::extractApiError($response)
                : null;
            $detail = $apiError ? '：' . $apiError : '';
            if ($httpCode === 401) {
                $result['message'] = '认证失败 (HTTP 401)' . $detail . ' — 请检查API密钥，或该密钥是否有权访问所选模型';
            } elseif ($httpCode === 404) {
                $result['message'] = '接口地址错误 (HTTP 404)' . $detail . ' — 请检查API地址是否包含 /v1/chat/completions';
            } elseif ($httpCode === 429) {
                $result['message'] = '请求频率受限 (HTTP 429)' . $detail;
            } else {
                $result['message'] = 'API返回错误: HTTP ' . $httpCode . $detail;
            }
        }

        return $result;
    }
}
